/**
 * FCL for the transaction files (resources/js/flow/*.tx.js): everything is @onflow/fcl, except mutate().
 *
 * mutate() = fcl.mutate() unchanged for wallet users (Flow Wallet, Dapper). When a passkey session is active
 * (testnet passkey sign-in, resources/js/passkey.js sets window.extSession) the same options go to
 * window.extMutate(): the server looks the Cadence up by its SHA-256 in an allowlist of these very files, builds the
 * transaction with the passkey account as proposer + authorizer and the sponsor as payer, the passkey signs (FLIP-264)
 * and the transaction id comes back — so callers keep using fcl.tx(txId) as before.
 * Without the passkey feature window.extSession is never set and this is exactly fcl.mutate.
 */
import * as fcl from "@onflow/fcl"

export * from "@onflow/fcl"

export async function mutate(opts) {
    if (window.extSession && window.extSession.active && typeof window.extMutate === 'function') {
        return window.extMutate(opts);
    }
    return fcl.mutate(opts);
}
