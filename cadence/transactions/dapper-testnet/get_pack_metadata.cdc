import FlowUtilityToken from 0x82ec283f88a62e65
import Flovatar, FlovatarComponent from 0x9392a4a7c3f49a0b

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

access(all) fun main(storefrontAddress: Address, listingResourceID: UInt64, expectedPrice: UFix64, signature: String, flovatarAddress: Address): PurchaseData {

    let purchaseData = PurchaseData(
        id: listingResourceID,
        name: "Flovatar Pack",
        amount: expectedPrice,
        description: "Flovatar Pack waiting to be opened and containing awesome Flovatar NFTs",
        imageURL: "https://flovatar.com/images/TerraKit-pulsating.svg",
        paymentVaultTypeID: Type<@FlowUtilityToken.Vault>()
    )

    return purchaseData
}
