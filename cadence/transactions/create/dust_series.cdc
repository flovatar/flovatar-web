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
    svgPrefix: String,
    svgSuffix: String,
    priceIncrease: UFix64,
    layersId: [UInt32],
    layersName: [String],
    layersIsAccessory: [Bool],
    colorsId: [UInt32],
    colorsName: [String],
    maxMintable: UInt64) {

    let flovatarDustCollectibleTemplateCollection: &FlovatarDustCollectibleTemplate.Collection
    let flovatarAdmin: &FlovatarDustCollectible.Admin

    prepare(account: AuthAccount) {
        self.flovatarDustCollectibleTemplateCollection = account.borrow<&FlovatarDustCollectibleTemplate.Collection>(from: FlovatarDustCollectibleTemplate.CollectionStoragePath)!

        self.flovatarAdmin = account.borrow<&FlovatarDustCollectible.Admin>(from: FlovatarDustCollectible.AdminStoragePath)!
    }

    execute {
        let layers: {UInt32: FlovatarDustCollectibleTemplate.Layer} = {}
        var i: UInt32 = UInt32(0)
        while(i <  UInt32(layersId.length)){
            layers.insert(key: layersId[i]!, FlovatarDustCollectibleTemplate.Layer(id: layersId[i]!, name: layersName[i]!, isAccessory: layersIsAccessory[i]!))
            i = i + UInt32(1)
        }


        let colors: {UInt32: String} = {}
        i = UInt32(0)
        while(i <  UInt32(colorsId.length)){
            colors.insert(key: colorsId[i]!, colorsName[i]!)
            i = i + UInt32(1)
        }

        let flovatarDustCollectibleSeries <- self.flovatarAdmin.createCollectibleSeries(
            name: name,
            description: description,
            svgPrefix: svgPrefix,
            svgSuffix: svgSuffix,
            priceIncrease: priceIncrease,
            layers: layers,
            colors: colors,
            metadata: {},
            maxMintable: maxMintable
            ) as! @FlovatarDustCollectibleTemplate.CollectibleSeries


        self.flovatarDustCollectibleTemplateCollection.depositSeries(collectibleSeries: <-flovatarDustCollectibleSeries)

    }
}
