<?php

namespace App\Support;

/**
 * "Buy ĐUST from any chain" (KB notes/flow-evm-program.md EVM-B): pay with a stablecoin on Flow EVM (the wallet's
 * Cadence-owned EVM account, COA) — and from another chain by bridging it into the COA first — in ONE Cadence tx that
 * swaps on the Flow EVM DEX, then bridges ĐUST back (or goes via FLOW + Increment) and burns / deposits it.
 *
 * Rides on the ĐUST swap feature (same lazy bundle "s", same Stealth gate) + its own flag DUST_EVM_ENABLED, so prod is
 * untouched while either is off. Dapper sessions never see it (no COA); passkey templates are not allowlisted (yet).
 */
class DustEvm
{
    public static function enabled(): bool
    {
        return DustSwap::enabled() && (bool) config('flovatar.dustEvm.enabled', false);
    }

    /** Static-safe runtime config for the browser (window.DUST_EVM): identical for every visitor. */
    public static function bootstrap(): array
    {
        $c = (array) config('flovatar.dustEvm', []);
        $pay = (array) ($c['pay'] ?? []);
        $xchain = in_array($c['xchain'] ?? 'off', ['simulate', 'relay', 'off'], true) ? $c['xchain'] : 'off';

        return [
            'enabled' => true,
            'network' => DustSwap::network(),
            'evm' => DustSwap::addr($c['evm'] ?? ''),
            'bridge' => DustSwap::addr($c['bridge'] ?? ''),
            'chainId' => (int) ($c['chainId'] ?? 0),
            'explorer' => (string) ($c['explorer'] ?? ''),
            'router' => self::evmAddr($c['router'] ?? ''),
            'factory' => self::evmAddr($c['factory'] ?? ''),
            'wflow' => self::evmAddr($c['wflow'] ?? ''),
            'dust' => self::evmAddr($c['dustErc20'] ?? ''),
            'pay' => [
                'symbol' => (string) ($pay['symbol'] ?? 'USDC'),
                'name' => (string) ($pay['name'] ?? ''),
                'address' => self::evmAddr($pay['address'] ?? ''),
                'decimals' => (int) ($pay['decimals'] ?? 6),
            ],
            'routes' => array_values(array_intersect((array) ($c['routes'] ?? []), ['direct', 'hop', 'via'])),
            'testFaucet' => (bool) ($c['testFaucet'] ?? false),
            'xchain' => $xchain,
            'relayApi' => (string) ($c['relayApi'] ?? ''),
            'origins' => array_values((array) ($c['origins'] ?? [])),
            'slippageDefault' => (float) ($c['slippageDefault'] ?? 1.0),
            'slippageMax' => (float) ($c['slippageMax'] ?? 3.0),
            'impactWarn' => (float) ($c['impactWarn'] ?? 5.0),
            'impactBlock' => (float) ($c['impactBlock'] ?? 10.0),
            'deadlineSeconds' => (int) ($c['deadlineSeconds'] ?? 300),
        ];
    }

    public static function evmAddr(?string $a): string
    {
        $a = trim((string) $a);

        return preg_match('/^0x[0-9a-fA-F]{40}$/', $a) ? $a : '';
    }
}
