import FungibleToken from 0x9a0766d93b6608b7
import NonFungibleToken from 0x631e88ae7f1d7c20
import Flovatar, FlovatarComponent from 0x9392a4a7c3f49a0b

transaction(
    flovatarId: UInt64,
    address: Address) {

    let flovatarCollection: &Flovatar.Collection
    let flovatarReceiverCollection: Capability<&{Flovatar.CollectionPublic}>

    prepare(account: AuthAccount) {
        self.flovatarCollection = account.borrow<&Flovatar.Collection>(from: Flovatar.CollectionStoragePath)!


        let receiverAccount = getAccount(address)
        self.flovatarReceiverCollection = receiverAccount.getCapability<&{Flovatar.CollectionPublic}>(Flovatar.CollectionPublicPath)
    }

    execute {
        let flovatar <- self.flovatarCollection.withdraw(withdrawID: flovatarId)
        if(flovatar == nil){
            panic("Flovatar not found!")
        }
        self.flovatarReceiverCollection.borrow()!.deposit(token: <-flovatar)
    }
}
