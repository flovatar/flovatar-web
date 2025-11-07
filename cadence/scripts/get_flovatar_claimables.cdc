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

  pub(set) var flovatarComponents: [UInt64]
  pub(set) var walletComponents: [UInt64]
  pub(set) var flovatarDust: UFix64
  pub(set) var walletDust: UFix64
  pub(set) var flovatarCommunityDust: UFix64
  init () {
    self.flovatarComponents = []
    self.walletComponents = []
    self.flovatarDust = 0.0
    self.walletDust = 0.0
    self.flovatarCommunityDust = 0.0
  }
}

access(all) fun main(id: UInt64, address: Address) : Claimables {
    // get the accounts' public address objects
    let status = Claimables()

    status.flovatarDust = FlovatarInbox.getFlovatarDustBalance(id: id)

    if let claimableCommunityDust = FlovatarInbox.getClaimableFlovatarCommunityDust(id: id, address: address) {
        status.flovatarCommunityDust = claimableCommunityDust.amount
    }
    status.flovatarComponents = status.flovatarComponents.concat(FlovatarInbox.getFlovatarComponentIDs(id: id))

    return status
}
