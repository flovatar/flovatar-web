import * as fcl from "@onflow/fcl"

/**
 * Chain-first owner lookup for item pages: returns the first candidate address whose collection holds
 * the item (or null). One script for all candidates; cheap (getIDs only, no SVG).
 * kind: flovatar | flobot | collectible | component
 */
export async function findItemOwnerScript(kind, itemId, candidates) {
    if (!candidates || !candidates.length) return null

    return await fcl
        .query({
            cadence: `
import Flovatar, Flobot, FlovatarComponent, FlovatarDustCollectible from 0xFlovatar

access(all) fun main(kind: String, itemId: UInt64, candidates: [Address]): Address? {
    for a in candidates {
        let acct = getAccount(a)
        var ids: [UInt64] = []
        if kind == "flovatar" {
            if let c = acct.capabilities.borrow<&{Flovatar.CollectionPublic}>(Flovatar.CollectionPublicPath) { ids = c.getIDs() }
        } else if kind == "flobot" {
            if let c = acct.capabilities.borrow<&{Flobot.CollectionPublic}>(Flobot.CollectionPublicPath) { ids = c.getIDs() }
        } else if kind == "collectible" {
            if let c = acct.capabilities.borrow<&{FlovatarDustCollectible.CollectionPublic}>(FlovatarDustCollectible.CollectionPublicPath) { ids = c.getIDs() }
        } else if kind == "component" {
            if let c = acct.capabilities.borrow<&{FlovatarComponent.CollectionPublic}>(FlovatarComponent.CollectionPublicPath) { ids = c.getIDs() }
        }
        if ids.contains(itemId) {
            return a
        }
    }
    return nil
}
`,
            args: (arg, t) => [
                arg(kind, t.String),
                arg('' + itemId, t.UInt64),
                arg(candidates, t.Array(t.Address))
            ],
        });
}
