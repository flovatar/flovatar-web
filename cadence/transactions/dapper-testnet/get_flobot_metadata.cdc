import NFTStorefrontV2 from 0x2d55b98eb200daef
import MetadataViews from 0x631e88ae7f1d7c20
import Flobot from 0x9392a4a7c3f49a0b

pub struct PurchaseData {
    pub let id: UInt64
    pub let name: String
    pub let amount: UFix64
    pub let description: String
    pub let imageURL: String
    pub let paymentVaultTypeID: Type

    init(id: UInt64, name: String, amount: UFix64, description: String, imageURL: String, paymentVaultTypeID: Type) {
        self.id = id
        self.name = name
        self.amount = amount
        self.description = description
        self.imageURL = imageURL
        self.paymentVaultTypeID = paymentVaultTypeID
    }
}

access(all) fun main(storefrontAddress: Address, listingResourceID: UInt64, expectedPrice: UFix64, commissionRecipient: Address): PurchaseData {


    let account = getAccount(storefrontAddress)
    let marketCollectionRef = account
        .getCapability<&NFTStorefrontV2.Storefront{NFTStorefrontV2.StorefrontPublic}>(
            NFTStorefrontV2.StorefrontPublicPath
        )
        .borrow()
        ?? panic("Could not borrow market collection from address")

    let saleItem = marketCollectionRef.borrowListing(listingResourceID: listingResourceID)
        ?? panic("No item with that ID")

    let listingDetails = saleItem.getDetails()!

    let collection = account.getCapability(Flobot.CollectionPublicPath)
    .borrow<&{Flobot.CollectionPublic}>()
    ?? panic("Could not borrow a reference to the collection")

    let nft = collection.borrowComponent(id: listingDetails.nftID)
            ?? panic("Could not borrow a reference to the collection")

    if let view = nft.resolveView(Type<MetadataViews.Display>()) {

        let display = view as! MetadataViews.Display

        let purchaseData = PurchaseData(
            id: listingDetails.nftID,
            name: (display.name == "") ? "Flovatar Flobot" : display.name,
            amount: listingDetails.salePrice,
            description: (display.description == "") ? "Flovatar Flobot" : display.description,
            imageURL: display.thumbnail.uri(),
            paymentVaultTypeID: listingDetails.salePaymentVaultType
        )

        return purchaseData
    }
     panic("No NFT")
}
