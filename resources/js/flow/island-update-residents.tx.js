import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

// Asteroid Islands: apply a batch of resident changes in ONE transaction (one wallet approval) — the island page's
// pick-and-place "Save". Clears run first (frees capacity and the "this NFT already lives here" index), then every
// set (premium slots = Flovatar family, drawn in the scene; standard slots = any NFT, shown on the display).
// References only: the NFTs stay in the wallet. Uses the contract's clearResident / setResident (same checks as
// set_residents.cdc / clear_residents.cdc). Keep sets <= 25 per transaction (computation).
export async function islandUpdateResidentsTx(islandId, clearSlots, slots, nftTypes, nftIds) {
    return await fcl
        .mutate({
            cadence: `
import FlovatarAsteroidIsland from 0xIslands

transaction(islandId: UInt64, clearSlots: [String], slots: [String], nftTypes: [String], nftIds: [UInt64]) {
    let island: auth(FlovatarAsteroidIsland.Edit) &FlovatarAsteroidIsland.NFT
    prepare(signer: auth(BorrowValue) &Account) {
        assert(slots.length == nftTypes.length && slots.length == nftIds.length, message: "length mismatch")
        let col = signer.storage.borrow<auth(FlovatarAsteroidIsland.Edit) &FlovatarAsteroidIsland.Collection>(from: FlovatarAsteroidIsland.CollectionStoragePath)
            ?? panic("No island collection")
        self.island = col.borrowIslandForEdit(id: islandId) ?? panic("Island not found")
    }
    execute {
        let residents = self.island.getResidents()
        for slot in clearSlots {
            if residents[slot] != nil { self.island.clearResident(slot: slot) }
        }
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
                arg((Array.isArray(clearSlots) ? clearSlots : []).map((x) => '' + x), t.Array(t.String)),
                arg((Array.isArray(slots) ? slots : []).map((x) => '' + x), t.Array(t.String)),
                arg((Array.isArray(nftTypes) ? nftTypes : []).map((x) => '' + x), t.Array(t.String)),
                arg((Array.isArray(nftIds) ? nftIds : []).map((x) => '' + x), t.Array(t.UInt64)),
            ],
            limit: 9999
        });
}
