import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"



// This script returns a specific Component Template

access(all) fun main(id: UInt64) : FlovatarComponentTemplate.ComponentTemplateData? {

    return FlovatarComponentTemplate.getComponentTemplate(id: id)

}
