import * as fcl from "@onflow/fcl"
import * as t from "@onflow/types"

/**
 * ĐUST marketplace (chain-first): the ĐUST NFTStorefrontV2 listings of a wallet for Flovatar item types.
 * kind + id given -> only that item's listings; kind = '' -> every ĐUST listing of the wallet (collection page).
 * Each row: listingId, kind, nftID, price, expiry, purchased, holds (seller still has the NFT), expired, valid, cuts.
 */
export async function getDustListingsScript(address, kind, id) {
    if (address == null) return []

    return await fcl
        .query({
            cadence: `
import NFTStorefrontV2 from 0xStorefront2
import Flovatar, FlovatarComponent, Flobot, FlovatarDustCollectible, FlovatarDustCollectibleAccessory, FlovatarDustToken from 0xFlovatar

access(all) struct DustCut {
    access(all) let address: Address
    access(all) let amount: UFix64
    init(address: Address, amount: UFix64) {
        self.address = address
        self.amount = amount
    }
}

access(all) struct DustListing {
    access(all) let storefrontAddress: Address
    access(all) let listingId: UInt64
    access(all) let kind: String
    access(all) let nftID: UInt64
    access(all) let price: UFix64
    access(all) let expiry: UInt64
    access(all) let purchased: Bool
    access(all) let holds: Bool
    access(all) let expired: Bool
    access(all) let valid: Bool
    access(all) let cuts: [DustCut]
    init(storefrontAddress: Address, listingId: UInt64, kind: String, nftID: UInt64, price: UFix64, expiry: UInt64, purchased: Bool, holds: Bool, expired: Bool, cuts: [DustCut]) {
        self.storefrontAddress = storefrontAddress
        self.listingId = listingId
        self.kind = kind
        self.nftID = nftID
        self.price = price
        self.expiry = expiry
        self.purchased = purchased
        self.holds = holds
        self.expired = expired
        self.valid = !purchased && holds && !expired
        self.cuts = cuts
    }
}

access(all) fun kindOf(_ type: Type): String {
    if type == Type<@Flovatar.NFT>() { return "flovatar" }
    if type == Type<@FlovatarComponent.NFT>() { return "flobit" }
    if type == Type<@Flobot.NFT>() { return "flobot" }
    if type == Type<@FlovatarDustCollectible.NFT>() { return "collectible" }
    if type == Type<@FlovatarDustCollectibleAccessory.NFT>() { return "accessory" }
    return ""
}

access(all) fun typeOf(_ kind: String): Type? {
    if kind == "flovatar" { return Type<@Flovatar.NFT>() }
    if kind == "flobit" { return Type<@FlovatarComponent.NFT>() }
    if kind == "flobot" { return Type<@Flobot.NFT>() }
    if kind == "collectible" { return Type<@FlovatarDustCollectible.NFT>() }
    if kind == "accessory" { return Type<@FlovatarDustCollectibleAccessory.NFT>() }
    return nil
}

access(all) fun main(address: Address, kind: String, id: UInt64): [DustListing] {
    let out: [DustListing] = []
    let storefront = getAccount(address).capabilities.borrow<&{NFTStorefrontV2.StorefrontPublic}>(NFTStorefrontV2.StorefrontPublicPath)
    if storefront == nil {
        return out
    }
    var ids: [UInt64] = []
    if kind != "" {
        let type = typeOf(kind)
        if type == nil {
            return out
        }
        ids = storefront!.getExistingListingIDs(nftType: type!, nftID: id)
    } else {
        ids = storefront!.getListingIDs()
    }
    let now = UInt64(getCurrentBlock().timestamp)
    for listingId in ids {
        if let listing = storefront!.borrowListing(listingResourceID: listingId) {
            let d = listing.getDetails()
            let k = kindOf(d.nftType)
            if d.salePaymentVaultType == Type<@FlovatarDustToken.Vault>() && k != "" && out.length < 500 {
                let cuts: [DustCut] = []
                for cut in d.saleCuts {
                    cuts.append(DustCut(address: cut.receiver.address, amount: cut.amount))
                }
                out.append(DustListing(storefrontAddress: address, listingId: listingId, kind: k, nftID: d.nftID, price: d.salePrice, expiry: d.expiry,
                    purchased: d.purchased, holds: listing.hasListingBecomeGhosted(), expired: d.expiry <= now, cuts: cuts))
            }
        }
    }
    return out
}
`,
            args: (arg, t) => [
                arg(address, t.Address),
                arg(kind || '', t.String),
                arg(''+(id || 0), t.UInt64)
            ],
    });

}
