import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"
import FlovatarDustCollectibleTemplate from "../contracts/FlovatarDustCollectibleTemplate.cdc"



// This script returns a specific Collectible Series

access(all) fun main(id: UInt64) : FlovatarDustCollectibleTemplate.CollectibleSeriesData? {

    return FlovatarDustCollectibleTemplate.getCollectibleSeries(id: id)

}
