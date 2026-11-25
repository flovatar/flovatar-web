// Read-only: FCL account-proof of the wallet a passkey account is being linked to (FCLCrypto, domain tag added there).
import FCLCrypto from 0xFCLCrypto

access(all) fun main(address: Address, message: String, keyIndices: [UInt64], signatures: [String]): Bool {
    let indices: [Int] = []
    for keyIndex in keyIndices {
        indices.append(Int(keyIndex))
    }
    return FCLCrypto.verifyAccountProofSignatures(address: address, message: message, keyIndices: indices, signatures: signatures)
}
