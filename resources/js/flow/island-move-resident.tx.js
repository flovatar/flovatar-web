import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

// Asteroid Islands: move a resident to another slot (swaps when the target slot is taken).
export async function islandMoveResidentTx(islandId, fromSlot, toSlot) {
    return await fcl
        .mutate({
            cadence: `
import FlovatarAsteroidIsland from 0xIslands

transaction(islandId: UInt64, fromSlot: String, toSlot: String) {
    let island: auth(FlovatarAsteroidIsland.Edit) &FlovatarAsteroidIsland.NFT
    prepare(signer: auth(BorrowValue) &Account) {
        let col = signer.storage.borrow<auth(FlovatarAsteroidIsland.Edit) &FlovatarAsteroidIsland.Collection>(from: FlovatarAsteroidIsland.CollectionStoragePath)
            ?? panic("No island collection")
        self.island = col.borrowIslandForEdit(id: islandId) ?? panic("Island not found")
    }
    execute { self.island.moveResident(fromSlot: fromSlot, toSlot: toSlot) }
}
`,
            args: (arg, t) => [
                arg('' + islandId, t.UInt64),
                arg('' + fromSlot, t.String),
                arg('' + toSlot, t.String),
            ],
            limit: 9999
        });
}
