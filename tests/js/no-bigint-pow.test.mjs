// node --test tests/js/no-bigint-pow.test.mjs — guard: Babel compiles `**` to Math.pow, which throws on BigInt
// ("Cannot convert a BigInt value to a number"). Node runs the source untranspiled, so unit tests cannot catch it:
// scan the EVM/buy-ĐUST sources instead and use pow10() from dust-evm-math.mjs.
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {pow10, toUnits, fromUnits} from '../../resources/js/evm/dust-evm-math.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const files = ['resources/js/evm/dust-evm-math.mjs', 'resources/js/evm/dust-evm.js', 'resources/js/app/buy-dust.js', 'resources/js/app/fub-v1.js'];

test('no ** in BigInt code (Babel → Math.pow throws on BigInt)', () => {
    for (const f of files) {
        const src = fs.readFileSync(path.join(root, f), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '');
        assert.equal(/\S\s*\*\*\s*\S/.test(src), false, f + ' uses ** (use pow10)');
    }
});

test('pow10', () => {
    assert.equal(pow10(0), 1n);
    assert.equal(pow10(6), 1000000n);
    assert.equal(pow10(18), 1000000000000000000n);
    assert.equal(toUnits('1.5', 6), 1500000n);
    assert.equal(fromUnits(97419n, 6), 0.097419);
});
