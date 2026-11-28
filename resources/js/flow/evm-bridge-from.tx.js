import * as fcl from "./fcl-tx"

// EVM-A (2026-11): "Bring back from Flow EVM". Same Cadence as
// flovatar-kb/cadence/evm-a/transactions/user/bridge_from_evm.cdc (emulator + testnet tested), FCL aliases.
// The signer's COA hands the ERC-721 to the bridge (bridge-deployed ERC-721 or the project ERC-721; legacy bridged
// tokens are migrated automatically) and the Cadence NFT is unlocked into the signer's collection.
export async function evmBridgeFromTx(nftIdentifier, ids) {
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

transaction(nftIdentifier: String, ids: [UInt256]) {
    let nftType: Type
    let collection: &{NonFungibleToken.Collection}
    let coa: auth(EVM.Bridge) &EVM.CadenceOwnedAccount
    let scopedProvider: @ScopedFTProviders.ScopedFTProvider

    prepare(signer: auth(BorrowValue, CopyValue, IssueStorageCapabilityController, PublishCapability, SaveValue, UnpublishCapability) &Account) {
        assert(ids.length > 0 && ids.length <= 20, message: "Bridge 1 to 20 items per transaction")
        self.coa = signer.storage.borrow<auth(EVM.Bridge) &EVM.CadenceOwnedAccount>(from: /storage/evm)
            ?? panic("No Cadence-Owned Account (COA) at /storage/evm")
        self.nftType = CompositeType(nftIdentifier) ?? panic("Bad NFT type identifier ".concat(nftIdentifier))
        let contractAddress = FlowEVMBridgeUtils.getContractAddress(fromType: self.nftType)!
        let contractName = FlowEVMBridgeUtils.getContractName(fromType: self.nftType)!
        let resolver = getAccount(contractAddress).contracts.borrow<&{ViewResolver}>(name: contractName)
            ?? panic("NFT contract is not a ViewResolver")
        let data = resolver.resolveContractView(resourceType: self.nftType, viewType: Type<MetadataViews.NFTCollectionData>()) as! MetadataViews.NFTCollectionData?
            ?? panic("No NFTCollectionData view")
        if signer.storage.borrow<&{NonFungibleToken.Collection}>(from: data.storagePath) == nil {
            signer.storage.save(<-data.createEmptyCollection(), to: data.storagePath)
            signer.capabilities.unpublish(data.publicPath)
            signer.capabilities.publish(signer.capabilities.storage.issue<&{NonFungibleToken.Collection}>(data.storagePath), at: data.publicPath)
        }
        self.collection = signer.storage.borrow<&{NonFungibleToken.Collection}>(from: data.storagePath)!

        let approxFee = FlowEVMBridgeUtils.calculateBridgeFee(bytes: 0) * UFix64(ids.length)
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
        for id in ids {
            let nft <- self.coa.withdrawNFT(type: self.nftType, id: id, feeProvider: fee)
            assert(nft.getType() == self.nftType, message: "Bridged NFT type mismatch")
            self.collection.deposit(token: <-nft)
        }
        destroy self.scopedProvider
    }
}
`,
        args: (arg, t) => [
            arg(nftIdentifier, t.String),
            arg([].concat(ids).map((id) => '' + id), t.Array(t.UInt256)),
        ],
        limit: 9999,
    });
}
