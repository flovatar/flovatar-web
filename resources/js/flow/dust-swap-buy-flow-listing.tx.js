import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

/**
 * ĐUST swap (KB notes/dust-swap.md): buy a FLOW listing (FlovatarMarketplace: kind 'flovatar' or 'flobit') PAYING ĐUST,
 * atomically: withdraw at most maxDustIn ĐUST, SwapRouter.swapTokensForExactTokens on the Increment ĐUST/FLOW pool for
 * exactly the listing's FLOW price, purchase, refund the leftover ĐUST. Reverts when the price changed, the swap needs
 * more than maxDustIn (slippage) or after `deadline`. This SELLS ĐUST into the pool: self-custody Flow Wallet sessions
 * only (NOT on the passkey allowlist, never offered to Dapper). Amounts are UFix64 strings (dust-swap-math.js ufix()).
 */
export async function dustSwapBuyFlowListingTx(kind, saleAddress, tokenId, expectedPrice, maxDustIn, deadline) {
    return await fcl
        .mutate({
            cadence: `
import FungibleToken from 0xFungible
import NonFungibleToken from 0xNonFungible
import FlowToken from 0xFlowToken
import Flovatar, FlovatarComponent, FlovatarMarketplace, FlovatarDustToken from 0xFlovatar
import SwapRouter from 0xSwapRouter
import SwapConfig from 0xSwapConfig

transaction(kind: String, saleAddress: Address, tokenId: UInt64, expectedPrice: UFix64, maxDustIn: UFix64, deadline: UFix64) {
    let marketplace: &{FlovatarMarketplace.SalePublic}
    let payment: @{FungibleToken.Vault}
    let flovatarCap: Capability<&{Flovatar.CollectionPublic}>?
    let componentCap: Capability<&{FlovatarComponent.CollectionPublic}>?

    prepare(buyer: auth(Storage, Capabilities) &Account) {
        assert(saleAddress != buyer.address, message: "This is your own listing")
        self.marketplace = getAccount(saleAddress).capabilities.borrow<&{FlovatarMarketplace.SalePublic}>(FlovatarMarketplace.CollectionPublicPath)
            ?? panic("Could not borrow the seller's sale collection")
        assert(self.marketplace.isInstance(Type<@FlovatarMarketplace.SaleCollection>()), message: "The Marketplace is not from the correct Type")

        var price: UFix64? = nil
        var flovatarCap: Capability<&{Flovatar.CollectionPublic}>? = nil
        var componentCap: Capability<&{FlovatarComponent.CollectionPublic}>? = nil
        if kind == "flovatar" {
            price = self.marketplace.getFlovatarPrice(tokenId: tokenId)
            if buyer.storage.borrow<&Flovatar.Collection>(from: Flovatar.CollectionStoragePath) == nil {
                buyer.storage.save<@{NonFungibleToken.Collection}>(<-Flovatar.createEmptyCollection(nftType: Type<@Flovatar.Collection>()), to: Flovatar.CollectionStoragePath)
            }
            if !buyer.capabilities.get<&Flovatar.Collection>(Flovatar.CollectionPublicPath).check() {
                buyer.capabilities.unpublish(Flovatar.CollectionPublicPath)
                buyer.capabilities.publish(buyer.capabilities.storage.issue<&Flovatar.Collection>(Flovatar.CollectionStoragePath), at: Flovatar.CollectionPublicPath)
            }
            flovatarCap = buyer.capabilities.get<&{Flovatar.CollectionPublic}>(Flovatar.CollectionPublicPath)
        } else if kind == "flobit" {
            price = self.marketplace.getFlovatarComponentPrice(tokenId: tokenId)
            if buyer.storage.borrow<&FlovatarComponent.Collection>(from: FlovatarComponent.CollectionStoragePath) == nil {
                buyer.storage.save<@{NonFungibleToken.Collection}>(<-FlovatarComponent.createEmptyCollection(nftType: Type<@FlovatarComponent.Collection>()), to: FlovatarComponent.CollectionStoragePath)
            }
            if !buyer.capabilities.get<&FlovatarComponent.Collection>(FlovatarComponent.CollectionPublicPath).check() {
                buyer.capabilities.unpublish(FlovatarComponent.CollectionPublicPath)
                buyer.capabilities.publish(buyer.capabilities.storage.issue<&FlovatarComponent.Collection>(FlovatarComponent.CollectionStoragePath), at: FlovatarComponent.CollectionPublicPath)
            }
            componentCap = buyer.capabilities.get<&{FlovatarComponent.CollectionPublic}>(FlovatarComponent.CollectionPublicPath)
        } else {
            panic("Unsupported item type")
        }
        let salePrice = price ?? panic("This item is no longer for sale")
        assert(salePrice == expectedPrice, message: "The price of this listing has changed")
        self.flovatarCap = flovatarCap
        self.componentCap = componentCap

        // Pay with DUST: swap at most maxDustIn DUST for exactly the FLOW price, refund the rest
        let dustVault = buyer.storage.borrow<auth(FungibleToken.Withdraw) &FlovatarDustToken.Vault>(from: FlovatarDustToken.VaultStoragePath)
            ?? panic("You have no DUST vault")
        assert(dustVault.balance >= maxDustIn, message: "Not enough DUST")
        let dustKey = SwapConfig.SliceTokenTypeIdentifierFromVaultType(vaultTypeIdentifier: Type<@FlovatarDustToken.Vault>().identifier)
        let flowKey = SwapConfig.SliceTokenTypeIdentifierFromVaultType(vaultTypeIdentifier: Type<@FlowToken.Vault>().identifier)
        let vaults <- SwapRouter.swapTokensForExactTokens(
            vaultInMax: <-dustVault.withdraw(amount: maxDustIn),
            exactAmountOut: salePrice,
            tokenKeyPath: [dustKey, flowKey],
            deadline: deadline
        )
        let leftover <- vaults.removeLast()
        let swapped <- vaults.removeLast()
        destroy vaults
        dustVault.deposit(from: <-leftover)
        assert(swapped.balance >= salePrice, message: "The swap returned less FLOW than the price")
        self.payment <- swapped.withdraw(amount: salePrice)
        let flowVault = buyer.storage.borrow<&FlowToken.Vault>(from: /storage/flowTokenVault)
            ?? panic("Could not borrow your FLOW vault")
        flowVault.deposit(from: <-swapped)
    }

    execute {
        if let cap = self.flovatarCap {
            self.marketplace.purchaseFlovatar(tokenId: tokenId, recipientCap: cap, buyTokens: <-self.payment)
        } else {
            self.marketplace.purchaseFlovatarComponent(tokenId: tokenId, recipientCap: self.componentCap!, buyTokens: <-self.payment)
        }
    }
}
`,
            args: (arg, t) => [
                arg(kind, t.String),
                arg(saleAddress, t.Address),
                arg('' + tokenId, t.UInt64),
                arg(String(expectedPrice), t.UFix64),
                arg(String(maxDustIn), t.UFix64),
                arg(String(deadline), t.UFix64),
            ],
            limit: 9999
        });
}
