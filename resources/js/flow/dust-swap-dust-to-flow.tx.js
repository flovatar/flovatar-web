import * as fcl from "./fcl-tx"
import * as t from "@onflow/types"

/**
 * ĐUST swap (KB notes/dust-swap.md): quick swap ĐUST → FLOW on the Increment ĐUST/FLOW pool (SwapRouter
 * swapExactTokensForTokens). Reverts if fewer than amountOutMin FLOW come out (slippage) or after `deadline`.
 * Self-custody Flow Wallet sessions only: NOT on the passkey allowlist (keeps Quest ĐUST in the closed loop) and never
 * offered to Dapper. Amounts are UFix64 strings (dust-swap-math.js ufix()).
 */
export async function dustSwapDustToFlowTx(amountIn, amountOutMin, deadline) {
    return await fcl
        .mutate({
            cadence: `
import FungibleToken from 0xFungible
import FlowToken from 0xFlowToken
import FlovatarDustToken from 0xFlovatar
import SwapRouter from 0xSwapRouter
import SwapConfig from 0xSwapConfig

transaction(amountIn: UFix64, amountOutMin: UFix64, deadline: UFix64) {
    prepare(signer: auth(Storage) &Account) {
        assert(amountIn > 0.0, message: "Nothing to swap")
        let dustVault = signer.storage.borrow<auth(FungibleToken.Withdraw) &FlovatarDustToken.Vault>(from: FlovatarDustToken.VaultStoragePath)
            ?? panic("You have no DUST vault")
        assert(dustVault.balance >= amountIn, message: "Not enough DUST")
        let flowReceiver = signer.storage.borrow<&FlowToken.Vault>(from: /storage/flowTokenVault)
            ?? panic("Could not borrow your FLOW vault")

        let dustKey = SwapConfig.SliceTokenTypeIdentifierFromVaultType(vaultTypeIdentifier: Type<@FlovatarDustToken.Vault>().identifier)
        let flowKey = SwapConfig.SliceTokenTypeIdentifierFromVaultType(vaultTypeIdentifier: Type<@FlowToken.Vault>().identifier)
        let out <- SwapRouter.swapExactTokensForTokens(
            exactVaultIn: <-dustVault.withdraw(amount: amountIn),
            amountOutMin: amountOutMin,
            tokenKeyPath: [dustKey, flowKey],
            deadline: deadline
        )
        assert(out.balance >= amountOutMin, message: "Price moved: less FLOW than your minimum")
        flowReceiver.deposit(from: <-out)
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
