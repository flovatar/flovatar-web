import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

// Asteroid Islands: place NFTs the signer holds on island slots (references only, the NFTs stay in the wallet).
// Premium slots (Flovatar family) are drawn in the scene, standard slots show in the carousel.
export async function islandSetResidentsTx(islandId, slots, nftTypes, nftIds) {
    return await fcl
        .mutate({
            cadence: `
import FlovatarAsteroidIsland from 0xIslands

transaction(islandId: UInt64, slots: [String], nftTypes: [String], nftIds: [UInt64]) {
    let island: auth(FlovatarAsteroidIsland.Edit) &FlovatarAsteroidIsland.NFT
    prepare(signer: auth(BorrowValue) &Account) {
        assert(slots.length == nftTypes.length && slots.length == nftIds.length, message: "length mismatch")
        let col = signer.storage.borrow<auth(FlovatarAsteroidIsland.Edit) &FlovatarAsteroidIsland.Collection>(from: FlovatarAsteroidIsland.CollectionStoragePath)
            ?? panic("No island collection")
        self.island = col.borrowIslandForEdit(id: islandId) ?? panic("Island not found")
    }
    execute {
        var i = 0
        while i < slots.length {
            let t = CompositeType(nftTypes[i]) ?? panic("Unknown NFT type")
            self.island.setResident(slot: slots[i], nftType: t, nftId: nftIds[i], collectionPath: nil, metadata: {})
            i = i + 1
        }
    }
}
`,
            args: (arg, t) => [
                arg('' + islandId, t.UInt64),
                arg((Array.isArray(slots) ? slots : []).map((x) => '' + x), t.Array(t.String)),
                arg((Array.isArray(nftTypes) ? nftTypes : []).map((x) => '' + x), t.Array(t.String)),
                arg((Array.isArray(nftIds) ? nftIds : []).map((x) => '' + x), t.Array(t.UInt64)),
            ],
            limit: 9999
        });
}
