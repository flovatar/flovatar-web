import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

// Asteroid Islands: redeem (burn) 1 TerraKit land deed, or fuse 5 of the same rarity into the next tier, and mint the
// island with the chosen scene (layer -> template id) and optional tint. Free: no payment argument (no fees configured).
// Also sets up the island collection. Contract: FlovatarAsteroidIsland (0xIslands, set at runtime).
export async function islandTerraformTx(deedIds, layers, tint) {
    const entries = Object.entries(layers || {});
    return await fcl
        .mutate({
            cadence: `
import NonFungibleToken from 0xNonFungible
import FlovatarComponent from 0xFlovatar
import FlovatarAsteroidIsland from 0xIslands

transaction(deedIds: [UInt64], layers: {String: UInt64}, tint: String?) {
    let deeds: @[FlovatarComponent.NFT]
    let collection: &FlovatarAsteroidIsland.Collection

    prepare(signer: auth(Storage, Capabilities) &Account) {
        if signer.storage.type(at: FlovatarAsteroidIsland.CollectionStoragePath) == nil {
            signer.storage.save(<-FlovatarAsteroidIsland.createEmptyCollection(nftType: Type<@FlovatarAsteroidIsland.NFT>()), to: FlovatarAsteroidIsland.CollectionStoragePath)
        }
        if !signer.capabilities.get<&FlovatarAsteroidIsland.Collection>(FlovatarAsteroidIsland.CollectionPublicPath).check() {
            signer.capabilities.unpublish(FlovatarAsteroidIsland.CollectionPublicPath)
            signer.capabilities.publish(
                signer.capabilities.storage.issue<&FlovatarAsteroidIsland.Collection>(FlovatarAsteroidIsland.CollectionStoragePath),
                at: FlovatarAsteroidIsland.CollectionPublicPath)
        }
        self.collection = signer.storage.borrow<&FlovatarAsteroidIsland.Collection>(from: FlovatarAsteroidIsland.CollectionStoragePath)!

        let components = signer.storage.borrow<auth(NonFungibleToken.Withdraw) &FlovatarComponent.Collection>(from: FlovatarComponent.CollectionStoragePath)
            ?? panic("No FlovatarComponent collection")
        self.deeds <- []
        for id in deedIds {
            self.deeds.append(<-(components.withdraw(withdrawID: id) as! @FlovatarComponent.NFT))
        }
    }

    execute {
        FlovatarAsteroidIsland.terraform(deeds: <-self.deeds, layers: layers, tint: tint, collection: self.collection, payment: nil)
    }
}
`,
            args: (arg, t) => [
                arg((Array.isArray(deedIds) ? deedIds : [deedIds]).map((x) => '' + x), t.Array(t.UInt64)),
                arg(entries.map(([k, v]) => ({key: '' + k, value: '' + v})), t.Dictionary({key: t.String, value: t.UInt64})),
                arg(tint ? '' + tint : null, t.Optional(t.String)),
            ],
            limit: 9999
        });
}
