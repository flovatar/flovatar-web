import * as fcl from "@onflow/fcl"

/**
 * Big wallets: FlovatarComponent.getComponents(address) exceeds the script computation limit (100k) at ~2,000 Flobits
 * (mainnet wallet with 2,055 Flobits fails). Fallback: list the IDs (cheap), then read ComponentData in batches.
 */
export async function getComponentIdsScript(address) {
    return await fcl.query({
        cadence: `
import FlovatarComponent from 0xFlovatar

access(all) fun main(address: Address): [UInt64] {
    if let c = getAccount(address).capabilities.borrow<&FlovatarComponent.Collection>(FlovatarComponent.CollectionPublicPath) {
        return c.getIDs()
    }
    return []
}
`,
        args: (arg, t) => [arg(address, t.Address)],
    });
}

export async function getComponentsByIdsScript(address, ids) {
    return await fcl.query({
        cadence: `
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
        args: (arg, t) => [arg(address, t.Address), arg(ids.map(id => '' + id), t.Array(t.UInt64))],
    });
}

/** All components of a wallet in batches (4 scripts in flight); same shape as get-collections-component's result. */
export async function getCollectionsComponentBatched(address, batchSize = 400) {
    const ids = await getComponentIdsScript(address);
    const chunks = [];
    for (let i = 0; i < ids.length; i += batchSize) {
        chunks.push(ids.slice(i, i + batchSize));
    }
    const components = [];
    for (let i = 0; i < chunks.length; i += 4) {
        const parts = await Promise.all(chunks.slice(i, i + 4).map(ch => getComponentsByIdsScript(address, ch)));
        parts.forEach(p => components.push(...p));
    }
    return {address: address, flovatars: [], components: components, flovatarSales: [], componentSales: [], packs: []};
}
