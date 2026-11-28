import * as fcl from "./fcl-tx"

// EVM-A (2026-11): "Bridge to Flow EVM" from an item page. Same Cadence as
// flovatar-kb/cadence/evm-a/transactions/user/bridge_to_evm.cdc (tested on emulator + testnet), FCL aliases.
// Creates the signer's Cadence-Owned Account (COA = their Flow EVM address) when missing, onboards the collection to
// the VM bridge if nobody did yet (1 FLOW, once per collection), escrows the NFT in Cadence and mints/releases the
// ERC-721 to the COA. Fees in FLOW from the signer's vault, capped by a scoped provider.
export async function evmBridgeToTx(nftIdentifier, ids) {
    return await fcl.mutate({
        cadence: `
import FungibleToken from 0xFungible
import NonFungibleToken from 0xNonFungible
import ViewResolver from 0xNonFungible
import MetadataViews from 0xMetadataViews
import FlowToken from 0xFlowToken
import ScopedFTProviders from 0xFlowEVMBridge
import EVM from 0xEVM
import FlowEVMBridge from 0xFlowEVMBridge
import FlowEVMBridgeConfig from 0xFlowEVMBridge
import FlowEVMBridgeUtils from 0xFlowEVMBridge

transaction(nftIdentifier: String, ids: [UInt64]) {
    let collection: auth(NonFungibleToken.Withdraw) &{NonFungibleToken.Collection}
    let coa: auth(EVM.Bridge) &EVM.CadenceOwnedAccount
    let nftType: Type
    let requiresOnboarding: Bool
    let scopedProvider: @ScopedFTProviders.ScopedFTProvider

    prepare(signer: auth(CopyValue, BorrowValue, IssueStorageCapabilityController, PublishCapability, SaveValue, UnpublishCapability) &Account) {
        assert(ids.length > 0 && ids.length <= 20, message: "Bridge 1 to 20 items per transaction")
        if signer.storage.type(at: /storage/evm) == nil {
            signer.storage.save(<-EVM.createCadenceOwnedAccount(), to: /storage/evm)
            let cap = signer.capabilities.storage.issue<&EVM.CadenceOwnedAccount>(/storage/evm)
            signer.capabilities.unpublish(/public/evm)
            signer.capabilities.publish(cap, at: /public/evm)
        }
        self.coa = signer.storage.borrow<auth(EVM.Bridge) &EVM.CadenceOwnedAccount>(from: /storage/evm)
            ?? panic("No Cadence-Owned Account (COA) at /storage/evm")

        self.nftType = CompositeType(nftIdentifier) ?? panic("Bad NFT type identifier ".concat(nftIdentifier))
        let contractAddress = FlowEVMBridgeUtils.getContractAddress(fromType: self.nftType)!
        let contractName = FlowEVMBridgeUtils.getContractName(fromType: self.nftType)!
        let resolver = getAccount(contractAddress).contracts.borrow<&{ViewResolver}>(name: contractName)
            ?? panic("NFT contract is not a ViewResolver")
        let data = resolver.resolveContractView(resourceType: self.nftType, viewType: Type<MetadataViews.NFTCollectionData>()) as! MetadataViews.NFTCollectionData?
            ?? panic("No NFTCollectionData view")
        self.collection = signer.storage.borrow<auth(NonFungibleToken.Withdraw) &{NonFungibleToken.Collection}>(from: data.storagePath)
            ?? panic("No collection in the signer's storage")

        var approxFee = FlowEVMBridgeUtils.calculateBridgeFee(bytes: 400_000) * UFix64(ids.length)
        self.requiresOnboarding = FlowEVMBridge.typeRequiresOnboarding(self.nftType)
            ?? panic("The bridge does not support ".concat(nftIdentifier))
        if self.requiresOnboarding {
            approxFee = approxFee + FlowEVMBridgeConfig.onboardFee
        }
        if signer.storage.type(at: FlowEVMBridgeConfig.providerCapabilityStoragePath) == nil {
            let providerCap = signer.capabilities.storage.issue<auth(FungibleToken.Withdraw) &{FungibleToken.Provider}>(/storage/flowTokenVault)
            signer.storage.save(providerCap, to: FlowEVMBridgeConfig.providerCapabilityStoragePath)
        }
        let providerCap = signer.storage.copy<Capability<auth(FungibleToken.Withdraw) &{FungibleToken.Provider}>>(
            from: FlowEVMBridgeConfig.providerCapabilityStoragePath)!
        self.scopedProvider <- ScopedFTProviders.createScopedFTProvider(
            provider: providerCap,
            filters: [ScopedFTProviders.AllowanceFilter(approxFee)],
            expiration: getCurrentBlock().timestamp + 1.0
        )
    }

    execute {
        let fee = &self.scopedProvider as auth(FungibleToken.Withdraw) &{FungibleToken.Provider}
        if self.requiresOnboarding {
            FlowEVMBridge.onboardByType(self.nftType, feeProvider: fee)
        }
        for id in ids {
            let nft <- self.collection.withdraw(withdrawID: id)
            assert(nft.getType() == self.nftType, message: "Item is not a ".concat(nftIdentifier))
            self.coa.depositNFT(nft: <-nft, feeProvider: fee)
        }
        destroy self.scopedProvider
    }
}
`,
        args: (arg, t) => [
            arg(nftIdentifier, t.String),
            arg([].concat(ids).map((id) => '' + id), t.Array(t.UInt64)),
        ],
        limit: 9999,
    });
}
