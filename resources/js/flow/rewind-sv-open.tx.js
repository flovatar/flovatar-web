import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

// Pack Rewind (testnet), 2023 chapter: open a Sportvatar pack (SportvatarPack.Collection.openPack deposits the Sport
// Flames and Sportbits into the opener's Sportbit collection, created here when missing).
export async function rewindSvOpenTx(packId) {
    return await fcl
        .mutate({
            cadence: `
import Sportbit, SportvatarPack from 0xSportvatar
import NonFungibleToken from 0xNonFungible

transaction(packId: UInt64) {
    let packs: auth(SportvatarPack.WithdrawEnt) &SportvatarPack.Collection

    prepare(account: auth(Storage, Capabilities) &Account) {
        if account.storage.borrow<&Sportbit.Collection>(from: Sportbit.CollectionStoragePath) == nil {
            account.storage.save<@{NonFungibleToken.Collection}>(<- Sportbit.createEmptyCollection(nftType: Type<@Sportbit.Collection>()), to: Sportbit.CollectionStoragePath)
        }
        if !account.capabilities.get<&Sportbit.Collection>(Sportbit.CollectionPublicPath).check() {
            account.capabilities.unpublish(Sportbit.CollectionPublicPath)
            account.capabilities.publish(account.capabilities.storage.issue<&Sportbit.Collection>(Sportbit.CollectionStoragePath), at: Sportbit.CollectionPublicPath)
        }
        self.packs = account.storage.borrow<auth(SportvatarPack.WithdrawEnt) &SportvatarPack.Collection>(from: SportvatarPack.CollectionStoragePath)
            ?? panic("No Sportvatar pack collection")
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
