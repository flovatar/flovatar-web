import Flovatar, FlovatarComponent, FlovatarComponentTemplate, FlovatarPack, FlovatarMarketplace, FlovatarDustToken, FlovatarDustCollectible, FlovatarDustCollectibleAccessory, FlovatarDustCollectibleTemplate from 0x921ea449dffec68a
import NonFungibleToken from 0x1d7e57aa55817448
import FungibleToken from 0xf233dcee88fe0abe
import FlowToken from 0x1654653399040a61

//this transaction will create a new Webshot and create and auction for it
transaction(
    series: UInt64,
    layers: [UInt32],
    templates: [UInt64?],
    amount: UFix64
    ){

    let flovatarCollectibleCollection: &FlovatarDustCollectible.Collection
    let temporaryVault: @FungibleToken.Vault

    let accountAddress: Address

    prepare(account: AuthAccount) {
        self.flovatarCollectibleCollection = account.borrow<&FlovatarDustCollectible.Collection>(from: FlovatarDustCollectible.CollectionStoragePath)!
        self.accountAddress = account.address


        let vaultRef = account.borrow<&{FungibleToken.Provider}>(from: FlovatarDustToken.VaultStoragePath) ?? panic("Could not borrow owner's Vault reference")
        // withdraw tokens from the buyer's Vault
        self.temporaryVault <- vaultRef.withdraw(amount: amount)
    }

    execute {

        let collectible <- FlovatarDustCollectible.createDustCollectible(
            series: series,
            layers: layers,
            templateIds: templates,
            address: self.accountAddress,
            vault: <-self.temporaryVault
        )

        self.flovatarCollectibleCollection.deposit(token: <-collectible)
    }
}
