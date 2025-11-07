import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"


//this transaction will send a Pack to an address
transaction(componentIds: [UInt64], address: Address) {

    let flovatarComponentCollection: &FlovatarComponent.Collection
    let flovatarComponentReceiverCollection: Capability<&{FlovatarComponent.CollectionPublic}>

    prepare(account: AuthAccount) {
        self.flovatarComponentCollection = account.borrow<&FlovatarComponent.Collection>(from: FlovatarComponent.CollectionStoragePath)!


        let receiverAccount = getAccount(address)
        self.flovatarComponentReceiverCollection = receiverAccount.getCapability<&{FlovatarComponent.CollectionPublic}>(FlovatarComponent.CollectionPublicPath)
    }

    execute {
        var i: UInt32 = 0

        while i < UInt32(componentIds.length) {
            let component <- self.flovatarComponentCollection.withdraw(withdrawID: componentIds[i])
            if(component == nil){
                panic("Component not found!")
            }
            self.flovatarComponentReceiverCollection.borrow()!.deposit(token: <-component)

            i = i + UInt32(1)
        }
    }
}
