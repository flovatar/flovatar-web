import FungibleToken from "../../contracts/FungibleToken.cdc"
import NonFungibleToken from "../../contracts/NonFungibleToken.cdc"
import FlowToken from "../../contracts/FlowToken.cdc"
import Flovatar from "../../contracts/Flovatar.cdc"
import FlovatarComponent from "../../contracts/FlovatarComponent.cdc"
import FlobotTemplate from "../../contracts/FlobotTemplate.cdc"
import FlovatarPack from "../../contracts/FlovatarPack.cdc"
import FlovatarMarketplace from "../../contracts/FlovatarMarketplace.cdc"


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

    let flobotTemplateCollection: &FlobotTemplate.Collection
    let flobotAdmin: &Flobot.Admin

    prepare(account: AuthAccount) {
        self.flobotTemplateCollection = account.borrow<&FlobotTemplate.Collection>(from: FlobotTemplate.CollectionStoragePath)!

        self.flobotAdmin = account.borrow<&Flobot.Admin>(from: Flobot.AdminStoragePath)!
    }

    execute {
        let flobotComponentTemplate <- self.flobotAdmin.createComponentTemplate(
            name: name,
            category: category,
            color: color,
            description: description,
            svg: svg,
            series: series,
            maxMintableComponents: maxMintableComponents,
            rarity: rarity
            ) as! @FlovatarComponentTemplate.ComponentTemplate


        self.flobotTemplateCollection.deposit(componentTemplate: <-flobotComponentTemplate)

    }
}
