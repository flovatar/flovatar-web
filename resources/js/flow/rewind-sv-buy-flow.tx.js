import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

// Pack Rewind (testnet), 2023 chapter: buy a Sportvatar pack paying FLOW (SportvatarPack.purchasePackFlow, price from
// the contract's FLOW price book, 9 FLOW) with a "purchase-flow" authorisation from POST /pack-rewind/reserve (bound to
// this wallet, expiring). Creates the Sportvatar pack + Sportbit collections when missing. FLOW goes to the Sportvatar
// contract account.
export async function rewindSvBuyFlowTx(tokenId, amount, expiry, signature) {
    return await fcl
        .mutate({
            cadence: `
import Sportbit, SportvatarPack from 0xSportvatar
import NonFungibleToken from 0xNonFungible
import FungibleToken from 0xFungible
import FlowToken from 0xFlowToken

transaction(tokenId: UInt64, amount: UFix64, expiry: UInt64, signature: String) {
    let collectionCap: Capability<&{SportvatarPack.CollectionPublic}>
    let payment: @FlowToken.Vault

    prepare(account: auth(Storage, Capabilities) &Account) {
        assert(amount <= account.availableBalance, message: "Not enough FLOW (the storage reserve can't be spent)")
        if account.storage.borrow<&SportvatarPack.Collection>(from: SportvatarPack.CollectionStoragePath) == nil {
            account.storage.save<@SportvatarPack.Collection>(<- SportvatarPack.createEmptyCollection(), to: SportvatarPack.CollectionStoragePath)
        }
        if !account.capabilities.get<&SportvatarPack.Collection>(SportvatarPack.CollectionPublicPath).check() {
            account.capabilities.unpublish(SportvatarPack.CollectionPublicPath)
            account.capabilities.publish(account.capabilities.storage.issue<&SportvatarPack.Collection>(SportvatarPack.CollectionStoragePath), at: SportvatarPack.CollectionPublicPath)
        }
        if account.storage.borrow<&Sportbit.Collection>(from: Sportbit.CollectionStoragePath) == nil {
            account.storage.save<@{NonFungibleToken.Collection}>(<- Sportbit.createEmptyCollection(nftType: Type<@Sportbit.Collection>()), to: Sportbit.CollectionStoragePath)
        }
        if !account.capabilities.get<&Sportbit.Collection>(Sportbit.CollectionPublicPath).check() {
            account.capabilities.unpublish(Sportbit.CollectionPublicPath)
            account.capabilities.publish(account.capabilities.storage.issue<&Sportbit.Collection>(Sportbit.CollectionStoragePath), at: Sportbit.CollectionPublicPath)
        }
        self.collectionCap = account.capabilities.get<&{SportvatarPack.CollectionPublic}>(SportvatarPack.CollectionPublicPath)
        let vault = account.storage.borrow<auth(FungibleToken.Withdraw) &FlowToken.Vault>(from: /storage/flowTokenVault)
            ?? panic("Could not borrow your FLOW vault")
        self.payment <- vault.withdraw(amount: amount) as! @FlowToken.Vault
    }

    execute {
        SportvatarPack.purchasePackFlow(tokenId: tokenId, recipientCap: self.collectionCap, buyTokens: <- self.payment, expiry: expiry, signature: signature, expectedPrice: amount)
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
