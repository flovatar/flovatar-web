import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"


transaction(templateId: UInt64, quantity: UInt64) {

    let flovatarComponentCollection: &FlovatarComponent.Collection
    let flovatarAdmin: &Flovatar.Admin

    prepare(account: auth(Storage) &Account) {
        self.flovatarComponentCollection = account.storage.borrow<&FlovatarComponent.Collection>(from: FlovatarComponent.CollectionStoragePath)!

        self.flovatarAdmin = account.storage.borrow<&Flovatar.Admin>(from: Flovatar.AdminStoragePath)!
    }

    execute {
        let collection <- self.flovatarAdmin.batchCreateComponents(templateId: templateId, quantity: quantity) as! @FlovatarComponent.Collection

        for id in collection.getIDs() {
            self.flovatarComponentCollection.deposit(token: <- collection.withdraw(withdrawID: id))
        }

        destroy collection

    }
}
