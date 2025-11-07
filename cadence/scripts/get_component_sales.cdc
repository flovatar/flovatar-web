import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"



// This script returns the available Flovatar Component sales

access(all) fun main(address:Address) : [FlovatarMarketplace.FlovatarComponentSaleData] {

    return FlovatarMarketplace.getFlovatarComponentSales(address: address)

}
