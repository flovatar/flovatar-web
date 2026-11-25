<?php

namespace App\Support;

/**
 * ĐUST ⇄ FLOW auto-swap via IncrementFi (KB notes/dust-swap.md).
 *
 * enabled() = DUST_SWAP_ENABLED (testnet only for now). Off: no window.DUST_SWAP, no rate chip / widget / pay-with
 * markup, and the swap templates are not on the passkey allowlist, so prod behaves exactly as before.
 *
 * Guardrails (Luca 2026-11-25):
 *  - FLOW → ĐUST (buy a ĐUST listing with FLOW, quick swap FLOW→ĐUST): Flow Wallet users, and passkey users who hold
 *    FLOW — the passkey allowlist gets these two templates ONLY on testnet behind this flag (passkeyBuyAllowed()).
 *  - ĐUST → FLOW (buy a FLOW listing with ĐUST, quick swap ĐUST→FLOW): self-custody Flow Wallet sessions only, never on
 *    the passkey allowlist (keeps Quest ĐUST in the closed loop).
 *  - Dapper sessions: no swap UI at all (enforced in the browser; Dapper never signs these templates).
 */
class DustSwap
{
    /** Passkey-allowlisted swap templates (FLOW → ĐUST only). */
    public const PASSKEY_TEMPLATES = ['dust-swap-flow-to-dust', 'dust-swap-buy-dust-listing'];

    /** Never allowlisted for passkey accounts (they sell ĐUST into the pool). */
    public const WALLET_ONLY_TEMPLATES = ['dust-swap-dust-to-flow', 'dust-swap-buy-flow-listing'];

    /** Visible to this request (DUST_SWAP_MODE on, or stealth + stealth gate; legacy DUST_SWAP_ENABLED). */
    public static function enabled(): bool
    {
        return Stealth::visible('swap');
    }

    public static function network(): string
    {
        return (string) config('services.flow.network', 'emulator');
    }

    /** Passkey accounts may sign the FLOW → ĐUST templates: testnet only, flag on. */
    public static function passkeyBuyAllowed(): bool
    {
        return self::enabled() && self::network() === 'testnet';
    }

    /** Static-safe runtime config for the browser (window.DUST_SWAP): identical for every visitor. */
    public static function bootstrap(): array
    {
        $c = (array) config('flovatar.dustSwap', []);

        return [
            'enabled' => true,
            'network' => self::network(),
            'router' => self::addr($c['router'] ?? ''),
            'swapConfig' => self::addr($c['swapConfig'] ?? ''),
            'factory' => self::addr($c['factory'] ?? ''),
            'pair' => self::addr($c['pair'] ?? ''),
            'slippageDefault' => (float) ($c['slippageDefault'] ?? 1.0),
            'slippageMax' => (float) ($c['slippageMax'] ?? 3.0),
            'impactWarn' => (float) ($c['impactWarn'] ?? 2.0),
            'impactBlock' => (float) ($c['impactBlock'] ?? 10.0),
            'lowLiquidityFlow' => (float) ($c['lowLiquidityFlow'] ?? 1000),
            'deadlineSeconds' => (int) ($c['deadlineSeconds'] ?? 300),
            'passkeyBuy' => self::passkeyBuyAllowed(),
            'dustMarket' => DustMarket::enabled(),
        ];
    }

    public static function addr(?string $a): string
    {
        $hex = strtolower(preg_replace('/^0x/i', '', trim((string) $a)));

        return preg_match('/^[0-9a-f]{1,16}$/', $hex) ? '0x'.str_pad($hex, 16, '0', STR_PAD_LEFT) : '';
    }
}
