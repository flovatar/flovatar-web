import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"


access(all) fun main(address: Address): Int {

  if let collection = getAccount(address).getCapability(Flovatar.CollectionPublicPath).borrow<&{Flovatar.CollectionPublic}>() {
      return collection.getIDs().length
  }

  return -1
}
