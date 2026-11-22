# Pack signing

Pack sales stay **disabled** (`FLOVATAR_PACK_SALES_ENABLED=false`). This page describes the signing code that replaces
the old Go tool, and the v2 authorisations that pack sales will use when they reopen.

## What changed

The old back-end shelled out to `go run ./user_signature/main.go -arg=<secret>` in `go/examples` (a fork of
flow-go-sdk that was never in this repository). That tool signed the pack's random string as a Flow *user message*:

```
signature = ECDSA_P256( SHA3_256( "FLOW-V0.0-user" right-padded with zero bytes to 32 bytes || message ) )
output    = ["<hex r||s, 64 bytes>"]
```

The key it used was key 0 of the contract account, because FlovatarPack v1 checks the signature against
**account key 0 of the pack owner account**.

The Go tool is gone. `App\Services\Flow\UserMessageSigner` does the same signing in PHP (ext-openssl + gmp):

- the same pre-image and SHA3-256 digest, and the same 64-byte `r||s` hex encoding;
- `s` is normalised to low-S (the Go SDK doesn't normalise, and Flow accepts both forms).

ECDSA uses a random nonce in both implementations, so two signatures of the same message are never byte-identical.
The tests therefore compare everything else:

- `tests/Unit/UserMessageSignerTest.php`, with vectors from the Go tool built locally with a throwaway key
  (`tests/Fixtures/flow-user-signature-go-vectors.json`; only the public key is stored). They check that the
  pre-image and digest are identical, that Go signatures verify in PHP, and that PHP signatures verified with the
  Go SDK.
- `tests/Unit/UserMessageSignerTestnetTest.php` (`php artisan test --group=testnet`). It runs Cadence
  `Crypto.KeyList.verify(..., domainSeparationTag: "FLOW-V0.0-user")` against a testnet account key.

## Callers

| Caller | Status |
|---|---|
| `App\Jobs\SignPack`, `flovatar:sign-packs` (v1) | Use `UserMessageSigner`. They run only when **both** `FLOVATAR_PACK_SALES_ENABLED` and `FLOVATAR_PACK_V1_SIGNING` are true. v1 needs the contract account's own key on the server, so it is for **testnet only**. The secret is no longer logged. |
| `PackController::reserve` | v1: returns the stored signature. With `FLOVATAR_PACKS_V2=true` it signs a fresh v2 authorisation for the logged-in wallet instead (`FlovatarPackAuthorizer`). |
| `/sportpacks` | Only reads Sportvatar v1 signatures from the database. There is no signing here. Sportvatar v2 is handled in the sportvatar back-end. |

## Key handling

- `PACK_SIGNER_KEY_FILE` holds 64 hex characters (the `flow keys generate` format).
  - The file must be **outside the site directory** and have **mode 600**. The signer refuses anything else.
  - The key is read on each call. It never appears in logs or exception messages.
- **Never put a key of the mainnet contract account on the server.** Mainnet uses packs v2 with a dedicated signer
  key whose public key is registered in the pack verifier inside `FlovatarPack` (no separate contract: Flow mainnet
  does not accept new contract deployments, so the verifier was added to FlovatarPack in its in-place update). That key is not an account key: it
  can't sign transactions or move funds.
- Suggested location: `/home/forge/secure/flovatar-pack-signer.pkey` (directory mode 700, owner `forge`).
- `php artisan flovatar:pack-signer-check` compares the local key and `FLOVATAR_PACK_EPOCH` with the on-chain
  verifier. It fails if the key is a key of the contract account.

## Packs v2

The message is signed with **no domain tag**, and must match `FlovatarPack.buildVerifierMessage` exactly:

```
FLOVATAR-PACK-V2|<kind>|<packId>|<recipient 0x + 16 hex>|<expiry unix s>|<epoch>
kind = flow | dust | dapper   (how the buyer pays; the contract derives it from the vault type)
```

The contract checks the following:

- the pack goes only to `recipient`;
- the payment kind matches;
- the price is exact;
- the authorisation has not expired (default TTL 15 min, `FLOVATAR_PACK_AUTH_TTL`);
- the epoch is current (bumping it on-chain invalidates every outstanding authorisation; then raise `FLOVATAR_PACK_EPOCH`);
- the pack is not revoked, and purchases are switched on.

Website transactions: `resources/js/flow/pack-buy-v2.tx.js` (DUST) and `pack-buy-dapper-v2.tx.js` (Dapper) call
`FlovatarPack.purchasePack`. `window.buyPack` uses them when `/pack/reserve` returns `version: 2`.

Rollout order:

1. On-chain update (owner-run).
2. Signer key on the server.
3. `flovatar:pack-signer-check` prints OK.
4. `FLOVATAR_PACKS_V2=true`, then rebuild the assets.
5. Owner switches purchases on.
6. `FLOVATAR_PACK_SALES_ENABLED=true`.

The old v1 website transactions stop working once the contract update is live.
