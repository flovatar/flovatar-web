import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

/**
 * ĐUST swap (KB notes/dust-swap.md): quick swap FLOW → ĐUST on the Increment ĐUST/FLOW pool (SwapRouter
 * swapExactTokensForTokens). Spends only the account's availableBalance (never the storage-reserved FLOW), reverts if
 * fewer than amountOutMin ĐUST come out (slippage) or after `deadline` (unix seconds). Creates the ĐUST vault and
 * receiver/balance capabilities when missing. Amounts are UFix64 strings (dust-swap-math.js ufix()).
 * Passkey accounts: allowlisted on testnet only, behind DUST_SWAP_ENABLED (buying ĐUST adds FLOW to the pool).
 */
export async function dustSwapFlowToDustTx(amountIn, amountOutMin, deadline) {
    return await fcl
        .mutate({
            cadence: `
import FungibleToken from 0xFungible
import FlowToken from 0xFlowToken
import FlovatarDustToken from 0xFlovatar
import SwapRouter from 0xSwapRouter
import SwapConfig from 0xSwapConfig

transaction(amountIn: UFix64, amountOutMin: UFix64, deadline: UFix64) {
    prepare(signer: auth(Storage, Capabilities) &Account) {
        assert(amountIn > 0.0, message: "Nothing to swap")
        assert(amountIn <= signer.availableBalance, message: "Not enough FLOW (the storage reserve can't be spent)")

        if signer.storage.borrow<&FlovatarDustToken.Vault>(from: FlovatarDustToken.VaultStoragePath) == nil {
            signer.storage.save(<-FlovatarDustToken.createEmptyVault(vaultType: Type<@FlovatarDustToken.Vault>()), to: FlovatarDustToken.VaultStoragePath)
        }
        if !signer.capabilities.get<&FlovatarDustToken.Vault>(FlovatarDustToken.VaultReceiverPath).check() {
            signer.capabilities.unpublish(FlovatarDustToken.VaultReceiverPath)
            signer.capabilities.publish(signer.capabilities.storage.issue<&FlovatarDustToken.Vault>(FlovatarDustToken.VaultStoragePath), at: FlovatarDustToken.VaultReceiverPath)
        }
        if !signer.capabilities.get<&FlovatarDustToken.Vault>(FlovatarDustToken.VaultBalancePath).check() {
            signer.capabilities.unpublish(FlovatarDustToken.VaultBalancePath)
            signer.capabilities.publish(signer.capabilities.storage.issue<&FlovatarDustToken.Vault>(FlovatarDustToken.VaultStoragePath), at: FlovatarDustToken.VaultBalancePath)
        }
        let dustVault = signer.storage.borrow<&FlovatarDustToken.Vault>(from: FlovatarDustToken.VaultStoragePath)!
        let flowVault = signer.storage.borrow<auth(FungibleToken.Withdraw) &FlowToken.Vault>(from: /storage/flowTokenVault)
            ?? panic("Could not borrow your FLOW vault")

        let flowKey = SwapConfig.SliceTokenTypeIdentifierFromVaultType(vaultTypeIdentifier: Type<@FlowToken.Vault>().identifier)
        let dustKey = SwapConfig.SliceTokenTypeIdentifierFromVaultType(vaultTypeIdentifier: Type<@FlovatarDustToken.Vault>().identifier)
        let out <- SwapRouter.swapExactTokensForTokens(
            exactVaultIn: <-flowVault.withdraw(amount: amountIn),
            amountOutMin: amountOutMin,
            tokenKeyPath: [flowKey, dustKey],
            deadline: deadline
        )
        assert(out.balance >= amountOutMin, message: "Price moved: less DUST than your minimum")
        dustVault.deposit(from: <-out)
    }
}
`,
            args: (arg, t) => [
                arg(String(amountIn), t.UFix64),
                arg(String(amountOutMin), t.UFix64),
                arg(String(deadline), t.UFix64),
            ],
            limit: 9999
        });
}
