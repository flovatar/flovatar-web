// Sponsor-only: top up a passkey account's FLOW balance so it has storage headroom (e.g. before minting a Flovatar).
import FungibleToken from 0xFungibleToken
import FlowToken from 0xFlowToken

transaction(to: Address, amount: UFix64) {
    let payment: @{FungibleToken.Vault}
    let receiver: &{FungibleToken.Receiver}

    prepare(sponsor: auth(BorrowValue) &Account) {
        let vault = sponsor.storage.borrow<auth(FungibleToken.Withdraw) &FlowToken.Vault>(from: /storage/flowTokenVault)
            ?? panic("sponsor has no FLOW vault")
        self.payment <- vault.withdraw(amount: amount)
        self.receiver = getAccount(to).capabilities.borrow<&{FungibleToken.Receiver}>(/public/flowTokenReceiver)
            ?? panic("recipient has no FLOW receiver")
    }

    execute {
        self.receiver.deposit(from: <- self.payment)
    }
}
