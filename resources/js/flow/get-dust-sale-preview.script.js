import * as fcl from "@onflow/fcl"
import * as t from "@onflow/types"

/**
 * ĐUST marketplace: what a ĐUST listing at `price` would pay (same rules as dust-sale-create.tx.js): marketplace fee
 * (Flovatar.getMarketplaceCut() to the Flovatar account), creator royalties from the NFT's MetadataViews.Royalties only
 * when the creator has a ĐUST receiver (Luca 2026-11-25), the rest to the seller.
 * Returns {fee, feeRate, creator, creatorAddresses, seller} or nil when the item is not in the seller's collection.
 */
export async function getDustSalePreviewScript(address, kind, id, price) {
    if (address == null) return null

    return await fcl
        .query({
            cadence: `
import NonFungibleToken from 0xNonFungible
import FungibleToken from 0xFungible
import MetadataViews from 0xMetadataViews
import Flovatar, FlovatarComponent, Flobot, FlovatarDustCollectible, FlovatarDustCollectibleAccessory, FlovatarDustToken from 0xFlovatar

access(all) struct Preview {
    access(all) let fee: UFix64
    access(all) let feeRate: UFix64
    access(all) let creator: UFix64
    access(all) let creatorAddresses: [Address]
    access(all) let seller: UFix64
    init(fee: UFix64, feeRate: UFix64, creator: UFix64, creatorAddresses: [Address], seller: UFix64) {
        self.fee = fee
        self.feeRate = feeRate
        self.creator = creator
        self.creatorAddresses = creatorAddresses
        self.seller = seller
    }
}

access(all) fun main(address: Address, kind: String, id: UInt64, price: UFix64): Preview? {
    var publicPath: PublicPath? = nil
    if kind == "flovatar" { publicPath = Flovatar.CollectionPublicPath }
    else if kind == "flobit" { publicPath = FlovatarComponent.CollectionPublicPath }
    else if kind == "flobot" { publicPath = Flobot.CollectionPublicPath }
    else if kind == "collectible" { publicPath = FlovatarDustCollectible.CollectionPublicPath }
    else if kind == "accessory" { publicPath = FlovatarDustCollectibleAccessory.CollectionPublicPath }
    if publicPath == nil {
        return nil
    }
    let feeRate = Flovatar.getMarketplaceCut()
    let fee = price * feeRate
    var creatorShare = 0.0
    var creator = 0.0
    let creators: [Address] = []
    if let collection = getAccount(address).capabilities.borrow<&{NonFungibleToken.Collection}>(publicPath!) {
        if let nft = collection.borrowNFT(id) {
            if let royalties = nft.resolveView(Type<MetadataViews.Royalties>()) as! MetadataViews.Royalties? {
                for royalty in royalties.getRoyalties() {
                    let a = royalty.receiver.address
                    if a != 0xFlovatar && a != address && royalty.cut > 0.0 && creatorShare + royalty.cut <= 0.1 {
                        if getAccount(a).capabilities.get<&{FungibleToken.Receiver}>(FlovatarDustToken.VaultReceiverPath).check() {
                            creatorShare = creatorShare + royalty.cut
                            creator = creator + price * royalty.cut
                            creators.append(a)
                        }
                    }
                }
            }
        }
    }
    return Preview(fee: fee, feeRate: feeRate, creator: creator, creatorAddresses: creators, seller: price - fee - creator)
}
`,
            args: (arg, t) => [
                arg(address, t.Address),
                arg(kind, t.String),
                arg(''+id, t.UInt64),
                arg(floatArg(price), t.UFix64)
            ],
    });

}
