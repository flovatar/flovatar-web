import * as fcl from "@onflow/fcl"

// EVM-A (2026-11): bridge state for the Flovatar-family collections + a wallet's COA + its Cadence ids.
// Returns {coa, fees:{onboard, base, estimate, max}, types:{<identifier>: {associated, custom, legacy, onboarded,
// owned:[ids] (Cadence ids of `owner`), paused}}}. Read-only.
export async function evmBridgeInfoScript(owner, identifiers) {
    return await fcl.query({
        cadence: `
import NonFungibleToken from 0xNonFungible
import ViewResolver from 0xNonFungible
import MetadataViews from 0xMetadataViews
import EVM from 0xEVM
import FlowEVMBridge from 0xFlowEVMBridge
import FlowEVMBridgeConfig from 0xFlowEVMBridge
import FlowEVMBridgeUtils from 0xFlowEVMBridge
import FlowEVMBridgeCustomAssociations from 0xFlowEVMBridge

access(all) fun main(owner: Address?, identifiers: [String]): {String: AnyStruct} {
    let out: {String: AnyStruct} = {}
    var coa: String? = nil
    if owner != nil {
        coa = getAccount(owner!).capabilities.borrow<&EVM.CadenceOwnedAccount>(/public/evm)?.address()?.toString()
    }
    out["coa"] = coa
    out["bridgeCOA"] = FlowEVMBridgeUtils.getBridgeCOAEVMAddress().toString()
    out["fees"] = {
        "onboard": FlowEVMBridgeConfig.onboardFee,
        "base": FlowEVMBridgeConfig.baseFee,
        "estimate": FlowEVMBridgeUtils.calculateBridgeFee(bytes: 20_000),
        "max": FlowEVMBridgeUtils.calculateBridgeFee(bytes: 400_000)
    }
    let types: {String: AnyStruct} = {}
    for id in identifiers {
        let t = CompositeType(id)
        if t == nil { continue }
        let assoc = FlowEVMBridgeConfig.getEVMAddressAssociated(with: t!)
        let custom = FlowEVMBridgeCustomAssociations.getEVMAddressAssociated(with: t!)
        var legacy: String? = nil
        if custom != nil {
            legacy = FlowEVMBridgeConfig.getLegacyEVMAddressForCustomCrossVMAddress(custom!)?.toString()
        }
        var owned: [UInt64] = []
        if owner != nil {
            let resolver = getAccount(t!.address!).contracts.borrow<&{ViewResolver}>(name: t!.contractName!)
            if let data = resolver?.resolveContractView(resourceType: t!, viewType: Type<MetadataViews.NFTCollectionData>()) as! MetadataViews.NFTCollectionData? {
                if let col = getAccount(owner!).capabilities.borrow<&{NonFungibleToken.Collection}>(data.publicPath) {
                    owned = col.getIDs()
                }
            }
        }
        types[id] = {
            "associated": assoc?.toString(),
            "custom": custom?.toString(),
            "legacy": legacy,
            "onboarded": FlowEVMBridge.typeRequiresOnboarding(t!) == false,
            "paused": FlowEVMBridgeConfig.isTypePaused(t!) ?? false,
            "owned": owned
        }
    }
    out["types"] = types
    return out
}
`,
        args: (arg, t) => [
            arg(owner, t.Optional(t.Address)),
            arg(identifiers, t.Array(t.String)),
        ],
    });
}
