import FungibleToken from "FungibleToken"
import NonFungibleToken from "NonFungibleToken"
import FlowToken from "FlowToken"
import Flovatar from "Flovatar"
import FlovatarComponent from "FlovatarComponent"
import FlovatarComponentTemplate from "FlovatarComponentTemplate"
import FlovatarPack from "FlovatarPack"
import FlovatarMarketplace from "FlovatarMarketplace"
import FlovatarDustToken from "../../contracts/FlovatarDustToken.cdc"
import FlovatarInbox from "../../contracts/FlovatarInbox.cdc"

//This transactions transfers flow on testnet from one account to another
transaction() {


    let flovatarAdmin: &Flovatar.Admin

      prepare(signer: AuthAccount) {

        self.flovatarAdmin = signer.borrow<&FlovatarInbox.Admin>(from: /storage/FlovatarInboxAdmin)!
      }

      execute {
        self.flovatarAdmin.setWithdrawEnable(enabled: self.enabled)
      }
}
