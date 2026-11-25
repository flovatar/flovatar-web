// Buy a FLOW-priced pack with a packs-v2 authorisation for the signer's own address (seeds the sponsor's stock).
import FungibleToken from 0xFungibleToken
import FlowToken from 0xFlowToken
import FlovatarPack from 0xFlovatar

transaction(tokenId: UInt64, amount: UFix64, expiry: UInt64, signature: String) {
    let collectionCap: Capability<&{FlovatarPack.CollectionPublic}>
    let payment: @{FungibleToken.Vault}

    prepare(account: auth(Storage, Capabilities) &Account) {
        self.collectionCap = account.capabilities.get<&{FlovatarPack.CollectionPublic}>(FlovatarPack.CollectionPublicPath)
        let vault = account.storage.borrow<auth(FungibleToken.Withdraw) &FlowToken.Vault>(from: /storage/flowTokenVault)!
        self.payment <- vault.withdraw(amount: amount)
    }

    execute {
        FlovatarPack.purchasePack(tokenId: tokenId, recipientCap: self.collectionCap, buyTokens: <- self.payment, expiry: expiry, signature: signature, expectedPrice: amount)
    }
}
