import FungibleToken from 0xf233dcee88fe0abe
import NonFungibleToken from 0x1d7e57aa55817448
import Flovatar, FlovatarComponent from 0x921ea449dffec68a

transaction(
    componentId: UInt64,
    address: Address) {

    let flovatarComponentCollection: &FlovatarComponent.Collection
    let flovatarComponentReceiverCollection: Capability<&{FlovatarComponent.CollectionPublic}>

    prepare(account: AuthAccount) {
        self.flovatarComponentCollection = account.borrow<&FlovatarComponent.Collection>(from: FlovatarComponent.CollectionStoragePath)!


        let receiverAccount = getAccount(address)
        self.flovatarComponentReceiverCollection = receiverAccount.getCapability<&{FlovatarComponent.CollectionPublic}>(FlovatarComponent.CollectionPublicPath)
    }

    execute {
        let component <- self.flovatarComponentCollection.withdraw(withdrawID: componentId)
        if(component == nil){
            panic("Component not found!")
        }
        self.flovatarComponentReceiverCollection.borrow()!.deposit(token: <-component)
    }
}
