import * as fcl from "@onflow/fcl"
import * as t from "@onflow/types"

/**
 * ĐUST ⇄ FLOW swap quote (KB notes/dust-swap.md), read in the browser (chain-first). Finds the Increment ĐUST/FLOW pair
 * through SwapFactory, returns its reserves and the router quote:
 *   exactOut = true  → SwapRouter.getAmountsIn  (amount = wanted output, e.g. a listing's ĐUST price)
 *   exactOut = false → SwapRouter.getAmountsOut (amount = input to sell)
 * amount: UFix64 string (8 decimals, see dust-swap-math.js ufix()); '0.0' = reserves only (rate chip). With `user`, also that account's spendable FLOW (availableBalance: never the
 * storage-reserved FLOW) and ĐUST balance. Panics (→ the caller hides the swap) when the pair is missing or the quote is
 * impossible (amountOut >= reserve).
 */
export async function getDustSwapQuoteScript(flowToDust, exactOut, amount, user) {
    return await fcl
        .query({
            cadence: `
import FungibleToken from 0xFungible
import FlowToken from 0xFlowToken
import FlovatarDustToken from 0xFlovatar
import SwapFactory from 0xSwapFactory
import SwapRouter from 0xSwapRouter
import SwapConfig from 0xSwapConfig
import SwapInterfaces from 0xSwapConfig

access(all) struct Quote {
    access(all) let pairAddress: Address
    access(all) let dustKey: String
    access(all) let flowKey: String
    access(all) let reserveDust: UFix64
    access(all) let reserveFlow: UFix64
    access(all) let feeBps: UInt64
    access(all) let amountIn: UFix64
    access(all) let amountOut: UFix64
    access(all) let flowAvailable: UFix64
    access(all) let dustBalance: UFix64
    access(all) let hasDustVault: Bool
    init(pairAddress: Address, dustKey: String, flowKey: String, reserveDust: UFix64, reserveFlow: UFix64, feeBps: UInt64,
         amountIn: UFix64, amountOut: UFix64, flowAvailable: UFix64, dustBalance: UFix64, hasDustVault: Bool) {
        self.pairAddress = pairAddress
        self.dustKey = dustKey
        self.flowKey = flowKey
        self.reserveDust = reserveDust
        self.reserveFlow = reserveFlow
        self.feeBps = feeBps
        self.amountIn = amountIn
        self.amountOut = amountOut
        self.flowAvailable = flowAvailable
        self.dustBalance = dustBalance
        self.hasDustVault = hasDustVault
    }
}

access(all) fun main(flowToDust: Bool, exactOut: Bool, amount: UFix64, user: Address?): Quote {
    let dustKey = SwapConfig.SliceTokenTypeIdentifierFromVaultType(vaultTypeIdentifier: Type<@FlovatarDustToken.Vault>().identifier)
    let flowKey = SwapConfig.SliceTokenTypeIdentifierFromVaultType(vaultTypeIdentifier: Type<@FlowToken.Vault>().identifier)
    let pairAddress = SwapFactory.getPairAddress(token0Key: dustKey, token1Key: flowKey) ?? panic("No DUST/FLOW pool")
    let pair = getAccount(pairAddress).capabilities.borrow<&{SwapInterfaces.PairPublic}>(SwapConfig.PairPublicPath)
        ?? panic("DUST/FLOW pool unavailable")
    let info = pair.getPairInfo()
    let dustIs0 = (info[0] as! String) == dustKey
    let reserveDust = (dustIs0 ? info[2] : info[3]) as! UFix64
    let reserveFlow = (dustIs0 ? info[3] : info[2]) as! UFix64

    var amountIn = 0.0
    var amountOut = 0.0
    if amount > 0.0 {
        let path = flowToDust ? [flowKey, dustKey] : [dustKey, flowKey]
        if exactOut {
            amountIn = SwapRouter.getAmountsIn(amountOut: amount, tokenKeyPath: path)[0]
            amountOut = amount
        } else {
            amountIn = amount
            amountOut = SwapRouter.getAmountsOut(amountIn: amount, tokenKeyPath: path)[1]
        }
    }

    var flowAvailable = 0.0
    var dustBalance = 0.0
    var hasDustVault = false
    if let address = user {
        let account = getAccount(address)
        flowAvailable = account.availableBalance
        if let vault = account.capabilities.borrow<&{FungibleToken.Balance}>(FlovatarDustToken.VaultBalancePath) {
            dustBalance = vault.balance
            hasDustVault = true
        }
    }

    return Quote(pairAddress: pairAddress, dustKey: dustKey, flowKey: flowKey, reserveDust: reserveDust, reserveFlow: reserveFlow,
        feeBps: info[6] as! UInt64, amountIn: amountIn, amountOut: amountOut, flowAvailable: flowAvailable,
        dustBalance: dustBalance, hasDustVault: hasDustVault)
}
`,
            args: (arg, t) => [
                arg(!!flowToDust, t.Bool),
                arg(!!exactOut, t.Bool),
                arg(amount ? String(amount) : '0.0', t.UFix64),
                arg(user || null, t.Optional(t.Address)),
            ],
        });
}
