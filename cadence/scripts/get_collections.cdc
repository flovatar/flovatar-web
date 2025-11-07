import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"

pub struct Collections {

  pub(set) var address: Address
  pub(set) var flovatars: [Flovatar.FlovatarData]
  pub(set) var components: [FlovatarComponent.ComponentData]
  pub(set) var packs: [UInt64]
  init (_ address:Address) {
    self.address = address
    self.flovatars = []
    self.components = []
    self.packs = []
  }
}

access(all) fun main(address:Address) : Collections {
    // get the accounts' public address objects
    let account = getAccount(address)
    let status = Collections(address)

    status.flovatars = Flovatar.getFlovatars(address: address)
    status.components = FlovatarComponent.getComponents(address: address)
    status.packs = FlovatarPack.getPacks(address: address)!

    return status
}
