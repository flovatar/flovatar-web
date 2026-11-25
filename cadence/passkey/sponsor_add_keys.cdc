// Sponsor-signed: add `count` more proposal keys to the sponsor, all with the sponsor's existing public key
// (same private key signs for every index; each index has its own sequence number -> parallel sponsor txs).
transaction(publicKey: String, count: Int) {
    prepare(signer: auth(AddKey) &Account) {
        var i = 0
        while i < count {
            signer.keys.add(
                publicKey: PublicKey(publicKey: publicKey.decodeHex(), signatureAlgorithm: SignatureAlgorithm.ECDSA_P256),
                hashAlgorithm: HashAlgorithm.SHA2_256,
                weight: 1000.0
            )
            i = i + 1
        }
    }
}
