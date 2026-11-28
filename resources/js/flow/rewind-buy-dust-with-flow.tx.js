import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

// Pack Rewind (testnet): buy the ĐUST Flobit Pack PAYING FLOW, in one transaction: withdraw at most maxFlowIn FLOW
// (spendable FLOW only), swap it on the Increment ĐUST/FLOW pool for EXACTLY `amount` ĐUST (the pack price), buy the
// pack with that ĐUST (FlovatarPack.purchasePack, kind "dust": the ĐUST is burned like in 2023) and return the unused
// FLOW. Reverts on slippage (EXCESSIVE_INPUT_AMOUNT) or after `deadline` (unix seconds). Same swap as the FUB refill.
export async function rewindBuyDustWithFlowTx(tokenId, amount, maxFlowIn, deadline, expiry, signature) {
    return await fcl
        .mutate({
            cadence: `
import FlovatarComponent, FlovatarPack, FlovatarDustToken from 0xFlovatar
import FungibleToken from 0xFungible
import FlowToken from 0xFlowToken
import SwapRouter from 0xSwapRouter
import SwapConfig from 0xSwapConfig

transaction(tokenId: UInt64, amount: UFix64, maxFlowIn: UFix64, deadline: UFix64, expiry: UInt64, signature: String) {
    let collectionCap: Capability<&{FlovatarPack.CollectionPublic}>
    let payment: @{FungibleToken.Vault}

    prepare(account: auth(Storage, Capabilities) &Account) {
        assert(maxFlowIn <= account.availableBalance, message: "Not enough FLOW (the storage reserve can't be spent)")
        if account.storage.borrow<&FlovatarPack.Collection>(from: FlovatarPack.CollectionStoragePath) == nil {
            let wallet = account.capabilities.get<&FlowToken.Vault>(/public/flowTokenReceiver)
            account.storage.save<@FlovatarPack.Collection>(<- FlovatarPack.createEmptyCollection(ownerVault: wallet), to: FlovatarPack.CollectionStoragePath)
        }
        if !account.capabilities.get<&FlovatarPack.Collection>(FlovatarPack.CollectionPublicPath).check() {
            account.capabilities.unpublish(FlovatarPack.CollectionPublicPath)
            account.capabilities.publish(account.capabilities.storage.issue<&FlovatarPack.Collection>(FlovatarPack.CollectionStoragePath), at: FlovatarPack.CollectionPublicPath)
        }
        if account.storage.type(at: FlovatarComponent.CollectionStoragePath) == nil {
            account.storage.save(<-FlovatarComponent.createEmptyCollection(nftType: Type<@FlovatarComponent.NFT>()), to: FlovatarComponent.CollectionStoragePath)
        }
        if !account.capabilities.get<&{FlovatarComponent.CollectionPublic}>(FlovatarComponent.CollectionPublicPath).check() {
            account.capabilities.unpublish(FlovatarComponent.CollectionPublicPath)
            account.capabilities.publish(account.capabilities.storage.issue<&FlovatarComponent.Collection>(FlovatarComponent.CollectionStoragePath), at: FlovatarComponent.CollectionPublicPath)
        }
        self.collectionCap = account.capabilities.get<&{FlovatarPack.CollectionPublic}>(FlovatarPack.CollectionPublicPath)

        let flowVault = account.storage.borrow<auth(FungibleToken.Withdraw) &FlowToken.Vault>(from: /storage/flowTokenVault)
            ?? panic("Could not borrow your FLOW vault")
        let flowKey = SwapConfig.SliceTokenTypeIdentifierFromVaultType(vaultTypeIdentifier: Type<@FlowToken.Vault>().identifier)
        let dustKey = SwapConfig.SliceTokenTypeIdentifierFromVaultType(vaultTypeIdentifier: Type<@FlovatarDustToken.Vault>().identifier)
        let vaults <- SwapRouter.swapTokensForExactTokens(
            vaultInMax: <-flowVault.withdraw(amount: maxFlowIn),
            exactAmountOut: amount,
            tokenKeyPath: [flowKey, dustKey],
            deadline: deadline
        )
        let leftover <- vaults.removeLast()
        let swapped <- vaults.removeLast()
        destroy vaults
        flowVault.deposit(from: <-leftover)
        assert(swapped.balance >= amount, message: "The swap returned less DUST than the pack price")
        self.payment <- swapped.withdraw(amount: amount)
        // exact-output swap: nothing should be left; any rounding extra goes to the buyer's DUST vault
        if let mine = account.storage.borrow<&FlovatarDustToken.Vault>(from: FlovatarDustToken.VaultStoragePath) {
            mine.deposit(from: <-swapped)
        } else {
            assert(swapped.balance == 0.0, message: "No DUST vault for the swap remainder")
            destroy swapped
        }
    }

    execute {
        FlovatarPack.purchasePack(tokenId: tokenId, recipientCap: self.collectionCap, buyTokens: <- self.payment, expiry: expiry, signature: signature, expectedPrice: amount)
    }
}
`,
            args: (arg, t) => [
                arg('' + tokenId, t.UInt64),
                arg(String(amount), t.UFix64),
                arg(String(maxFlowIn), t.UFix64),
                arg(String(deadline), t.UFix64),
                arg('' + expiry, t.UInt64),
                arg(signature, t.String)
            ],
            limit: 9999
        });
}
