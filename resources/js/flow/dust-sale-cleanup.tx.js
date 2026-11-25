import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

/**
 * ĐUST marketplace: ANYONE can remove a dead NFTStorefrontV2 listing from someone's storefront (public contract functions):
 * purchased -> cleanupPurchasedListings, seller no longer holds the NFT (ghost) -> cleanupGhostListings,
 * expired -> cleanupExpiredListings on its index. Panics when the listing is still valid.
 */
export async function dustSaleCleanupTx(storefrontAddress, listingResourceID) {
    return await fcl
        .mutate({
            cadence: `
import NFTStorefrontV2 from 0xStorefront2

transaction(storefrontAddress: Address, listingResourceID: UInt64) {
    let storefront: &{NFTStorefrontV2.StorefrontPublic}

    prepare(acct: &Account) {
        self.storefront = getAccount(storefrontAddress).capabilities.borrow<&{NFTStorefrontV2.StorefrontPublic}>(NFTStorefrontV2.StorefrontPublicPath)
            ?? panic("No Storefront at this address")
    }

    execute {
        if let listing = self.storefront.borrowListing(listingResourceID: listingResourceID) {
            let details = listing.getDetails()
            if details.purchased {
                self.storefront.cleanupPurchasedListings(listingResourceID: listingResourceID)
            } else if !listing.hasListingBecomeGhosted() {
                self.storefront.cleanupGhostListings(listingResourceID: listingResourceID)
            } else if details.expiry <= UInt64(getCurrentBlock().timestamp) {
                let ids = self.storefront.getListingIDs()
                let index = UInt64(ids.firstIndex(of: listingResourceID)!)
                self.storefront.cleanupExpiredListings(fromIndex: index, toIndex: index)
            } else {
                panic("This listing is still valid")
            }
        }
    }
}
`,
            args: (arg, t) => [
                arg(storefrontAddress, t.Address),
                arg(''+listingResourceID, t.UInt64)
            ],
            limit: 9999
        });

}
