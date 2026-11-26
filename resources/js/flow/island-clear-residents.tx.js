import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

// Asteroid Islands: clear the given slots (an empty list clears every resident, e.g. after buying an island).
export async function islandClearResidentsTx(islandId, slots) {
    return await fcl
        .mutate({
            cadence: `
import FlovatarAsteroidIsland from 0xIslands

transaction(islandId: UInt64, slots: [String]) {
    let island: auth(FlovatarAsteroidIsland.Edit) &FlovatarAsteroidIsland.NFT
    prepare(signer: auth(BorrowValue) &Account) {
        let col = signer.storage.borrow<auth(FlovatarAsteroidIsland.Edit) &FlovatarAsteroidIsland.Collection>(from: FlovatarAsteroidIsland.CollectionStoragePath)
            ?? panic("No island collection")
        self.island = col.borrowIslandForEdit(id: islandId) ?? panic("Island not found")
    }
    execute {
        if slots.length == 0 {
            self.island.clearAllResidents()
        } else {
            for s in slots { self.island.clearResident(slot: s) }
        }
    }
}
`,
            args: (arg, t) => [
                arg('' + islandId, t.UInt64),
                arg((Array.isArray(slots) ? slots : []).map((x) => '' + x), t.Array(t.String)),
            ],
            limit: 9999
        });
}
