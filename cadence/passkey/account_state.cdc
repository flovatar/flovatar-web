// Read-only: what the passkey PoC page shows for an address.
import Flovatar, FlovatarComponent, FlovatarPack, FlovatarDustToken from 0xFlovatar
import FungibleToken from 0xFungibleToken

access(all) fun main(address: Address): {String: AnyStruct} {
    let account = getAccount(address)
    let initialized = account.capabilities.get<&Flovatar.Collection>(Flovatar.CollectionPublicPath).check()
        && account.capabilities.get<&FlovatarComponent.Collection>(FlovatarComponent.CollectionPublicPath).check()
        && account.capabilities.get<&FlovatarPack.Collection>(FlovatarPack.CollectionPublicPath).check()
        && account.capabilities.get<&FlovatarDustToken.Vault>(FlovatarDustToken.VaultReceiverPath).check()
    var components: [{String: AnyStruct}] = []
    for c in FlovatarComponent.getComponents(address: address) {
        components.append({"id": c.id, "templateId": c.templateId, "name": c.name, "category": c.category, "rarity": c.rarity})
    }
    var flovatars: [{String: AnyStruct}] = []
    for f in Flovatar.getFlovatars(address: address) {
        flovatars.append({"id": f.id, "name": f.name})
    }
    var dust: UFix64 = 0.0
    if let b = account.capabilities.borrow<&{FungibleToken.Balance}>(FlovatarDustToken.VaultBalancePath) {
        dust = b.balance
    }
    return {
        "initialized": initialized,
        "components": components,
        "flovatars": flovatars,
        "packs": FlovatarPack.getPacks(address: address) ?? [],
        "dust": dust,
        "flow": account.balance,
        "storageUsed": account.storage.used,
        "storageCapacity": account.storage.capacity
    }
}
