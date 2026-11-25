// Sponsor-only: create a Flow account whose only key is the user's passkey (ECDSA_P256 / SHA2_256, weight 1000)
// and give it a little FLOW for storage. The sponsor keeps NO key on the new account.
import FungibleToken from 0xFungibleToken
import FlowToken from 0xFlowToken

transaction(publicKey: String, fund: UFix64) {
    prepare(sponsor: auth(BorrowValue) &Account) {
        let account = Account(payer: sponsor)
        account.keys.add(
            publicKey: PublicKey(publicKey: publicKey.decodeHex(), signatureAlgorithm: SignatureAlgorithm.ECDSA_P256),
            hashAlgorithm: HashAlgorithm.SHA2_256,
            weight: 1000.0
        )
        if fund > 0.0 {
            let vault = sponsor.storage.borrow<auth(FungibleToken.Withdraw) &FlowToken.Vault>(from: /storage/flowTokenVault)
                ?? panic("sponsor has no FLOW vault")
            let receiver = getAccount(account.address).capabilities.borrow<&{FungibleToken.Receiver}>(/public/flowTokenReceiver)
                ?? panic("new account has no FLOW receiver")
            receiver.deposit(from: <- vault.withdraw(amount: fund))
        }
    }
}
