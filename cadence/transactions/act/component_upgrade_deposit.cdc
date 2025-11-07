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
import FlovatarDustToken from "../../contracts/FlovatarDustToken.cdc"
import FlovatarComponentUpgrader from "../../contracts/FlovatarComponentUpgrader.cdc"


transaction(
    componentIds: [UInt64]
    ) {

    let componentCollection: &FlovatarComponent.Collection
    let upgraderCollection: &FlovatarComponentUpgrader.Collection

    prepare(account: AuthAccount) {

        self.componentCollection = account.borrow<&FlovatarComponent.Collection>(from: FlovatarComponent.CollectionStoragePath)!
        self.upgraderCollection = account.borrow<&FlovatarComponentUpgrader.Collection>(from: FlovatarComponentUpgrader.CollectionStoragePath)!
    }

    execute {

        for componentId in componentIds {
            let tempNFT = self.componentCollection.borrowComponent(id: componentId)
            if(tempNFT != nil){
                let newNFT <- self.componentCollection.withdraw(withdrawID: componentId) as! @FlovatarComponent.NFT
                self.upgraderCollection.depositComponent(component: <- newNFT)
            }
        }
    }
}
