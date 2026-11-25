<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Runs the ĐUST swap quote-math unit test (tests/js/dust-swap-math.test.mjs, node --test) against
 * resources/js/app/dust-swap-math.js: UFix64 formatting, router-identical constant-product quotes, slippage bounds,
 * price impact policy (warn > 2%, block > 10%) and the "pay with FLOW" plan (availableBalance only).
 */
class DustSwapMathTest extends TestCase
{
    public function test_quote_math(): void
    {
        $node = trim((string) shell_exec('command -v node'));
        if ($node === '') {
            $this->markTestSkipped('node not installed');
        }
        $file = dirname(__DIR__).'/js/dust-swap-math.test.mjs';
        exec(escapeshellarg($node).' --test '.escapeshellarg($file).' 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
        $this->assertMatchesRegularExpression('/# fail 0/', implode("\n", $out));
    }
}
