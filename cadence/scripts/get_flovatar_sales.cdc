import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"



// This script returns the available Flovatar sales

access(all) fun main(address:Address) : [FlovatarMarketplace.FlovatarSaleData] {

    return FlovatarMarketplace.getFlovatarSales(address: address)

}
