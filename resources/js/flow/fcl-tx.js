/**
 * FCL for the transaction files (resources/js/flow/*.tx.js): everything is @onflow/fcl, except mutate().
 *
 * mutate() = fcl.mutate() unchanged for wallet users (Flow Wallet, Dapper). When a passkey session is active
 * (testnet passkey sign-in, resources/js/passkey.js sets window.passkeySession) the same options go to
 * window.passkeyMutate(): the server looks the Cadence up by its SHA-256 in an allowlist of these very files, builds the
 * transaction with the passkey account as proposer + authorizer and the sponsor as payer, the passkey signs (FLIP-264)
 * and the transaction id comes back — so callers keep using fcl.tx(txId) as before.
 * Without the passkey feature window.passkeySession is never set and this is exactly fcl.mutate.
 */
import * as fcl from "@onflow/fcl"

export * from "@onflow/fcl"

export async function mutate(opts) {
    if (window.passkeySession && window.passkeySession.active && typeof window.passkeyMutate === 'function') {
        return window.passkeyMutate(opts);
    }
    return fcl.mutate(opts);
}
