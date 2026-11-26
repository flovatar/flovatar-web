import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

// Asteroid Islands: set the island name and/or description (null = unchanged). Free (no fee configured).
export async function islandSetNameDescriptionTx(islandId, name, description) {
    return await fcl
        .mutate({
            cadence: `
import FlovatarAsteroidIsland from 0xIslands

transaction(islandId: UInt64, name: String?, description: String?) {
    let island: auth(FlovatarAsteroidIsland.Edit) &FlovatarAsteroidIsland.NFT
    prepare(signer: auth(BorrowValue) &Account) {
        let col = signer.storage.borrow<auth(FlovatarAsteroidIsland.Edit) &FlovatarAsteroidIsland.Collection>(from: FlovatarAsteroidIsland.CollectionStoragePath)
            ?? panic("No island collection")
        self.island = col.borrowIslandForEdit(id: islandId) ?? panic("Island not found")
    }
    execute {
        if name != nil { self.island.setName(name!, payment: nil) }
        if description != nil { self.island.setDescription(description!, payment: nil) }
    }
}
`,
            args: (arg, t) => [
                arg('' + islandId, t.UInt64),
                arg(name === null || name === undefined ? null : '' + name, t.Optional(t.String)),
                arg(description === null || description === undefined ? null : '' + description, t.Optional(t.String)),
            ],
            limit: 9999
        });
}
