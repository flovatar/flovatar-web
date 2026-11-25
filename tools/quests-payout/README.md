# ĐUST Quests payout worker

Runs on the **payout box** (never on the web server). It holds the rewards-wallet key through a flow CLI config, pulls a
batch of verified quest claims from the web app (HMAC-signed API `/quests/payout/*`), re-checks its own limits, sends ONE
ĐUST transaction for N recipients and reports the tx id; the web app verifies that transaction on chain before marking
claims paid. Design + runbook: Flovatar KB `notes/dust-quests.md`.

Requirements: Python 3.9+ (stdlib only) and the flow CLI.

`worker.env` (KEY=VALUE, chmod 600, never commit):

```
QUESTS_API=https://testnet.flovatar.com
QUESTS_PAYOUT_SECRET=<64 hex, same value as DUST_QUESTS_PAYOUT_SECRET in the site .env>
REWARDS_ADDRESS=0x...
FLOW_JSON=/path/to/flow.json          # accounts.<FLOW_SIGNER> with the rewards key (file or KMS)
FLOW_SIGNER=rewards
FLOW_NETWORK=testnet                  # mainnet also needs ALLOW_MAINNET=yes
FLOW_REST=https://rest-testnet.onflow.org
FT_ADDRESS=0x9a0766d93b6608b7
DUST_ADDRESS=0x2c6a4c4342aeca55
MAX_PER_USER=1000
MAX_CLAIM=1000
DAILY_CAP=25000
SEASON_BUDGET=1000000
MAX_BATCH=20
LOW_BALANCE_WARN=2000
JOURNAL=/path/to/journal.json
```

Run every 5 minutes (`DUST_QUESTS_WORKER_ENV=/path/to/worker.env python3 worker.py`). It prints nothing when all is well
and one line per problem otherwise. Manual: `-v` (verbose), `--status`, `--report BATCH TX`, `--release BATCH "reason"`.
