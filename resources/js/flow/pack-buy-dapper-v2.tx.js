import * as fcl from "@onflow/fcl"
import * as t from "@onflow/types"

// Packs v2 (Dapper wallet): buy a pack with FlowUtilityToken and an authorisation from POST /pack/reserve
// (bound to this wallet, kind "dapper", expiring). Checked on-chain by the verifier inside FlovatarPack.
export async function packBuyDapperV2Tx(tokenId, expectedPrice, expiry, signature) {
    return await fcl
        .mutate({
            cadence: `
import FungibleToken from 0xFungible
import FlowUtilityToken from 0xDuc
import FlowToken from 0xFlowToken
import FlovatarPack from 0xFlovatar

transaction(tokenId: UInt64, expectedPrice: UFix64, expiry: UInt64, signature: String) {
    let paymentVault: @{FungibleToken.Vault}
    let buyerFlovatarPackCollection: Capability<&{FlovatarPack.CollectionPublic}>
    let balanceBeforeTransfer: UFix64
    let mainDucVault: auth(FungibleToken.Withdraw) &FlowUtilityToken.Vault

    prepare(dapper: auth(Storage) &Account, buyer: auth(Storage, Capabilities) &Account) {
        let flovatarPackCap = buyer.capabilities.get<&{FlovatarPack.CollectionPublic}>(FlovatarPack.CollectionPublicPath)
        if(!flovatarPackCap.check()) {
            let wallet = buyer.capabilities.get<&FlowToken.Vault>(/public/flowTokenReceiver)
            if buyer.storage.borrow<&FlovatarPack.Collection>(from: FlovatarPack.CollectionStoragePath) == nil {
                buyer.storage.save<@FlovatarPack.Collection>(<- FlovatarPack.createEmptyCollection(ownerVault: wallet), to: FlovatarPack.CollectionStoragePath)
            }
            buyer.capabilities.unpublish(FlovatarPack.CollectionPublicPath)
            buyer.capabilities.publish(
                buyer.capabilities.storage.issue<&{FlovatarPack.CollectionPublic}>(FlovatarPack.CollectionStoragePath),
                at: FlovatarPack.CollectionPublicPath
            )
        }

        self.mainDucVault = dapper.storage.borrow<auth(FungibleToken.Withdraw) &FlowUtilityToken.Vault>(from: /storage/flowUtilityTokenVault)
            ?? panic("Cannot borrow FlowUtilityToken vault from dapper storage")
        self.balanceBeforeTransfer = self.mainDucVault.balance
        self.paymentVault <- self.mainDucVault.withdraw(amount: expectedPrice)

        self.buyerFlovatarPackCollection = buyer.capabilities.get<&{FlovatarPack.CollectionPublic}>(FlovatarPack.CollectionPublicPath)
    }

    execute {
        FlovatarPack.purchasePack(tokenId: tokenId, recipientCap: self.buyerFlovatarPackCollection, buyTokens: <- self.paymentVault, expiry: expiry, signature: signature, expectedPrice: expectedPrice)
    }

    post {
        // Ensure there is no DUC leakage
        self.mainDucVault.balance == self.balanceBeforeTransfer: "transaction would leak DUC"
    }
}
`,
            args: (arg, t) => [
                arg(''+tokenId, t.UInt64),
                arg(floatArg(expectedPrice), t.UFix64),
                arg(''+expiry, t.UInt64),
                arg(signature, t.String)
            ],
            limit: 9999
        });
}
