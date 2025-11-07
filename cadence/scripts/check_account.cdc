import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"


access(all) fun main(address: Address): Bool {

  let account = getAccount(address)

  let flovatarCap = account.capabilities.get<&Flovatar.Collection>(Flovatar.CollectionPublicPath)
  let flovatarComponentCap = account.capabilities.get<&FlovatarComponent.Collection>(FlovatarComponent.CollectionPublicPath)
  let marketplaceCap = account.capabilities.get<&FlovatarMarketplace.SaleCollection>(FlovatarMarketplace.CollectionPublicPath)
  let flovatarPackCap = account.capabilities.get<&FlovatarPack.Collection>(FlovatarPack.CollectionPublicPath)


  return (flovatarCap.check() && flovatarComponentCap.check() && marketplaceCap.check() && flovatarPackCap.check())
}
