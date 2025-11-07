import Flovatar, FlovatarComponent, FlovatarComponentTemplate, FlovatarPack, FlovatarMarketplace, FlovatarDustToken, FlovatarDustCollectible, FlovatarDustCollectibleAccessory, FlovatarDustCollectibleTemplate from 0x9392a4a7c3f49a0b
import NonFungibleToken from 0x631e88ae7f1d7c20
import FungibleToken from 0x9a0766d93b6608b7
import FlowToken from 0x7e60df042a9c0868

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
