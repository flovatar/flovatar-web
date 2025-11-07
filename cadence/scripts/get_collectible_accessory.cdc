import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"
import FlovatarDustCollectibleAccessory from "../contracts/FlovatarDustCollectibleAccessory.cdc"



// This script returns the Flovatar Collectible Accessory

access(all) fun main(address:Address, componentId: UInt64) : FlovatarDustCollectibleAccessory.CollectibleAccessoryData? {

    return FlovatarDustCollectibleAccessory.getAccessory(address: address, componentId: componentId)

}
