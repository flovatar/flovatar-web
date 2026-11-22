import FungibleToken from "../contracts/core/FungibleToken.cdc"
import NonFungibleToken from "../contracts/core/NonFungibleToken.cdc"
import FlowToken from "../contracts/core/FlowToken.cdc"
import Flovatar from "../contracts/Flovatar.cdc"
import Flobot from "../contracts/Flobot.cdc"
import FlovatarComponent from "../contracts/FlovatarComponent.cdc"
import FlovatarComponentTemplate from "../contracts/FlovatarComponentTemplate.cdc"
import FlovatarPack from "../contracts/FlovatarPack.cdc"
import FlovatarMarketplace from "../contracts/FlovatarMarketplace.cdc"



// This script returns the available webshots

access(all) fun main(address:Address, flobotId: UInt64) : Flobot.FlobotData? {

    return Flobot.getFlobot(address: address, flobotId: flobotId)

}
