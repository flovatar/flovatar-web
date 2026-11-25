import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

/** ĐUST marketplace: the seller removes one of their NFTStorefrontV2 listings (no-op when it is already gone). */
export async function dustSaleCancelTx(listingResourceID) {
    return await fcl
        .mutate({
            cadence: `
import NFTStorefrontV2 from 0xStorefront2

transaction(listingResourceID: UInt64) {
    let storefront: auth(NFTStorefrontV2.RemoveListing) &NFTStorefrontV2.Storefront

    prepare(acct: auth(Storage) &Account) {
        self.storefront = acct.storage.borrow<auth(NFTStorefrontV2.RemoveListing) &NFTStorefrontV2.Storefront>(from: NFTStorefrontV2.StorefrontStoragePath)
            ?? panic("You have no Storefront")
    }

    execute {
        if self.storefront.borrowListing(listingResourceID: listingResourceID) != nil {
            self.storefront.removeListing(listingResourceID: listingResourceID)
        }
    }
}
`,
            args: (arg, t) => [
                arg(''+listingResourceID, t.UInt64)
            ],
            limit: 9999
        });

}
