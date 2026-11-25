// Sponsor-only: send starter Flobits from the sponsor's stock to a user.
import NonFungibleToken from 0xNonFungibleToken
import FlovatarComponent from 0xFlovatar

transaction(to: Address, ids: [UInt64]) {
    let stock: auth(NonFungibleToken.Withdraw) &FlovatarComponent.Collection
    let receiver: &{FlovatarComponent.CollectionPublic}

    prepare(sponsor: auth(BorrowValue) &Account) {
        self.stock = sponsor.storage.borrow<auth(NonFungibleToken.Withdraw) &FlovatarComponent.Collection>(from: FlovatarComponent.CollectionStoragePath)
            ?? panic("sponsor has no Flobit stock")
        self.receiver = getAccount(to).capabilities.borrow<&{FlovatarComponent.CollectionPublic}>(FlovatarComponent.CollectionPublicPath)
            ?? panic("recipient has no Flobit collection (run the setup step first)")
    }

    execute {
        for id in ids {
            self.receiver.deposit(token: <- self.stock.withdraw(withdrawID: id))
        }
    }
}
