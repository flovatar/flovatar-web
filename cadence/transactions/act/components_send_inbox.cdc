import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"
import FlovatarInbox from "../../contracts/FlovatarInbox.cdc"


//this transaction will send a Pack to an address
transaction(componentIds: [UInt64], address: Address) {

    let flovatarComponentCollection: &FlovatarComponent.Collection

    prepare(account: AuthAccount) {
        self.flovatarComponentCollection = account.borrow<&FlovatarComponent.Collection>(from: FlovatarComponent.CollectionStoragePath)!

        if let inboxCollection = account.getCapability(FlovatarInbox.CollectionPublicPath).borrow<&{FlovatarInbox.CollectionPublic}>() {
            var i: UInt32 = 0

            while i < UInt32(componentIds.length) {
                let component: @FlovatarComponent.NFT <- self.flovatarComponentCollection.withdraw(withdrawID: componentIds[i]) as! @FlovatarComponent.NFT
                if(component == nil){
                    panic("Component not found!")
                }

                inboxCollection.depositComponentToWallet(address: address, component: <- component)

                i = i + UInt32(1)
            }

        }
    }

    execute {



    }
}
