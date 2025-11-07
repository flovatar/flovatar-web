import FungibleToken from 0xf233dcee88fe0abe
import NonFungibleToken from 0x1d7e57aa55817448
import Flovatar, FlovatarComponent, Flobot from 0x921ea449dffec68a

transaction(
    flobotId: UInt64,
    address: Address) {

    let flobotCollection: &Flobot.Collection
    let flobotReceiverCollection: Capability<&{Flobot.CollectionPublic}>

    prepare(account: AuthAccount) {
        self.flobotCollection = account.borrow<&Flobot.Collection>(from: Flobot.CollectionStoragePath)!


        let receiverAccount = getAccount(address)
        self.flobotReceiverCollection = receiverAccount.getCapability<&{Flobot.CollectionPublic}>(Flobot.CollectionPublicPath)
    }

    execute {
        let flobot <- self.flobotCollection.withdraw(withdrawID: flobotId)
        if(flobot == nil){
            panic("Flobot not found!")
        }
        self.flobotReceiverCollection.borrow()!.deposit(token: <-flobot)
    }
}
