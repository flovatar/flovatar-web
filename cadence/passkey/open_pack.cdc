// Open a Flovatar pack (same as resources/js/flow/pack-open.tx.js). Used to seed the sponsor's starter stock.
import Flovatar, FlovatarComponent, FlovatarComponentTemplate, FlovatarPack, FlovatarMarketplace from 0xFlovatar
import FungibleToken from 0xFungibleToken
import NonFungibleToken from 0xNonFungibleToken
import FlowToken from 0xFlowToken

transaction(packId: UInt64) {

    let flovatarPackCollection: auth(FlovatarPack.WithdrawEnt) &FlovatarPack.Collection

    prepare(account: auth(Storage) &Account) {
        self.flovatarPackCollection = account.storage.borrow<auth(FlovatarPack.WithdrawEnt) &FlovatarPack.Collection>(from: FlovatarPack.CollectionStoragePath)!
    }

    execute {
        self.flovatarPackCollection.openPack(id: packId)
    }

}
