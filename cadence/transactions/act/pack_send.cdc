import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"


//this transaction will send a Pack to an address
transaction(packId: UInt64, address: Address) {

    let flovatarPackCollection: auth(FlovatarPack.WithdrawEnt) &FlovatarPack.Collection
    let flovatarPackReceiverCollection: Capability<&{FlovatarPack.CollectionPublic}>

    prepare(account: auth(Storage) &Account) {
        self.flovatarPackCollection = account.storage.borrow<auth(FlovatarPack.WithdrawEnt) &FlovatarPack.Collection>(from: FlovatarPack.CollectionStoragePath)!


        let receiverAccount = getAccount(address)
        self.flovatarPackReceiverCollection = receiverAccount.capabilities.get<&{FlovatarPack.CollectionPublic}>(FlovatarPack.CollectionPublicPath)
    }

    execute {
        let pack <- self.flovatarPackCollection.withdraw(withdrawID: packId)
        if(pack == nil){
            panic("Pack not found!")
        }
        self.flovatarPackReceiverCollection.borrow()!.deposit(token: <-pack)
    }
}
