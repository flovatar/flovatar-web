import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

/**
 * ĐUST swap (KB notes/dust-swap.md): buy a ĐUST NFTStorefrontV2 listing PAYING FLOW, atomically: withdraw at most
 * maxFlowIn FLOW (only from availableBalance, never the storage reserve), SwapRouter.swapTokensForExactTokens on the
 * Increment ĐUST/FLOW pool for exactly the listing's ĐUST price, purchase, refund the leftover FLOW. Reverts when the
 * swap needs more than maxFlowIn (slippage) or after `deadline` (unix seconds). Same listing checks and collection /
 * ĐUST vault setup as dust-sale-buy.tx.js. maxFlowIn/deadline are UFix64 strings (dust-swap-math.js ufix()).
 * Passkey accounts: allowlisted on testnet only, behind DUST_SWAP_ENABLED.
 */
export async function dustSwapBuyDustListingTx(storefrontAddress, listingResourceID, expectedPrice, maxFlowIn, deadline) {
    return await fcl
        .mutate({
            cadence: `
import FungibleToken from 0xFungible
import NonFungibleToken from 0xNonFungible
import FlowToken from 0xFlowToken
import NFTStorefrontV2 from 0xStorefront2
import Flovatar, FlovatarComponent, Flobot, FlovatarDustCollectible, FlovatarDustCollectibleAccessory, FlovatarDustToken from 0xFlovatar
import SwapRouter from 0xSwapRouter
import SwapConfig from 0xSwapConfig

transaction(storefrontAddress: Address, listingResourceID: UInt64, expectedPrice: UFix64, maxFlowIn: UFix64, deadline: UFix64) {
    let storefront: &{NFTStorefrontV2.StorefrontPublic}
    let listing: &{NFTStorefrontV2.ListingPublic}
    let listingID: UInt64
    let payment: @{FungibleToken.Vault}
    let collection: &{NonFungibleToken.Collection}

    prepare(buyer: auth(Storage, Capabilities) &Account) {
        assert(storefrontAddress != buyer.address, message: "This is your own listing")
        self.storefront = getAccount(storefrontAddress).capabilities.borrow<&{NFTStorefrontV2.StorefrontPublic}>(NFTStorefrontV2.StorefrontPublicPath)
            ?? panic("The seller has no Storefront")
        self.listingID = listingResourceID
        self.listing = self.storefront.borrowListing(listingResourceID: listingResourceID) ?? panic("This listing is no longer available")
        let details = self.listing.getDetails()
        assert(details.salePaymentVaultType == Type<@FlovatarDustToken.Vault>(), message: "This is not a DUST listing")
        assert(!details.purchased, message: "This item has already been sold")
        assert(details.salePrice == expectedPrice, message: "The price of this listing has changed")
        assert(details.expiry > UInt64(getCurrentBlock().timestamp), message: "This listing has expired")
        assert(self.listing.hasListingBecomeGhosted(), message: "The seller no longer holds this item")

        // Buyer collection for the listed NFT type (created if missing, same capabilities as init-account-dust)
        var path: StoragePath? = nil
        if details.nftType == Type<@Flovatar.NFT>() {
            if buyer.storage.borrow<&Flovatar.Collection>(from: Flovatar.CollectionStoragePath) == nil {
                buyer.storage.save<@{NonFungibleToken.Collection}>(<-Flovatar.createEmptyCollection(nftType: Type<@Flovatar.Collection>()), to: Flovatar.CollectionStoragePath)
            }
            if !buyer.capabilities.get<&Flovatar.Collection>(Flovatar.CollectionPublicPath).check() {
                buyer.capabilities.unpublish(Flovatar.CollectionPublicPath)
                buyer.capabilities.publish(buyer.capabilities.storage.issue<&Flovatar.Collection>(Flovatar.CollectionStoragePath), at: Flovatar.CollectionPublicPath)
            }
            path = Flovatar.CollectionStoragePath
        } else if details.nftType == Type<@FlovatarComponent.NFT>() {
            if buyer.storage.borrow<&FlovatarComponent.Collection>(from: FlovatarComponent.CollectionStoragePath) == nil {
                buyer.storage.save<@{NonFungibleToken.Collection}>(<-FlovatarComponent.createEmptyCollection(nftType: Type<@FlovatarComponent.Collection>()), to: FlovatarComponent.CollectionStoragePath)
            }
            if !buyer.capabilities.get<&FlovatarComponent.Collection>(FlovatarComponent.CollectionPublicPath).check() {
                buyer.capabilities.unpublish(FlovatarComponent.CollectionPublicPath)
                buyer.capabilities.publish(buyer.capabilities.storage.issue<&FlovatarComponent.Collection>(FlovatarComponent.CollectionStoragePath), at: FlovatarComponent.CollectionPublicPath)
            }
            path = FlovatarComponent.CollectionStoragePath
        } else if details.nftType == Type<@Flobot.NFT>() {
            if buyer.storage.borrow<&Flobot.Collection>(from: Flobot.CollectionStoragePath) == nil {
                buyer.storage.save<@{NonFungibleToken.Collection}>(<-Flobot.createEmptyCollection(nftType: Type<@Flobot.Collection>()), to: Flobot.CollectionStoragePath)
            }
            if !buyer.capabilities.get<&Flobot.Collection>(Flobot.CollectionPublicPath).check() {
                buyer.capabilities.unpublish(Flobot.CollectionPublicPath)
                buyer.capabilities.publish(buyer.capabilities.storage.issue<&Flobot.Collection>(Flobot.CollectionStoragePath), at: Flobot.CollectionPublicPath)
            }
            path = Flobot.CollectionStoragePath
        } else if details.nftType == Type<@FlovatarDustCollectible.NFT>() {
            if buyer.storage.borrow<&FlovatarDustCollectible.Collection>(from: FlovatarDustCollectible.CollectionStoragePath) == nil {
                buyer.storage.save<@{NonFungibleToken.Collection}>(<-FlovatarDustCollectible.createEmptyCollection(nftType: Type<@FlovatarDustCollectible.Collection>()), to: FlovatarDustCollectible.CollectionStoragePath)
            }
            if !buyer.capabilities.get<&FlovatarDustCollectible.Collection>(FlovatarDustCollectible.CollectionPublicPath).check() {
                buyer.capabilities.unpublish(FlovatarDustCollectible.CollectionPublicPath)
                buyer.capabilities.publish(buyer.capabilities.storage.issue<&FlovatarDustCollectible.Collection>(FlovatarDustCollectible.CollectionStoragePath), at: FlovatarDustCollectible.CollectionPublicPath)
            }
            path = FlovatarDustCollectible.CollectionStoragePath
        } else if details.nftType == Type<@FlovatarDustCollectibleAccessory.NFT>() {
            if buyer.storage.borrow<&FlovatarDustCollectibleAccessory.Collection>(from: FlovatarDustCollectibleAccessory.CollectionStoragePath) == nil {
                buyer.storage.save<@{NonFungibleToken.Collection}>(<-FlovatarDustCollectibleAccessory.createEmptyCollection(nftType: Type<@FlovatarDustCollectibleAccessory.Collection>()), to: FlovatarDustCollectibleAccessory.CollectionStoragePath)
            }
            if !buyer.capabilities.get<&FlovatarDustCollectibleAccessory.Collection>(FlovatarDustCollectibleAccessory.CollectionPublicPath).check() {
                buyer.capabilities.unpublish(FlovatarDustCollectibleAccessory.CollectionPublicPath)
                buyer.capabilities.publish(buyer.capabilities.storage.issue<&FlovatarDustCollectibleAccessory.Collection>(FlovatarDustCollectibleAccessory.CollectionStoragePath), at: FlovatarDustCollectibleAccessory.CollectionPublicPath)
            }
            path = FlovatarDustCollectibleAccessory.CollectionStoragePath
        }
        let storagePath = path ?? panic("This item type is not sold on Flovatar")
        self.collection = buyer.storage.borrow<&{NonFungibleToken.Collection}>(from: storagePath) ?? panic("Could not borrow your collection")

        // Buyer DUST vault (created if missing; then the balance check fails with a clear message)
        if buyer.storage.borrow<&FlovatarDustToken.Vault>(from: FlovatarDustToken.VaultStoragePath) == nil {
            buyer.storage.save(<-FlovatarDustToken.createEmptyVault(vaultType: Type<@FlovatarDustToken.Vault>()), to: FlovatarDustToken.VaultStoragePath)
        }
        if !buyer.capabilities.get<&FlovatarDustToken.Vault>(FlovatarDustToken.VaultReceiverPath).check() {
            buyer.capabilities.unpublish(FlovatarDustToken.VaultReceiverPath)
            buyer.capabilities.publish(buyer.capabilities.storage.issue<&FlovatarDustToken.Vault>(FlovatarDustToken.VaultStoragePath), at: FlovatarDustToken.VaultReceiverPath)
        }
        if !buyer.capabilities.get<&FlovatarDustToken.Vault>(FlovatarDustToken.VaultBalancePath).check() {
            buyer.capabilities.unpublish(FlovatarDustToken.VaultBalancePath)
            buyer.capabilities.publish(buyer.capabilities.storage.issue<&FlovatarDustToken.Vault>(FlovatarDustToken.VaultStoragePath), at: FlovatarDustToken.VaultBalancePath)
        }
        let dustVault = buyer.storage.borrow<&FlovatarDustToken.Vault>(from: FlovatarDustToken.VaultStoragePath)
            ?? panic("Could not borrow your DUST vault")

        // Pay with FLOW: swap at most maxFlowIn FLOW for exactly the listing price in DUST, refund the rest
        assert(maxFlowIn <= buyer.availableBalance, message: "Not enough FLOW (the storage reserve can't be spent)")
        let flowVault = buyer.storage.borrow<auth(FungibleToken.Withdraw) &FlowToken.Vault>(from: /storage/flowTokenVault)
            ?? panic("Could not borrow your FLOW vault")
        let flowKey = SwapConfig.SliceTokenTypeIdentifierFromVaultType(vaultTypeIdentifier: Type<@FlowToken.Vault>().identifier)
        let dustKey = SwapConfig.SliceTokenTypeIdentifierFromVaultType(vaultTypeIdentifier: Type<@FlovatarDustToken.Vault>().identifier)
        let vaults <- SwapRouter.swapTokensForExactTokens(
            vaultInMax: <-flowVault.withdraw(amount: maxFlowIn),
            exactAmountOut: details.salePrice,
            tokenKeyPath: [flowKey, dustKey],
            deadline: deadline
        )
        let leftover <- vaults.removeLast()
        let swapped <- vaults.removeLast()
        destroy vaults
        flowVault.deposit(from: <-leftover)
        assert(swapped.balance >= details.salePrice, message: "The swap returned less DUST than the price")
        self.payment <- swapped.withdraw(amount: details.salePrice)
        dustVault.deposit(from: <-swapped)
    }

    execute {
        let item <- self.listing.purchase(payment: <-self.payment, commissionRecipient: nil)
        self.collection.deposit(token: <-item)
        // remove the sold listing from the seller's Storefront right away (no "sold, waiting for clean-up" leftovers)
        self.storefront.cleanupPurchasedListings(listingResourceID: self.listingID)
    }
}
`,
            args: (arg, t) => [
                arg(storefrontAddress, t.Address),
                arg(''+listingResourceID, t.UInt64),
                arg(String(expectedPrice), t.UFix64),
                arg(String(maxFlowIn), t.UFix64),
                arg(String(deadline), t.UFix64)
            ],
            limit: 9999
        });

}
