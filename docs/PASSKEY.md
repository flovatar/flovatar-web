# Passkey sign-in (email + passkey, no wallet) — developer notes

Status: testnet only, off by default. Enabled when `PASSKEY_LOGIN_ENABLED=true`, `PASSKEY_POC_ENABLED=true` and
`FLOW_NETWORK=testnet` (`App\Support\PasskeyLogin::enabled()`). With the flag off the HTML is byte-identical and every
transaction goes straight to `fcl.mutate`.

## How it fits together
- **Sign in**: "Sign in" opens a choice (`resources/views/partials/passkey-auth.blade.php`, `resources/js/passkey.js`):
  *Email + passkey* (WebAuthn, FLIP-264 account key, a sponsor creates the Flow account and pays fees) or *Wallet*
  (unchanged FCL discovery: Flow Wallet, Dapper). A passkey sign-in is also a normal Laravel login (`users.email` = address,
  `users.auth_type = 'passkey'`); `/login/nonce` returns `auth_type`.
- **One signing path**: every `resources/js/flow/*.tx.js` imports `./fcl-tx`, whose `mutate()` is `fcl.mutate()` for wallet
  users and `window.passkeyMutate()` for passkey users. The browser sends `{template: sha256(cadence), args}` to
  `POST /passkey/tx/template`; the server builds the transaction from its own copy of the Cadence, the passkey signs the
  payload (proposer + authorizer), the sponsor signs the envelope (payer), and the tx id goes back to the caller
  (`fcl.tx(id)` keeps working).
- **Allowlist**: `config/passkey-templates.php` (template id → pinned SHA-256 + label). Cadence source:
  `resources/passkey/templates.json`, generated from the tx files. Dapper-only templates are not on the list.

## Changing a transaction file
1. Edit `resources/js/flow/<name>.tx.js`.
2. `node bin/passkey-templates.js` (rewrites the manifest).
3. Update the `sha256` of `<name>` in `config/passkey-templates.php` (review the Cadence change: the sponsor pays for it).
4. `php artisan test --filter=PasskeyTemplatesTest` (checks manifest freshness, pins, argument parsing, alias resolution).

New transaction files must be added to the allowlist explicitly to be available to passkey accounts.

## Server pieces
`app/Services/Passkey/*` (`TemplateRegistry`, `PasskeyPoc`, `FlowTransaction`, `PasskeyCrypto`, `SponsorSigner`,
`FlowGateway`), `app/Http/Controllers/PasskeyPocController.php` (`/passkey/*`), `app/Support/PasskeyLogin.php`,
`php artisan passkey:sponsor-keys N` (adds sponsor proposal keys; set `PASSKEY_SPONSOR_PROPOSAL_KEYS`).
Limits: `PASSKEY_ACCOUNTS_PER_IP_PER_DAY`, `PASSKEY_ACCOUNTS_PER_DAY`, `PASSKEY_TXS_PER_ACCOUNT_PER_HOUR`.
Headless Worker: `/passkey*` is proxied to the origin (session). WebAuthn `PASSKEY_RP_ID` must be the page's host or a
registrable suffix of it.
