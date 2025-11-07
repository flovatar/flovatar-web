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
transaction(
    amount: [UFix64],
    to: [UInt64]) {



      prepare(signer: AuthAccount) {
        if let inboxCollection = signer.getCapability(FlovatarInbox.CollectionPublicPath).borrow<&{FlovatarInbox.CollectionPublic}>() {
            let vaultRef = signer.borrow<&{FungibleToken.Provider}>(from: FlovatarDustToken.VaultStoragePath)
                      ?? panic("Could not borrow reference to the owner's Vault!")

            var i: UInt32 = 0

            while i < UInt32(amount.length) {
                inboxCollection.depositDustToFlovatar(id: to[i], vault: <- vaultRef.withdraw(amount: amount[i]))

                i = i + UInt32(1)
            }


        }
      }

      execute {

      }
}
