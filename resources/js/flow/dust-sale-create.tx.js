import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

/**
 * ĐUST marketplace: list any Flovatar item for ĐUST on the standard NFTStorefrontV2 (no custom contract).
 * kind = flovatar | flobit | flobot | collectible | accessory. One listing per item: a FLOW listing on FlovatarMarketplace
 * is withdrawn first and older storefront listings of the same NFT are removed, so "change price" = list again.
 * Cuts: seller first (residual receiver), creator royalty from the NFT's MetadataViews.Royalties paid only when the
 * creator has a ĐUST receiver, otherwise skipped (Luca 2026-11-25; max 10%), marketplace fee = Flovatar.getMarketplaceCut()
 * (same 5% as the FLOW marketplace) to the Flovatar account's ĐUST receiver. See KB notes/dust-marketplace.md.
 */
export async function dustSaleCreateTx(kind, saleItemID, saleItemPrice, expiry) {
    return await fcl
        .mutate({
            cadence: `
import FungibleToken from 0xFungible
import NonFungibleToken from 0xNonFungible
import MetadataViews from 0xMetadataViews
import NFTStorefrontV2 from 0xStorefront2
import Flovatar, FlovatarComponent, Flobot, FlovatarDustCollectible, FlovatarDustCollectibleAccessory, FlovatarDustToken, FlovatarMarketplace from 0xFlovatar

transaction(kind: String, saleItemID: UInt64, saleItemPrice: UFix64, expiry: UInt64) {
    let storefront: auth(NFTStorefrontV2.CreateListing, NFTStorefrontV2.RemoveListing) &NFTStorefrontV2.Storefront
    let provider: Capability<auth(NonFungibleToken.Withdraw) &{NonFungibleToken.Collection}>
    let nftType: Type
    let saleCuts: [NFTStorefrontV2.SaleCut]

    prepare(acct: auth(Storage, Capabilities) &Account) {
        assert(saleItemPrice > 0.0 && saleItemPrice <= 999999.0, message: "The price must be between 0 and 999,999 DUST")
        assert(expiry > UInt64(getCurrentBlock().timestamp), message: "The expiry must be in the future")

        // Seller DUST vault + public receiver/balance (same capabilities as init-account-dust)
        if acct.storage.borrow<&FlovatarDustToken.Vault>(from: FlovatarDustToken.VaultStoragePath) == nil {
            acct.storage.save(<-FlovatarDustToken.createEmptyVault(vaultType: Type<@FlovatarDustToken.Vault>()), to: FlovatarDustToken.VaultStoragePath)
        }
        if !acct.capabilities.get<&FlovatarDustToken.Vault>(FlovatarDustToken.VaultReceiverPath).check() {
            acct.capabilities.unpublish(FlovatarDustToken.VaultReceiverPath)
            acct.capabilities.publish(acct.capabilities.storage.issue<&FlovatarDustToken.Vault>(FlovatarDustToken.VaultStoragePath), at: FlovatarDustToken.VaultReceiverPath)
        }
        if !acct.capabilities.get<&FlovatarDustToken.Vault>(FlovatarDustToken.VaultBalancePath).check() {
            acct.capabilities.unpublish(FlovatarDustToken.VaultBalancePath)
            acct.capabilities.publish(acct.capabilities.storage.issue<&FlovatarDustToken.Vault>(FlovatarDustToken.VaultStoragePath), at: FlovatarDustToken.VaultBalancePath)
        }

        var path: StoragePath? = nil
        var itemType: Type? = nil
        if kind == "flovatar" {
            path = Flovatar.CollectionStoragePath
            itemType = Type<@Flovatar.NFT>()
        } else if kind == "flobit" {
            path = FlovatarComponent.CollectionStoragePath
            itemType = Type<@FlovatarComponent.NFT>()
        } else if kind == "flobot" {
            path = Flobot.CollectionStoragePath
            itemType = Type<@Flobot.NFT>()
        } else if kind == "collectible" {
            path = FlovatarDustCollectible.CollectionStoragePath
            itemType = Type<@FlovatarDustCollectible.NFT>()
        } else if kind == "accessory" {
            path = FlovatarDustCollectibleAccessory.CollectionStoragePath
            itemType = Type<@FlovatarDustCollectibleAccessory.NFT>()
        }
        let storagePath = path ?? panic("Unknown item kind")
        self.nftType = itemType!

        // Listed for FLOW on FlovatarMarketplace (the NFT sits in the sale collection): take it back first
        if kind == "flovatar" || kind == "flobit" {
            if let market = acct.storage.borrow<auth(FlovatarMarketplace.Withdraw) &FlovatarMarketplace.SaleCollection>(from: FlovatarMarketplace.CollectionStoragePath) {
                if kind == "flovatar" && market.getFlovatarIDs().contains(saleItemID) {
                    let collection = acct.storage.borrow<&Flovatar.Collection>(from: Flovatar.CollectionStoragePath) ?? panic("Missing Flovatar collection")
                    collection.deposit(token: <-market.withdrawFlovatar(tokenId: saleItemID))
                }
                if kind == "flobit" && market.getFlovatarComponentIDs().contains(saleItemID) {
                    let collection = acct.storage.borrow<&FlovatarComponent.Collection>(from: FlovatarComponent.CollectionStoragePath) ?? panic("Missing Flobit collection")
                    collection.deposit(token: <-market.withdrawFlovatarComponent(tokenId: saleItemID))
                }
            }
        }

        // Storefront (created on first use)
        if acct.storage.borrow<&NFTStorefrontV2.Storefront>(from: NFTStorefrontV2.StorefrontStoragePath) == nil {
            acct.storage.save(<-NFTStorefrontV2.createStorefront(), to: NFTStorefrontV2.StorefrontStoragePath)
            acct.capabilities.unpublish(NFTStorefrontV2.StorefrontPublicPath)
            acct.capabilities.publish(acct.capabilities.storage.issue<&NFTStorefrontV2.Storefront>(NFTStorefrontV2.StorefrontStoragePath), at: NFTStorefrontV2.StorefrontPublicPath)
        }
        self.storefront = acct.storage.borrow<auth(NFTStorefrontV2.CreateListing, NFTStorefrontV2.RemoveListing) &NFTStorefrontV2.Storefront>(from: NFTStorefrontV2.StorefrontStoragePath)
            ?? panic("Could not borrow your Storefront")

        // One listing per item: remove older listings of this NFT (any currency)
        for listingID in self.storefront.getExistingListingIDs(nftType: self.nftType, nftID: saleItemID) {
            self.storefront.removeListing(listingResourceID: listingID)
        }

        // Reuse an existing withdraw capability for the collection instead of issuing a new controller per listing
        let providerType = Type<auth(NonFungibleToken.Withdraw) &{NonFungibleToken.Collection}>()
        var existing: Capability<auth(NonFungibleToken.Withdraw) &{NonFungibleToken.Collection}>? = nil
        for controller in acct.capabilities.storage.getControllers(forPath: storagePath) {
            if controller.borrowType == providerType {
                existing = controller.capability as! Capability<auth(NonFungibleToken.Withdraw) &{NonFungibleToken.Collection}>
                break
            }
        }
        self.provider = existing ?? acct.capabilities.storage.issue<auth(NonFungibleToken.Withdraw) &{NonFungibleToken.Collection}>(storagePath)
        let collectionRef = self.provider.borrow() ?? panic("Could not borrow your collection")
        let nft = collectionRef.borrowNFT(saleItemID) ?? panic("This item is not in your collection")
        assert(nft.isInstance(self.nftType), message: "Wrong item type")

        // Sale cuts
        let marketplace = getAccount(0xFlovatar).capabilities.get<&{FungibleToken.Receiver}>(FlovatarDustToken.VaultReceiverPath)
        assert(marketplace.check(), message: "Marketplace DUST receiver missing")
        let seller = acct.capabilities.get<&{FungibleToken.Receiver}>(FlovatarDustToken.VaultReceiverPath)
        assert(seller.check(), message: "Your DUST receiver is missing")

        let fee = saleItemPrice * Flovatar.getMarketplaceCut()
        var creatorShare = 0.0
        var creatorCuts: [NFTStorefrontV2.SaleCut] = []
        if let royalties = nft.resolveView(Type<MetadataViews.Royalties>()) as! MetadataViews.Royalties? {
            for royalty in royalties.getRoyalties() {
                let creator = royalty.receiver.address
                if creator != 0xFlovatar && creator != acct.address && royalty.cut > 0.0 && creatorShare + royalty.cut <= 0.1 {
                    let receiver = getAccount(creator).capabilities.get<&{FungibleToken.Receiver}>(FlovatarDustToken.VaultReceiverPath)
                    if receiver.check() {
                        creatorShare = creatorShare + royalty.cut
                        creatorCuts.append(NFTStorefrontV2.SaleCut(receiver: receiver, amount: saleItemPrice * royalty.cut))
                    }
                }
            }
        }
        var creatorTotal = 0.0
        for cut in creatorCuts {
            creatorTotal = creatorTotal + cut.amount
        }
        var cuts: [NFTStorefrontV2.SaleCut] = [NFTStorefrontV2.SaleCut(receiver: seller, amount: saleItemPrice - fee - creatorTotal)]
        cuts.appendAll(creatorCuts)
        if fee > 0.0 {
            cuts.append(NFTStorefrontV2.SaleCut(receiver: marketplace, amount: fee))
        }
        self.saleCuts = cuts
    }

    execute {
        self.storefront.createListing(
            nftProviderCapability: self.provider,
            nftType: self.nftType,
            nftID: saleItemID,
            salePaymentVaultType: Type<@FlovatarDustToken.Vault>(),
            saleCuts: self.saleCuts,
            marketplacesCapability: nil,
            customID: "flovatar-dust",
            commissionAmount: 0.0,
            expiry: expiry
        )
    }
}
`,
            args: (arg, t) => [
                arg(kind, t.String),
                arg(''+saleItemID, t.UInt64),
                arg(floatArg(saleItemPrice), t.UFix64),
                arg(''+expiry, t.UInt64)
            ],
            limit: 9999
        });

}
