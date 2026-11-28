import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

// Pack Rewind (testnet): buy a Flovatar pack paying FLOW with a packs-v2 authorisation from POST /pack-rewind/reserve
// (bound to this wallet, expiring; checked on-chain by FlovatarPack.purchasePack). Creates the pack + Flobit
// collections when missing so the pack can be opened right away. Pays EXACTLY the pack price from spendable FLOW.
export async function rewindBuyFlowTx(tokenId, amount, expiry, signature) {
    return await fcl
        .mutate({
            cadence: `
import FlovatarComponent, FlovatarPack from 0xFlovatar
import FungibleToken from 0xFungible
import FlowToken from 0xFlowToken

transaction(tokenId: UInt64, amount: UFix64, expiry: UInt64, signature: String) {
    let collectionCap: Capability<&{FlovatarPack.CollectionPublic}>
    let payment: @{FungibleToken.Vault}

    prepare(account: auth(Storage, Capabilities) &Account) {
        assert(amount <= account.availableBalance, message: "Not enough FLOW (the storage reserve can't be spent)")
        if account.storage.borrow<&FlovatarPack.Collection>(from: FlovatarPack.CollectionStoragePath) == nil {
            let wallet = account.capabilities.get<&FlowToken.Vault>(/public/flowTokenReceiver)
            account.storage.save<@FlovatarPack.Collection>(<- FlovatarPack.createEmptyCollection(ownerVault: wallet), to: FlovatarPack.CollectionStoragePath)
        }
        if !account.capabilities.get<&FlovatarPack.Collection>(FlovatarPack.CollectionPublicPath).check() {
            account.capabilities.unpublish(FlovatarPack.CollectionPublicPath)
            account.capabilities.publish(account.capabilities.storage.issue<&FlovatarPack.Collection>(FlovatarPack.CollectionStoragePath), at: FlovatarPack.CollectionPublicPath)
        }
        if account.storage.type(at: FlovatarComponent.CollectionStoragePath) == nil {
            account.storage.save(<-FlovatarComponent.createEmptyCollection(nftType: Type<@FlovatarComponent.NFT>()), to: FlovatarComponent.CollectionStoragePath)
        }
        if !account.capabilities.get<&{FlovatarComponent.CollectionPublic}>(FlovatarComponent.CollectionPublicPath).check() {
            account.capabilities.unpublish(FlovatarComponent.CollectionPublicPath)
            account.capabilities.publish(account.capabilities.storage.issue<&FlovatarComponent.Collection>(FlovatarComponent.CollectionStoragePath), at: FlovatarComponent.CollectionPublicPath)
        }
        self.collectionCap = account.capabilities.get<&{FlovatarPack.CollectionPublic}>(FlovatarPack.CollectionPublicPath)
        let vault = account.storage.borrow<auth(FungibleToken.Withdraw) &FlowToken.Vault>(from: /storage/flowTokenVault)
            ?? panic("Could not borrow your FLOW vault")
        self.payment <- vault.withdraw(amount: amount)
    }

    execute {
        FlovatarPack.purchasePack(tokenId: tokenId, recipientCap: self.collectionCap, buyTokens: <- self.payment, expiry: expiry, signature: signature, expectedPrice: amount)
    }
}
`,
            args: (arg, t) => [
                arg('' + tokenId, t.UInt64),
                arg(String(amount), t.UFix64),
                arg('' + expiry, t.UInt64),
                arg(signature, t.String)
            ],
            limit: 9999
        });
}
