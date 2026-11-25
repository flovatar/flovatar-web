import * as fcl from "@onflow/fcl"

/**
 * Big wallets: the whole-collection scripts (Flovatar.getFlovatars, FlovatarComponent.getComponents, ...) read every NFT
 * in ONE script and hit the access node's computation limit on very large wallets (measured on mainnet, 2026-11-24:
 * ~800 Flovatars, ~2,000 Flobits, ~2,500 DUST collectibles per script). Fallback used by actions.js when a whole
 * script fails: list the IDs (cheap), then read the items in batches (BATCH[kind] per script, IN_FLIGHT scripts in
 * parallel). Every result has the same shape as the matching get-collections-*.script.js result, so the collection
 * page's loaders don't change. Add `?batched=1` to a collection URL to force this path (testing).
 *
 * Flovatar/sale metadata.svg is returned empty (as Flovatar.getFlovatars does): list views use images, not the SVG.
 */

export const BATCH = {flovatar: 100, flobot: 200, collectible: 200, component: 400, flovatarSale: 100, componentSale: 400};
const IN_FLIGHT = 4;

const CADENCE = {
    findName: `
import FIND from 0xFind

access(all) fun main(name: String): Address? {
    return FIND.lookupAddress(name)
}
`,
    flovatarIds: `
import Flovatar from 0xFlovatar

access(all) fun main(address: Address): [UInt64] {
    if let c = getAccount(address).capabilities.borrow<&Flovatar.Collection>(Flovatar.CollectionPublicPath) {
        return c.getIDs()
    }
    return []
}
`,
    flovatar: `
import Flovatar from 0xFlovatar

access(all) fun main(address: Address, ids: [UInt64]): [Flovatar.FlovatarData] {
    var out: [Flovatar.FlovatarData] = []
    if let c = getAccount(address).capabilities.borrow<&Flovatar.Collection>(Flovatar.CollectionPublicPath) {
        for id in ids {
            if let f = c.borrowFlovatar(id: id) {
                let m = f.getMetadata()
                let meta = Flovatar.Metadata(mint: m.mint, series: m.series, svg: "", combination: m.combination, creatorAddress: m.creatorAddress, components: m.getComponents(), rareCount: m.rareCount, epicCount: m.epicCount, legendaryCount: m.legendaryCount)
                out.append(Flovatar.FlovatarData(id: id, name: f.getName(), metadata: meta, accessoryId: f.getAccessory(), hatId: f.getHat(), eyeglassesId: f.getEyeglasses(), backgroundId: f.getBackground(), bio: f.getBio()))
            }
        }
    }
    return out
}
`,
    flobotIds: `
import Flobot from 0xFlovatar

access(all) fun main(address: Address): [UInt64] {
    if let c = getAccount(address).capabilities.borrow<&Flobot.Collection>(Flobot.CollectionPublicPath) {
        return c.getIDs()
    }
    return []
}
`,
    flobot: `
import Flobot from 0xFlovatar

access(all) fun main(address: Address, ids: [UInt64]): [Flobot.FlobotData] {
    var out: [Flobot.FlobotData] = []
    if let c = getAccount(address).capabilities.borrow<&Flobot.Collection>(Flobot.CollectionPublicPath) {
        for id in ids {
            if let f = c.borrowFlobot(id: id) {
                out.append(Flobot.FlobotData(id: id, name: f.getName(), metadata: f.getMetadata(), backgroundId: f.getBackground(), bio: f.getBio()))
            }
        }
    }
    return out
}
`,
    collectibleIds: `
import FlovatarDustCollectible from 0xFlovatar

access(all) fun main(address: Address): [UInt64] {
    if let c = getAccount(address).capabilities.borrow<&FlovatarDustCollectible.Collection>(FlovatarDustCollectible.CollectionPublicPath) {
        return c.getIDs()
    }
    return []
}
`,
    collectible: `
import FlovatarDustCollectible from 0xFlovatar

access(all) fun main(address: Address, ids: [UInt64]): [FlovatarDustCollectible.FlovatarDustCollectibleData] {
    var out: [FlovatarDustCollectible.FlovatarDustCollectibleData] = []
    if let c = getAccount(address).capabilities.borrow<&FlovatarDustCollectible.Collection>(FlovatarDustCollectible.CollectionPublicPath) {
        for id in ids {
            if let x = c.borrowDustCollectible(id: id) {
                out.append(FlovatarDustCollectible.FlovatarDustCollectibleData(id: id, mint: x.mint, series: x.series, name: x.getName(), svg: nil, combination: x.combination, creatorAddress: x.creatorAddress, layers: x.getLayers(), bio: x.getBio(), metadata: x.getMetadata()))
            }
        }
    }
    return out
}
`,
    componentIds: `
import FlovatarComponent from 0xFlovatar

access(all) fun main(address: Address): [UInt64] {
    if let c = getAccount(address).capabilities.borrow<&FlovatarComponent.Collection>(FlovatarComponent.CollectionPublicPath) {
        return c.getIDs()
    }
    return []
}
`,
    component: `
import FlovatarComponent from 0xFlovatar

access(all) fun main(address: Address, ids: [UInt64]): [FlovatarComponent.ComponentData] {
    var out: [FlovatarComponent.ComponentData] = []
    if let c = getAccount(address).capabilities.borrow<&FlovatarComponent.Collection>(FlovatarComponent.CollectionPublicPath) {
        for id in ids {
            if let component = c.borrowComponent(id: id) {
                out.append(FlovatarComponent.ComponentData(id: id, templateId: component.templateId, mint: component.mint))
            }
        }
    }
    return out
}
`,
    flovatarSaleIds: `
import FlovatarMarketplace from 0xFlovatar

access(all) fun main(address: Address): [UInt64] {
    if let s = getAccount(address).capabilities.borrow<&FlovatarMarketplace.SaleCollection>(FlovatarMarketplace.CollectionPublicPath) {
        return s.getFlovatarIDs()
    }
    return []
}
`,
    flovatarSale: `
import Flovatar, FlovatarMarketplace from 0xFlovatar

access(all) fun main(address: Address, ids: [UInt64]): [FlovatarMarketplace.FlovatarSaleData] {
    var out: [FlovatarMarketplace.FlovatarSaleData] = []
    if let s = getAccount(address).capabilities.borrow<&FlovatarMarketplace.SaleCollection>(FlovatarMarketplace.CollectionPublicPath) {
        for id in ids {
            if let price = s.getFlovatarPrice(tokenId: id) {
                if let f = s.getFlovatar(tokenId: id) {
                    let m = f.getMetadata()
                    let meta = Flovatar.Metadata(mint: m.mint, series: m.series, svg: "", combination: m.combination, creatorAddress: m.creatorAddress, components: m.getComponents(), rareCount: m.rareCount, epicCount: m.epicCount, legendaryCount: m.legendaryCount)
                    out.append(FlovatarMarketplace.FlovatarSaleData(id: id, price: price, metadata: meta, accessoryId: f.getAccessory(), hatId: f.getHat(), eyeglassesId: f.getEyeglasses(), backgroundId: f.getBackground()))
                }
            }
        }
    }
    return out
}
`,
    componentSaleIds: `
import FlovatarMarketplace from 0xFlovatar

access(all) fun main(address: Address): [UInt64] {
    if let s = getAccount(address).capabilities.borrow<&FlovatarMarketplace.SaleCollection>(FlovatarMarketplace.CollectionPublicPath) {
        return s.getFlovatarComponentIDs()
    }
    return []
}
`,
    componentSale: `
import FlovatarComponent, FlovatarMarketplace from 0xFlovatar

access(all) fun main(address: Address, ids: [UInt64]): [FlovatarMarketplace.FlovatarComponentSaleData] {
    var out: [FlovatarMarketplace.FlovatarComponentSaleData] = []
    if let s = getAccount(address).capabilities.borrow<&FlovatarMarketplace.SaleCollection>(FlovatarMarketplace.CollectionPublicPath) {
        for id in ids {
            if let price = s.getFlovatarComponentPrice(tokenId: id) {
                if let c = s.getFlovatarComponent(tokenId: id) {
                    out.append(FlovatarMarketplace.FlovatarComponentSaleData(id: id, price: price, metadata: FlovatarComponent.ComponentData(id: id, templateId: c.templateId, mint: c.mint)))
                }
            }
        }
    }
    return out
}
`,
    packs: `
import FlovatarPack from 0xFlovatar

access(all) fun main(address: Address): [UInt64] {
    return FlovatarPack.getPacks(address: address) ?? []
}
`,
};

/** Result key of each kind in the get-collections-* shape. */
const RESULT_KEY = {flovatar: 'flovatars', flobot: 'flobots', collectible: 'collectibles', component: 'components',
    flovatarSale: 'flovatarSales', componentSale: 'componentSales'};

function emptyResult(address) {
    return {address: address, flovatars: [], components: [], flovatarSales: [], componentSales: [], packs: [],
        flobots: [], collectibles: []};
}

export function forceBatched() {
    try {
        return new URLSearchParams(window.location.search).has('batched');
    } catch (e) {
        return false;
    }
}

export async function resolveFindName(name) {
    return await fcl.query({cadence: CADENCE.findName, args: (arg, t) => [arg(name, t.String)]});
}

export async function getIds(kind, address) {
    return await fcl.query({cadence: CADENCE[kind + 'Ids'], args: (arg, t) => [arg(address, t.Address)]});
}

export async function getByIds(kind, address, ids) {
    return await fcl.query({
        cadence: CADENCE[kind],
        args: (arg, t) => [arg(address, t.Address), arg(ids.map(id => '' + id), t.Array(t.UInt64))],
    });
}

/** All items of one kind for a wallet, in ID batches; resolves to the get-collections-* result shape. */
export async function getCollectionBatched(kind, address) {
    const ids = await getIds(kind, address);
    const size = BATCH[kind];
    const chunks = [];
    for (let i = 0; i < ids.length; i += size) {
        chunks.push(ids.slice(i, i + size));
    }
    const items = [];
    for (let i = 0; i < chunks.length; i += IN_FLIGHT) {
        const parts = await Promise.all(chunks.slice(i, i + IN_FLIGHT).map(ch => getByIds(kind, address, ch)));
        parts.forEach(p => items.push(...p));
    }
    const out = emptyResult(address);
    out[RESULT_KEY[kind]] = items;
    return out;
}

/** Packs are a plain ID list (cheap even for the 3,136 packs of the mainnet admin wallet). */
export async function getPacksList(address) {
    const out = emptyResult(address);
    out.packs = await fcl.query({cadence: CADENCE.packs, args: (arg, t) => [arg(address, t.Address)]});
    return out;
}

/** Same for a FIND name (by-name collection URLs); null when the name doesn't resolve (as the by-name scripts). */
export async function getCollectionBatchedByName(kind, name) {
    const address = await resolveFindName(name);
    if (!address) {
        return null;
    }
    return kind === 'pack' ? getPacksList(address) : getCollectionBatched(kind, address);
}
