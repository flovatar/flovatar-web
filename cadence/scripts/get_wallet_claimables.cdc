import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"
import FlovatarInbox from "../contracts/FlovatarInbox.cdc"

pub struct Claimables {

  pub(set) var address: Address
  pub(set) var components: [UInt64]
  pub(set) var dust: UFix64
  init (_ address:Address) {
    self.address = address
    self.components = []
    self.dust = 0.0
  }
}

access(all) fun main(address:Address) : Claimables {
    // get the accounts' public address objects
    let status = Claimables(address)
    let account = getAccount(address)

    status.components = FlovatarInbox.getWalletComponentIDs(address: address)
    status.dust = FlovatarInbox.getWalletDustBalance(address: address)

    return status
}
