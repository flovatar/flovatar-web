#!/usr/bin/env python3
"""
ĐUST Quests payout worker (KB notes/dust-quests.md).

Runs on the PAYOUT box (never on the web server): it holds the rewards-wallet key (via a flow CLI config), pulls a
batch of verified claims from the web app over an HMAC-signed API, re-checks its own limits, sends ONE transaction for
N recipients, and reports the tx id (the web app verifies the transaction on chain before marking claims paid).

Safety:
  - the web app never sees the key; a compromised web app can at most get this worker to pay within ITS limits
    (per-account cap, daily cap, season budget, max per claim — tracked in the local journal) and the wallet balance;
  - idempotent: a batch is journaled BEFORE sending; a batch with a tx id is only ever re-reported, never re-sent;
    if a send ends without a tx id the batch stays open and the worker prints an ERROR for a manual check
    (`--report BATCH TX` or `--release BATCH REASON`);
  - one run at a time (flock).

Output: nothing when all is well (cron-friendly); one line per problem otherwise. `-v` for a verbose manual run.
Config: KEY=VALUE file (default ~/secure/rewards-testnet/worker.env, override with DUST_QUESTS_WORKER_ENV).
Stdlib only + the flow CLI.
"""
import fcntl
import hashlib
import hmac
import json
import os
import re
import secrets
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request
from datetime import datetime, timezone
from decimal import Decimal, ROUND_DOWN

ENV_PATH = os.path.expanduser(os.environ.get('DUST_QUESTS_WORKER_ENV', '~/secure/rewards-testnet/worker.env'))
VERBOSE = '-v' in sys.argv

CADENCE = """import FungibleToken from {ft}
import FlovatarDustToken from {dust}

// ĐUST Quests payout: one transaction for N recipients from the rewards wallet. The claim ids are recorded as
// arguments so the web app can match this transaction to its payout batch (idempotency by claim id).
transaction(recipients: [Address], amounts: [UFix64], claimIds: [UInt64], maxTotal: UFix64) {{
    let vault: auth(FungibleToken.Withdraw) &FlovatarDustToken.Vault

    prepare(signer: auth(BorrowValue) &Account) {{
        self.vault = signer.storage.borrow<auth(FungibleToken.Withdraw) &FlovatarDustToken.Vault>(from: FlovatarDustToken.VaultStoragePath)
            ?? panic("rewards wallet has no DUST vault")
    }}

    pre {{
        recipients.length == amounts.length && amounts.length == claimIds.length: "length mismatch"
        recipients.length > 0 && recipients.length <= 50: "batch size"
    }}

    execute {{
        var total = 0.0
        var i = 0
        while i < recipients.length {{
            total = total + amounts[i]
            let receiver = getAccount(recipients[i]).capabilities.borrow<&{{FungibleToken.Receiver}}>(FlovatarDustToken.VaultReceiverPath)
                ?? panic("recipient has no DUST receiver")
            receiver.deposit(from: <- self.vault.withdraw(amount: amounts[i]))
            i = i + 1
        }}
        assert(total <= maxTotal, message: "batch total over maxTotal")
    }}
}}
"""

RECEIVERS = """import FungibleToken from {ft}
import FlovatarDustToken from {dust}
access(all) fun main(addrs: [Address], me: Address): [UFix64?] {{
    let out: [UFix64?] = []
    for a in addrs {{
        let ok = getAccount(a).capabilities.get<&{{FungibleToken.Receiver}}>(FlovatarDustToken.VaultReceiverPath).check()
        out.append(ok ? 0.0 : nil)
    }}
    out.append(getAccount(me).capabilities.borrow<&{{FungibleToken.Balance}}>(FlovatarDustToken.VaultBalancePath)?.balance)
    return out
}}
"""


def log(msg):
    if VERBOSE:
        print(msg)


def problem(msg):
    print('⚠️ dust-quests payout: ' + msg)


def load_env():
    cfg = {}
    with open(ENV_PATH) as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith('#') or '=' not in line:
                continue
            k, v = line.split('=', 1)
            cfg[k.strip()] = v.strip().strip('"').strip("'")
    need = ['QUESTS_API', 'QUESTS_PAYOUT_SECRET', 'REWARDS_ADDRESS', 'FLOW_JSON', 'FLOW_SIGNER', 'FLOW_NETWORK', 'FLOW_REST', 'FT_ADDRESS', 'DUST_ADDRESS']
    missing = [k for k in need if not cfg.get(k)]
    if missing:
        raise SystemExit('⚠️ dust-quests payout: config missing ' + ','.join(missing))
    if cfg['FLOW_NETWORK'] == 'mainnet' and cfg.get('ALLOW_MAINNET') != 'yes':
        raise SystemExit('⚠️ dust-quests payout: mainnet needs ALLOW_MAINNET=yes in the worker config')
    return cfg


def D(v):
    return Decimal(str(v)).quantize(Decimal('0.00000001'), rounding=ROUND_DOWN)


def ufix(v):
    return format(D(v), 'f')


# ------------------------------------------------------------------ journal

def journal_path(cfg):
    return os.path.expanduser(cfg.get('JOURNAL', '~/secure/rewards-testnet/journal.json'))


def load_journal(cfg):
    p = journal_path(cfg)
    if not os.path.exists(p):
        return {'batches': {}, 'claims': {}, 'per_address': {}, 'per_day': {}, 'season_total': '0'}
    with open(p) as f:
        return json.load(f)


def save_journal(cfg, j):
    p = journal_path(cfg)
    fd, tmp = tempfile.mkstemp(dir=os.path.dirname(p), prefix='.journal-')
    with os.fdopen(fd, 'w') as f:
        json.dump(j, f, indent=1, sort_keys=True)
    os.chmod(tmp, 0o600)
    os.replace(tmp, p)


def ledger_add(j, items, sign=1):
    day = datetime.now(timezone.utc).strftime('%Y-%m-%d')
    for it in items:
        a = it['address']
        j['per_address'][a] = str(D(j['per_address'].get(a, '0')) + sign * D(it['amount']))
        j['per_day'][day] = str(D(j['per_day'].get(day, '0')) + sign * D(it['amount']))
        j['season_total'] = str(D(j.get('season_total', '0')) + sign * D(it['amount']))
        if sign > 0:
            j['claims'][str(it['claim_id'])] = it.get('tx_id') or 'sending'
        else:
            j['claims'].pop(str(it['claim_id']), None)


# ------------------------------------------------------------------ web API (HMAC)

def call(cfg, path, body):
    raw = json.dumps(body, separators=(',', ':')).encode()
    ts = str(int(time.time()))
    nonce = secrets.token_urlsafe(18)
    msg = '\n'.join([ts, nonce, 'POST', path, hashlib.sha256(raw).hexdigest()]).encode()
    sig = hmac.new(cfg['QUESTS_PAYOUT_SECRET'].encode(), msg, hashlib.sha256).hexdigest()
    req = urllib.request.Request(cfg['QUESTS_API'].rstrip('/') + path, data=raw, method='POST', headers={
        'Content-Type': 'application/json', 'Accept': 'application/json', 'User-Agent': 'flovatar-quests-payout/1',
        'X-Quests-Timestamp': ts, 'X-Quests-Nonce': nonce, 'X-Quests-Signature': sig,
    })
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return r.status, json.loads(r.read().decode() or '{}')
    except urllib.error.HTTPError as e:
        try:
            return e.code, json.loads(e.read().decode() or '{}')
        except Exception:
            return e.code, {'error': 'http %d' % e.code}


# ------------------------------------------------------------------ chain

def script(cfg, code, args):
    body = json.dumps({'script': __import__('base64').b64encode(code.encode()).decode(),
                       'arguments': [__import__('base64').b64encode(json.dumps(a).encode()).decode() for a in args]}).encode()
    req = urllib.request.Request(cfg['FLOW_REST'].rstrip('/') + '/v1/scripts?block_height=sealed', data=body, method='POST', headers={'Content-Type': 'application/json'})
    with urllib.request.urlopen(req, timeout=30) as r:
        return json.loads(__import__('base64').b64decode(r.read().decode().strip().strip('"')))


def receivers_and_balance(cfg, addresses):
    code = RECEIVERS.format(ft=cfg['FT_ADDRESS'], dust=cfg['DUST_ADDRESS'])
    res = script(cfg, code, [{'type': 'Array', 'value': [{'type': 'Address', 'value': a} for a in addresses]}, {'type': 'Address', 'value': cfg['REWARDS_ADDRESS']}])
    vals = res['value']
    ok = [v['value'] is not None for v in vals[:-1]]
    bal = vals[-1]['value']
    return ok, (D(bal['value']) if bal is not None else None)


def send_batch(cfg, items, max_total):
    code = CADENCE.format(ft=cfg['FT_ADDRESS'], dust=cfg['DUST_ADDRESS'])
    args = [
        {'type': 'Array', 'value': [{'type': 'Address', 'value': it['address']} for it in items]},
        {'type': 'Array', 'value': [{'type': 'UFix64', 'value': ufix(it['amount'])} for it in items]},
        {'type': 'Array', 'value': [{'type': 'UInt64', 'value': str(int(it['claim_id']))} for it in items]},
        {'type': 'UFix64', 'value': ufix(max_total)},
    ]
    with tempfile.TemporaryDirectory() as d:
        cdc = os.path.join(d, 'payout.cdc')
        with open(cdc, 'w') as f:
            f.write(code)
        p = subprocess.run(['flow', 'transactions', 'send', cdc, '--args-json', json.dumps(args), '--signer', cfg['FLOW_SIGNER'],
                            '--network', cfg['FLOW_NETWORK'], '-f', os.path.expanduser(cfg['FLOW_JSON']), '--compute-limit', '9999'],
                           capture_output=True, text=True, timeout=240, cwd=d)
    out = p.stdout + '\n' + p.stderr
    m = re.search(r'\bID\s+([0-9a-f]{64})\b', out)
    sealed = bool(re.search(r'Status\s+.*SEALED', out))
    err = None
    em = re.search(r'(?:❌|error|Error)[^\n]{0,300}', out)
    if em and not sealed:
        err = em.group(0)
    return (m.group(1) if m else None), sealed, err, p.returncode


# ------------------------------------------------------------------ main loop

def report(cfg, j, batch_id, tx_id=None, not_sent=None, reason=''):
    code, r = call(cfg, '/quests/payout/report', {'batch_id': batch_id, 'tx_id': tx_id, 'not_sent': not_sent or [], 'reason': reason})
    log('report %s tx=%s -> %s %s' % (batch_id, tx_id, code, r))
    b = j['batches'].get(batch_id)
    if code == 200 and r.get('status') == 'paid':
        if b:
            b['status'] = 'reported'
            b['reported_at'] = int(time.time())
        return 'paid'
    if code == 200 and r.get('status') == 'failed':
        # the tx failed on chain: nothing moved -> undo our ledger, the server released the claims
        if b and b.get('status') != 'failed':
            ledger_add(j, b['items'], -1)
            b['status'] = 'failed'
        problem('payout tx %s failed on chain (batch %s); claims released for retry' % (tx_id, batch_id))
        return 'failed'
    if code == 200 and r.get('status') in ('pending',):
        return 'pending'
    if code == 200 and r.get('status') in ('released', 'reserved'):
        return r.get('status')
    problem('report for batch %s (tx %s) not accepted: HTTP %s %s' % (batch_id, tx_id, code, r.get('error', r)))
    return 'error'


def run(cfg):
    j = load_journal(cfg)
    # 1) re-report batches sent earlier but not confirmed yet
    for bid, b in list(j['batches'].items()):
        if b.get('status') == 'sent' and b.get('tx_id'):
            report(cfg, j, bid, b['tx_id'])
            save_journal(cfg, j)
        elif b.get('status') == 'unknown':
            problem('batch %s ended without a tx id at %s: check the rewards wallet on Flowscan, then run --report %s <tx> or --release %s "<reason>"' % (bid, b.get('sent_at'), bid, bid))
    if any(b.get('status') == 'unknown' for b in j['batches'].values()):
        return
    # 2) get the open batch or a new one
    code, r = call(cfg, '/quests/payout/reserve', {'max': int(cfg.get('MAX_BATCH', 20))})
    if code != 200:
        problem('reserve failed: HTTP %s %s' % (code, r.get('error', r)))
        return
    bid = r.get('batch_id')
    if not bid:
        log('nothing to pay %s' % r)
        return
    items = r.get('items') or []
    known = j['batches'].get(bid)
    if known and known.get('tx_id'):
        report(cfg, j, bid, known['tx_id'])  # already sent: never send twice
        save_journal(cfg, j)
        return
    if known and known.get('status') in ('sending', 'unknown'):
        known['status'] = 'unknown'
        save_journal(cfg, j)
        problem('batch %s was being sent when the worker stopped: manual check needed' % bid)
        return
    if r.get('rewards_address') and r['rewards_address'].lower() != cfg['REWARDS_ADDRESS'].lower():
        problem('server expects rewards wallet %s, worker has %s: not paying' % (r['rewards_address'], cfg['REWARDS_ADDRESS']))
        return
    # 3) our own limits (the server enforced them too)
    max_user, daily, season = D(cfg.get('MAX_PER_USER', '1000')), D(cfg.get('DAILY_CAP', '25000')), D(cfg.get('SEASON_BUDGET', '1000000'))
    max_claim, max_batch = D(cfg.get('MAX_CLAIM', '1000')), int(cfg.get('MAX_BATCH', 20))
    day = datetime.now(timezone.utc).strftime('%Y-%m-%d')
    day_total, season_total = D(j['per_day'].get(day, '0')), D(j.get('season_total', '0'))
    per_addr = {}
    ok_items, not_sent, reasons = [], [], []
    for it in items:
        cid, addr, amt = int(it['claim_id']), str(it['address']).lower(), D(it['amount'])
        why = None
        if not re.fullmatch(r'0x[0-9a-f]{16}', addr):
            why = 'bad address'
        elif str(cid) in j['claims']:
            why = 'claim already paid by tx %s' % j['claims'][str(cid)]
        elif amt <= 0 or amt > max_claim:
            why = 'amount out of range'
        elif len(ok_items) >= max_batch:
            why = 'batch size'
        else:
            used = D(j['per_address'].get(addr, '0')) + per_addr.get(addr, D(0))
            if used + amt > max_user:
                why = 'per-account cap (worker)'
            elif day_total + amt > daily:
                why = 'daily cap (worker)'
            elif season_total + amt > season:
                why = 'season budget (worker)'
        if why:
            not_sent.append(cid)
            reasons.append('%s:%s' % (cid, why))
            continue
        per_addr[addr] = per_addr.get(addr, D(0)) + amt
        day_total += amt
        season_total += amt
        ok_items.append({'claim_id': cid, 'address': addr, 'amount': ufix(amt)})
    # 4) recipients need a ĐUST receiver; the wallet needs the balance
    if ok_items:
        try:
            has, balance = receivers_and_balance(cfg, [it['address'] for it in ok_items])
        except Exception as e:
            problem('chain read failed: %s' % e)
            return
        keep = []
        for it, h in zip(ok_items, has):
            if h:
                keep.append(it)
            else:
                not_sent.append(it['claim_id'])
                reasons.append('%s:no ĐUST receiver yet' % it['claim_id'])
        ok_items = keep
        total = sum((D(it['amount']) for it in ok_items), D(0))
        if balance is None or balance < total:
            problem('rewards wallet balance %s < batch total %s: top it up (batch %s left open)' % (balance, total, bid))
            return
        low = D(cfg.get('LOW_BALANCE_WARN', '0'))
        if low and balance - total < low:
            problem('rewards wallet low after this batch: %s ĐUST left (warn below %s)' % (balance - total, low))
    if not ok_items:
        report(cfg, j, bid, None, [int(i['claim_id']) for i in items], '; '.join(reasons)[:480])
        if reasons and any('cap' in x or 'budget' in x or 'paid' in x or 'range' in x for x in reasons):
            problem('batch %s not paid: %s' % (bid, '; '.join(reasons)[:300]))
        return
    if not_sent:
        report(cfg, j, bid, None, not_sent, '; '.join(reasons)[:480])
        if any('cap' in x or 'budget' in x or 'paid' in x or 'range' in x for x in reasons):
            problem('skipped claims in batch %s: %s' % (bid, '; '.join(reasons)[:300]))
    total = sum((D(it['amount']) for it in ok_items), D(0))
    # 5) journal first, then send
    j['batches'][bid] = {'status': 'sending', 'items': ok_items, 'total': ufix(total), 'sent_at': int(time.time())}
    ledger_add(j, ok_items, +1)
    save_journal(cfg, j)
    tx_id, sealed, err, rc = send_batch(cfg, ok_items, total)
    b = j['batches'][bid]
    if not tx_id:
        b['status'] = 'unknown'
        b['error'] = (err or 'flow CLI exit %s' % rc)[:300]
        save_journal(cfg, j)
        problem('sending batch %s gave no tx id (%s): manual check needed' % (bid, b['error']))
        return
    b['tx_id'] = tx_id
    b['status'] = 'sent'
    for it in b['items']:
        it['tx_id'] = tx_id
        j['claims'][str(it['claim_id'])] = tx_id
    save_journal(cfg, j)
    log('sent batch %s: %d claims, %s ĐUST, tx %s (sealed=%s)' % (bid, len(ok_items), ufix(total), tx_id, sealed))
    report(cfg, j, bid, tx_id)
    save_journal(cfg, j)


def main():
    cfg = load_env()
    lock_path = journal_path(cfg) + '.lock'
    with open(lock_path, 'a') as lf:
        try:
            fcntl.flock(lf, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            log('another run is active')
            return
        j = load_journal(cfg)
        if len(sys.argv) >= 4 and sys.argv[1] == '--report':
            bid, tx = sys.argv[2], sys.argv[3].lower()
            b = j['batches'].setdefault(bid, {'items': [], 'status': 'unknown'})
            b['tx_id'], b['status'] = tx, 'sent'
            for it in b.get('items', []):
                j['claims'][str(it['claim_id'])] = tx
            save_journal(cfg, j)
            print(report(cfg, j, bid, tx))
            save_journal(cfg, j)
            return
        if len(sys.argv) >= 4 and sys.argv[1] == '--release':
            bid, reason = sys.argv[2], sys.argv[3]
            b = j['batches'].get(bid)
            if b and b.get('tx_id'):
                raise SystemExit('batch %s has tx %s: report it instead of releasing' % (bid, b['tx_id']))
            if b and b.get('status') in ('sending', 'unknown'):
                ledger_add(j, b.get('items', []), -1)
                b['status'] = 'released'
            save_journal(cfg, j)
            code, r = call(cfg, '/quests/payout/report', {'batch_id': bid, 'tx_id': None, 'not_sent': [], 'reason': 'released by operator: ' + reason})
            print(code, r)
            return
        if len(sys.argv) >= 2 and sys.argv[1] == '--status':
            code, r = call(cfg, '/quests/payout/status', {})
            print(json.dumps({'server': r, 'journal': {'season_total': j.get('season_total'), 'per_day': j.get('per_day'),
                                                        'open': {k: v.get('status') for k, v in j['batches'].items() if v.get('status') not in ('reported', 'failed', 'released')}}}, indent=1))
            return
        run(cfg)


if __name__ == '__main__':
    try:
        main()
    except SystemExit:
        raise
    except Exception as e:  # never stay silent on a crash
        problem('worker crashed: %s: %s' % (type(e).__name__, str(e)[:300]))
        sys.exit(1)
