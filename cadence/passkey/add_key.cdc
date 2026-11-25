// User-signed (existing passkey): add another device's passkey as a full-weight key.
transaction(publicKey: String) {
    prepare(signer: auth(AddKey) &Account) {
        signer.keys.add(
            publicKey: PublicKey(publicKey: publicKey.decodeHex(), signatureAlgorithm: SignatureAlgorithm.ECDSA_P256),
            hashAlgorithm: HashAlgorithm.SHA2_256,
            weight: 1000.0
        )
    }
}
