// No-op transaction signed only by the passkey sponsor (passkey:kms-check --send, testnet): proves the KMS signer +
// proposal-key pool end to end. Changes nothing.
transaction {
    prepare(sponsor: &Account) {
        log("flovatar kms-check")
    }
}
