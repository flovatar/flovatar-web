import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"


//this transaction will create a new Webshot and create and auction for it
transaction(
    name: String,
    category: String,
    color: String,
    description: String,
    svg: String,
    series: UInt32,
    maxMintableComponents: UInt64,
    rarity: String) {

    let flovatarComponentTemplateCollection: &FlovatarComponentTemplate.Collection
    let flovatarAdmin: &Flovatar.Admin

    prepare(account: auth(Storage) &Account) {
        self.flovatarComponentTemplateCollection = account.storage.borrow<&FlovatarComponentTemplate.Collection>(from: FlovatarComponentTemplate.CollectionStoragePath)!

        self.flovatarAdmin = account.storage.borrow<&Flovatar.Admin>(from: Flovatar.AdminStoragePath)!
    }

    execute {
        let flovatarComponentTemplate <- self.flovatarAdmin.createComponentTemplate(
            name: name,
            category: category,
            color: color,
            description: description,
            svg: svg,
            series: series,
            maxMintableComponents: maxMintableComponents,
            rarity: rarity
            ) as! @FlovatarComponentTemplate.ComponentTemplate


        self.flovatarComponentTemplateCollection.deposit(componentTemplate: <-flovatarComponentTemplate)

    }
}
