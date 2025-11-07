import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"
import FlovatarDustCollectible from "../../contracts/FlovatarDustCollectible.cdc"
import FlovatarDustCollectibleTemplate from "../../contracts/FlovatarDustCollectibleTemplate.cdc"
import FlovatarDustCollectibleAccessory from "../../contracts/FlovatarDustCollectibleAccessory.cdc"


transaction(templateId: UInt64, quantity: UInt64) {


    let flovatarDustAccessoryCollection: &FlovatarDustCollectibleAccessory.Collection
    let flovatarAdmin: &FlovatarDustCollectible.Admin

    prepare(account: AuthAccount) {
        self.flovatarDustAccessoryCollection = account.borrow<&FlovatarDustCollectibleAccessory.Collection>(from: FlovatarDustCollectibleAccessory.CollectionStoragePath)!

        self.flovatarAdmin = account.borrow<&FlovatarDustCollectible.Admin>(from: FlovatarDustCollectible.AdminStoragePath)!
    }

    execute {
        let collection <- self.flovatarAdmin.batchCreateCollectibles(templateId: templateId, quantity: quantity) as! @FlovatarDustCollectibleAccessory.Collection

        for id in collection.getIDs() {
            self.flovatarDustAccessoryCollection.deposit(token: <- collection.withdraw(withdrawID: id))
        }

        destroy collection

    }
}
