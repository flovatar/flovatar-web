import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"



// This script returns all the Flovatars for a specific address

access(all) fun main(address:Address) : [Flovatar.FlovatarData] {

    return Flovatar.getFlovatars(address: address)

}
