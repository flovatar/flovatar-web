import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

// Asteroid Islands: change one scene layer of an island the signer owns (contract set_layer). Allowed for the
// contract's `mutableLayers` (the sky: any time, free) and once for the display of an island minted before displays
// existed. templateId null removes an optional mutable layer (no sky). Same Cadence as cadence/islands/transactions/set_layer.cdc.
export async function islandSetLayerTx(islandId, layer, templateId) {
    return await fcl
        .mutate({
            cadence: `
import FlovatarAsteroidIsland from 0xIslands

transaction(islandId: UInt64, layer: String, templateId: UInt64?) {
    let island: auth(FlovatarAsteroidIsland.Edit) &FlovatarAsteroidIsland.NFT
    prepare(signer: auth(BorrowValue) &Account) {
        let col = signer.storage.borrow<auth(FlovatarAsteroidIsland.Edit) &FlovatarAsteroidIsland.Collection>(from: FlovatarAsteroidIsland.CollectionStoragePath)
            ?? panic("No island collection")
        self.island = col.borrowIslandForEdit(id: islandId) ?? panic("Island not found")
    }
    execute {
        self.island.setLayer(layer: layer, templateId: templateId)
    }
}
`,
            args: (arg, t) => [
                arg('' + islandId, t.UInt64),
                arg('' + layer, t.String),
                arg(templateId === null || templateId === undefined ? null : '' + templateId, t.Optional(t.UInt64)),
            ],
            limit: 9999
        });
}
