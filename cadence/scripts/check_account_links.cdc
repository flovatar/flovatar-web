import FungibleToken from "../contracts/FungibleToken.cdc"
import NonFungibleToken from "../contracts/NonFungibleToken.cdc"
import FlowToken from "../contracts/FlowToken.cdc"


access(all) fun main(address: Address): Bool {

    return getAccount(address).getCapability<&{FungibleToken.Provider}>(/public/flowTokenReceiver).check() || getAccount(address).getCapability<&{FungibleToken.Provider}>(/public/fusdReceiver).check()


  //if let c = getAccount(address).getCapability<&{FungibleToken.Provider}>(/public/flowTokenReceiver).borrow() {
  //       return "LINK PROBLEM!"
  //  }

  //return "link ok"
}
