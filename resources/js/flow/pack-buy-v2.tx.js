import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

// Packs v2: buy a DUST pack with an authorisation from POST /pack/reserve (bound to this wallet, expiring).
// The authorisation is checked on-chain by the verifier inside FlovatarPack (FlovatarPack.purchasePack).
export async function packBuyV2Tx(tokenId, amount, expiry, signature) {
    return await fcl
        .mutate({
            cadence: `
import FlovatarDustToken, FlovatarPack from 0xFlovatar
import FungibleToken from 0xFungible
import FlowToken from 0xFlowToken

transaction(tokenId: UInt64, amount: UFix64, expiry: UInt64, signature: String) {

    let collectionCap: Capability<&{FlovatarPack.CollectionPublic}>
    let temporaryVault: @{FungibleToken.Vault}

    prepare(account: auth(Storage, Capabilities) &Account) {

        let flovatarPackCap = account.capabilities.get<&FlovatarPack.Collection>(FlovatarPack.CollectionPublicPath)
        if(!flovatarPackCap.check()) {
            let wallet = account.capabilities.get<&FlowToken.Vault>(/public/flowTokenReceiver)
            if account.storage.borrow<&FlovatarPack.Collection>(from: FlovatarPack.CollectionStoragePath) == nil {
                account.storage.save<@FlovatarPack.Collection>(<- FlovatarPack.createEmptyCollection(ownerVault: wallet), to: FlovatarPack.CollectionStoragePath)
            }
            account.capabilities.unpublish(FlovatarPack.CollectionPublicPath)
            account.capabilities.publish(
                account.capabilities.storage.issue<&FlovatarPack.Collection>(FlovatarPack.CollectionStoragePath),
                at: FlovatarPack.CollectionPublicPath
            )
        }
        self.collectionCap = account.capabilities.get<&{FlovatarPack.CollectionPublic}>(FlovatarPack.CollectionPublicPath)

        let vaultRef = account.storage.borrow<auth(FungibleToken.Withdraw) &FlovatarDustToken.Vault>(from: FlovatarDustToken.VaultStoragePath) ?? panic("Could not borrow owner's Vault reference")
        self.temporaryVault <- vaultRef.withdraw(amount: amount)
    }

    execute {
        FlovatarPack.purchasePack(tokenId: tokenId, recipientCap: self.collectionCap, buyTokens: <- self.temporaryVault, expiry: expiry, signature: signature, expectedPrice: amount)
    }
}
`,
            args: (arg, t) => [
                arg(''+tokenId, t.UInt64),
                arg(floatArg(amount), t.UFix64),
                arg(''+expiry, t.UInt64),
                arg(signature, t.String)
            ],
            limit: 9999
        });
}
