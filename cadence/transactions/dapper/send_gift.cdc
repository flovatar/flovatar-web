import FungibleToken from 0xf233dcee88fe0abe
import NonFungibleToken from 0x1d7e57aa55817448
import Flovatar, FlovatarComponent from 0x921ea449dffec68a

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
