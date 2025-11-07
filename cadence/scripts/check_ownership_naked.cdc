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
      var count: Int = 0;
      let flovatarsData: [Flovatar.FlovatarData] = Flovatar.getFlovatars(address: address)
      for flovatar in flovatarsData {
        let str = flovatar.metadata.combination.slice(from: flovatar.metadata.combination.length - Int(4), upTo: flovatar.metadata.combination.length)
        if(str == "C121"){
          count = count + Int(1)
        }
      }
      return count
  }
  return -1
}
