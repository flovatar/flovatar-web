import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"
import FlovatarDustCollectible from "../contracts/FlovatarDustCollectible.cdc"



// This script returns the Flovatar Collectible

access(all) fun main(address:Address, collectibleId: UInt64) : FlovatarDustCollectible.FlovatarDustCollectibleData? {

    return FlovatarDustCollectible.getCollectible(address: address, collectibleId: collectibleId)

}
