/**
 * Asteroid Islands — read-only Cadence the browser runs (FCL queries against the site's access node).
 * Aliases: 0xIslands (island contract, set at runtime from window.ISLANDS.contract), 0xFlovatar, 0xNonFungible,
 * 0xMetadataViews (resources/js/bootstrap.js).
 */

/** Wallet inventory for the redeem flow: TerraKit land deeds (terraformable), FlovatarPack ids, island ids, keys. */
export const WALLET_CADENCE = `
import FlovatarComponent, FlovatarComponentTemplate, FlovatarPack from 0xFlovatar
import FlovatarAsteroidIsland from 0xIslands

access(all) struct Deed {
    access(all) let id: UInt64
    access(all) let templateId: UInt64
    access(all) let rarity: String
    access(all) let name: String
    init(id: UInt64, templateId: UInt64, rarity: String, name: String) {
        self.id = id; self.templateId = templateId; self.rarity = rarity; self.name = name
    }
}

access(all) struct Inventory {
    access(all) let address: Address
    access(all) let deeds: [Deed]
    access(all) let packs: [UInt64]
    access(all) let islands: [UInt64]
    access(all) let keys: [{String: AnyStruct}]
    access(all) let dapperReceiver: Bool
    access(all) let componentCollection: Bool
    access(all) let islandCollection: Bool
    access(all) let scanned: Int
    access(all) let componentTotal: Int
    init(address: Address, deeds: [Deed], packs: [UInt64], islands: [UInt64], keys: [{String: AnyStruct}], dapperReceiver: Bool,
         componentCollection: Bool, islandCollection: Bool, scanned: Int, componentTotal: Int) {
        self.address = address; self.deeds = deeds; self.packs = packs; self.islands = islands; self.keys = keys
        self.dapperReceiver = dapperReceiver; self.componentCollection = componentCollection
        self.islandCollection = islandCollection; self.scanned = scanned; self.componentTotal = componentTotal
    }
}

access(all) fun main(address: Address, start: Int, size: Int): Inventory {
    let acct = getAccount(address)
    let category = FlovatarAsteroidIsland.setting("deedCategory")
    let series = UInt32.fromString(FlovatarAsteroidIsland.setting("deedSeries")) ?? 0
    let deeds: [Deed] = []
    let skip: {UInt64: Bool} = {}
    let keep: {UInt64: FlovatarComponentTemplate.ComponentTemplateData} = {}
    var componentCollection = false
    var total = 0
    var scanned = 0
    if let col = acct.capabilities.borrow<&{FlovatarComponent.CollectionPublic}>(FlovatarComponent.CollectionPublicPath) {
        componentCollection = true
        let ids = col.getIDs()
        total = ids.length
        var i = start
        while i < ids.length && i < start + size {
            let id = ids[i]
            i = i + 1
            scanned = scanned + 1
            if let c = col.borrowComponent(id: id) {
                if skip[c.templateId] == true { continue }
                if keep[c.templateId] == nil {
                    if let t = FlovatarComponentTemplate.getComponentTemplate(id: c.templateId) {
                        if t.category == category && t.series == series { keep[c.templateId] = t } else { skip[c.templateId] = true; continue }
                    } else { skip[c.templateId] = true; continue }
                }
                if FlovatarComponentTemplate.isRestricted(nftType: Type<@FlovatarComponent.NFT>(), id: id, owner: address) { continue }
                let t = keep[c.templateId]!
                deeds.append(Deed(id: id, templateId: c.templateId, rarity: t.rarity, name: t.name))
            }
        }
    }
    let keys: [{String: AnyStruct}] = []
    if start == 0 {
        acct.keys.forEach(fun (k: AccountKey): Bool {
            keys.append({"index": k.keyIndex, "signAlgo": k.publicKey.signatureAlgorithm.rawValue, "hashAlgo": k.hashAlgorithm.rawValue,
                "weight": k.weight, "revoked": k.isRevoked})
            return true
        })
    }
    var islands: [UInt64] = []
    var islandCollection = false
    if let ic = acct.capabilities.borrow<&{FlovatarAsteroidIsland.CollectionPublic}>(FlovatarAsteroidIsland.CollectionPublicPath) {
        islandCollection = true
        islands = ic.getIDs()
    }
    return Inventory(
        address: address, deeds: deeds, packs: start == 0 ? (FlovatarPack.getPacks(address: address) ?? []) : [],
        islands: islands, keys: keys,
        dapperReceiver: acct.capabilities.exists(/public/dapperUtilityCoinReceiver) || acct.capabilities.exists(/public/flowUtilityTokenReceiver),
        componentCollection: componentCollection, islandCollection: islandCollection, scanned: scanned, componentTotal: total)
}
`;

/** Contract-side validation + composition of a scene (no mint): same as scripts/preview_svg.cdc. */
export const PREVIEW_CADENCE = `
import FlovatarAsteroidIsland from 0xIslands
access(all) fun main(rarity: String, layers: {String: UInt64}, tint: String?): String {
    FlovatarAsteroidIsland.validateScene(rarity: rarity, layers: layers, tint: tint)
    return FlovatarAsteroidIsland.composeSvg(layers: layers, tint: tint, islandId: nil)
}
`;

/** Live resident validation (contract checkResidents) for one island. */
export const RESIDENTS_CADENCE = `
import FlovatarAsteroidIsland from 0xIslands
access(all) fun main(address: Address, islandId: UInt64): [{String: AnyStruct}]? {
    if let island = FlovatarAsteroidIsland.borrowIsland(address: address, id: islandId) {
        let out: [{String: AnyStruct}] = []
        for s in island.checkResidents() {
            out.append({"slot": s.slot, "nftType": s.nftType, "nftId": s.nftId, "setBy": s.setBy, "valid": s.valid, "reason": s.reason})
        }
        return out
    }
    return nil
}
`;

/**
 * Every NFT the account holds, any project (stored collections), with its MetadataViews.Display — the resident picker.
 * Collections are read in slices of \`size\` ids per collection (\`start\` offset) to stay under the script limits.
 */
export const WALLET_NFTS_CADENCE = `
import NonFungibleToken from 0xNonFungible
import MetadataViews from 0xMetadataViews
import ViewResolver from 0xNonFungible

access(all) fun main(address: Address, start: Int, size: Int, skipTypes: [String]): [{String: AnyStruct}] {
    let acct = getAuthAccount<auth(BorrowValue) &Account>(address)
    let out: [{String: AnyStruct}] = []
    let colType = Type<@{NonFungibleToken.Collection}>()
    acct.storage.forEachStored(fun (path: StoragePath, type: Type): Bool {
        if !type.isSubtype(of: colType) { return true }
        if let col = acct.storage.borrow<&{NonFungibleToken.Collection}>(from: path) {
            let ids = col.getIDs()
            var i = start
            while i < ids.length && i < start + size {
                let id = ids[i]
                i = i + 1
                if let nft = col.borrowNFT(id) {
                    let t = nft.getType().identifier
                    if skipTypes.contains(t) { continue }
                    var name = ""
                    var thumb = ""
                    if let d = nft.resolveView(Type<MetadataViews.Display>()) as? MetadataViews.Display {
                        name = d.name
                        thumb = d.thumbnail.uri()
                    }
                    out.append({"type": t, "id": id, "name": name, "thumbnail": thumb, "total": ids.length, "path": path.toString()})
                }
            }
        }
        return true
    })
    return out
}
`;

/** Display (name + thumbnail) of residents held by \`owner\` (resolved like the contract's checkOwnership). */
export const DISPLAYS_CADENCE = `
import NonFungibleToken from 0xNonFungible
import MetadataViews from 0xMetadataViews
import ViewResolver from 0xNonFungible

access(all) fun main(owner: Address, types: [String], ids: [UInt64]): [{String: String}] {
    let out: [{String: String}] = []
    var i = 0
    while i < types.length {
        let typeId = types[i]
        let id = ids[i]
        i = i + 1
        var name = ""
        var thumb = ""
        var owned = "no"
        let parts = typeId.split(separator: ".")
        if parts.length >= 4 && parts[0] == "A" {
            if let addr = Address.fromString("0x".concat(parts[1])) {
                if let resolver = getAccount(addr).contracts.borrow<&{ViewResolver}>(name: parts[2]) {
                    if let nftType = CompositeType(typeId) {
                        if let data = resolver.resolveContractView(resourceType: nftType, viewType: Type<MetadataViews.NFTCollectionData>()) as? MetadataViews.NFTCollectionData {
                            if let col = getAccount(owner).capabilities.borrow<&{NonFungibleToken.CollectionPublic}>(data.publicPath) {
                                if let nft = col.borrowNFT(id) {
                                    if nft.getType().identifier == typeId {
                                        owned = "yes"
                                        if let d = nft.resolveView(Type<MetadataViews.Display>()) as? MetadataViews.Display {
                                            name = d.name
                                            thumb = d.thumbnail.uri()
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        out.append({"type": typeId, "id": id.toString(), "name": name, "thumbnail": thumb, "owned": owned})
    }
    return out
}
`;

/** Pre-flight: does \`owner\` hold each NFT the way the contract checks it (setResident would accept it)? */
export const OWNERSHIP_CADENCE = `
import FlovatarAsteroidIsland from 0xIslands
access(all) fun main(owner: Address, types: [String], ids: [UInt64]): [Bool] {
    let out: [Bool] = []
    var i = 0
    while i < types.length {
        if let t = CompositeType(types[i]) {
            out.append(FlovatarAsteroidIsland.checkOwnership(owner: owner, nftType: t, nftId: ids[i], collectionPath: nil))
        } else { out.append(false) }
        i = i + 1
    }
    return out
}
`;
