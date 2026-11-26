import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

// Asteroid Islands redeem flow: open an unopened TerraKit pack (land deed + FUB voucher + Flobot kit). Same call as
// pack-open.tx.js (FlovatarPack.Collection.openPack), but first makes sure the FlovatarComponent collection the pack
// deposits into exists and is published (openPack panics otherwise). Port of cadence/islands open_terrakit_pack.cdc.
export async function islandOpenPackTx(packId) {
    return await fcl
        .mutate({
            cadence: `
import FlovatarComponent, FlovatarPack from 0xFlovatar

transaction(packId: UInt64) {
    let packs: auth(FlovatarPack.WithdrawEnt) &FlovatarPack.Collection

    prepare(signer: auth(Storage, Capabilities) &Account) {
        if signer.storage.type(at: FlovatarComponent.CollectionStoragePath) == nil {
            signer.storage.save(<-FlovatarComponent.createEmptyCollection(nftType: Type<@FlovatarComponent.NFT>()), to: FlovatarComponent.CollectionStoragePath)
        }
        if !signer.capabilities.get<&{FlovatarComponent.CollectionPublic}>(FlovatarComponent.CollectionPublicPath).check() {
            signer.capabilities.unpublish(FlovatarComponent.CollectionPublicPath)
            signer.capabilities.publish(signer.capabilities.storage.issue<&FlovatarComponent.Collection>(FlovatarComponent.CollectionStoragePath), at: FlovatarComponent.CollectionPublicPath)
        }
        self.packs = signer.storage.borrow<auth(FlovatarPack.WithdrawEnt) &FlovatarPack.Collection>(from: FlovatarPack.CollectionStoragePath)
            ?? panic("No pack collection")
    }

    execute {
        self.packs.openPack(id: packId)
    }
}
`,
            args: (arg, t) => [
                arg('' + packId, t.UInt64)
            ],
            limit: 9999
        });
}
