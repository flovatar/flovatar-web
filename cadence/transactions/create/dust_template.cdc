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


//this transaction will create a new Webshot and create and auction for it
transaction(
    name: String,
    description: String,
    series: UInt64,
    layer: UInt32,
    rarity: String,
    basePrice: UFix64,
    svg: String,
    maxMintableComponents: UInt64) {

    let flovatarDustCollectibleTemplateCollection: &FlovatarDustCollectibleTemplate.Collection
    let flovatarAdmin: &FlovatarDustCollectible.Admin

    prepare(account: AuthAccount) {
        self.flovatarDustCollectibleTemplateCollection = account.borrow<&FlovatarDustCollectibleTemplate.Collection>(from: FlovatarDustCollectibleTemplate.CollectionStoragePath)!

        self.flovatarAdmin = account.borrow<&FlovatarDustCollectible.Admin>(from: FlovatarDustCollectible.AdminStoragePath)!
    }

    execute {
        let flovatarDustCollectibleTemplate <- self.flovatarAdmin.createCollectibleTemplate(
            name: name,
            description: description,
            series: series,
            layer: layer,
            metadata: {},
            rarity: rarity,
            basePrice: basePrice,
            svg: svg,
            maxMintableComponents: maxMintableComponents
            ) as! @FlovatarDustCollectibleTemplate.CollectibleTemplate


        self.flovatarDustCollectibleTemplateCollection.deposit(collectibleTemplate: <-flovatarDustCollectibleTemplate)

    }
}
