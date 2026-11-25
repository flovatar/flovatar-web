/**
 * TerraKit wallet check — the one Cadence script the page runs (read-only, FCL query against the site's access node).
 * Shared with tools/terrakit-check/verify.mjs (mainnet/testnet verification) so both run the same Cadence.
 *
 * Returns, for one address: account keys (index, algorithms, weight, revoked), whether a Dapper (DUC/FUT) receiver is
 * published, voucher counts per template (only templates whose category is in `categories`; loose + listed on the
 * legacy FlovatarMarketplace) and every FlovatarPack id (the page keeps the TerraKit range). Template category/rarity are
 * read by reference from the template collection on `templateHost` (no SVG copies), once per template.
 * `0xFlovatar` / `0xFungible` / `0xMarketplace` are FCL address aliases (resources/js/bootstrap.js).
 */
export const TERRAKIT_CHECK_CADENCE = `
import FlovatarComponent, FlovatarComponentTemplate, FlovatarPack, FlovatarMarketplace from 0xFlovatar

access(all) struct Key {
    access(all) let index: Int
    access(all) let signAlgo: UInt8
    access(all) let hashAlgo: UInt8
    access(all) let weight: UFix64
    access(all) let revoked: Bool
    init(_ k: AccountKey) {
        self.index = k.keyIndex
        self.signAlgo = k.publicKey.signatureAlgorithm.rawValue
        self.hashAlgo = k.hashAlgorithm.rawValue
        self.weight = k.weight
        self.revoked = k.isRevoked
    }
}

access(all) struct Voucher {
    access(all) let templateId: UInt64
    access(all) let category: String
    access(all) let rarity: String
    access(all) let name: String
    access(all) var count: Int
    access(all) var listed: Int
    init(templateId: UInt64, category: String, rarity: String, name: String) {
        self.templateId = templateId
        self.category = category
        self.rarity = rarity
        self.name = name
        self.count = 0
        self.listed = 0
    }
    access(all) fun add(listed: Bool) {
        self.count = self.count + 1
        if listed { self.listed = self.listed + 1 }
    }
}

access(all) struct Check {
    access(all) let address: Address
    access(all) let exists: Bool
    access(all) let keys: [Key]
    access(all) let dapperReceiver: Bool
    access(all) let componentCollection: Bool
    access(all) let packCollection: Bool
    access(all) let vouchers: [Voucher]
    access(all) let packs: [UInt64]
    access(all) let componentTotal: Int
    init(address: Address, exists: Bool, keys: [Key], dapperReceiver: Bool, componentCollection: Bool, packCollection: Bool, vouchers: [Voucher], packs: [UInt64], componentTotal: Int) {
        self.address = address
        self.exists = exists
        self.keys = keys
        self.dapperReceiver = dapperReceiver
        self.componentCollection = componentCollection
        self.packCollection = packCollection
        self.vouchers = vouchers
        self.packs = packs
        self.componentTotal = componentTotal
    }
}

access(all) fun main(address: Address, templateHost: Address, categories: [String], start: Int, size: Int): Check {
    let acct = getAccount(address)
    let keys: [Key] = []
    acct.keys.forEach(fun (k: AccountKey): Bool {
        keys.append(Key(k))
        return true
    })

    let templates = getAccount(templateHost).capabilities.borrow<&FlovatarComponentTemplate.Collection>(FlovatarComponentTemplate.CollectionPublicPath)
    let skip: {UInt64: Bool} = {}
    let found: {UInt64: Voucher} = {}

    // template id -> keep? (cached; reads category/rarity/name by reference)
    fun keep(_ templateId: UInt64): Bool {
        if skip[templateId] != nil { return false }
        if found[templateId] != nil { return true }
        if let t = templates?.borrowComponentTemplate(id: templateId) ?? nil {
            if categories.contains(t.category) {
                found[templateId] = Voucher(templateId: templateId, category: t.category, rarity: t.rarity, name: t.name)
                return true
            }
        }
        skip[templateId] = true
        return false
    }

    // Flobits are scanned in slices [start, start + size) so very large collections stay under the script
    // computation limit (the page calls again while start + size < componentTotal). Listings are read with slice 0.
    var componentCollection = false
    var componentTotal = 0
    if let col = acct.capabilities.borrow<&{FlovatarComponent.CollectionPublic}>(FlovatarComponent.CollectionPublicPath) {
        componentCollection = true
        let ids = col.getIDs()
        componentTotal = ids.length
        var i = start
        while i < ids.length && i < start + size {
            if let c = col.borrowComponent(id: ids[i]) {
                if keep(c.templateId) { found[c.templateId]!.add(listed: false) }
            }
            i = i + 1
        }
    }
    if start > 0 {
        return Check(address: address, exists: true, keys: keys, dapperReceiver: false, componentCollection: componentCollection,
            packCollection: false, vouchers: found.values, packs: [], componentTotal: componentTotal)
    }
    if let sale = acct.capabilities.borrow<&FlovatarMarketplace.SaleCollection>(FlovatarMarketplace.CollectionPublicPath) {
        for id in sale.getFlovatarComponentIDs() {
            if let c = sale.getFlovatarComponent(tokenId: id) {
                if keep(c.templateId) { found[c.templateId]!.add(listed: true) }
            }
        }
    }

    let packs = FlovatarPack.getPacks(address: address)

    return Check(
        address: address,
        exists: acct.storage.used > 0 || keys.length > 0,
        keys: keys,
        dapperReceiver: acct.capabilities.exists(/public/dapperUtilityCoinReceiver) || acct.capabilities.exists(/public/flowUtilityTokenReceiver),
        componentCollection: componentCollection,
        packCollection: packs != nil,
        vouchers: found.values,
        packs: packs ?? [],
        componentTotal: componentTotal
    )
}
`;
