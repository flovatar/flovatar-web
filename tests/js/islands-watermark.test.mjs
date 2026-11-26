// Islands builder: "UNMINTED ISLAND" stamp on saved PNGs + category tab icons. `node --test tests/js/*.test.mjs`
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {UNMINTED_WATERMARK, withWatermark} from '../../resources/js/islands/watermark.mjs';
import {composeScene} from '../../resources/js/islands/compose.mjs';

const fx = JSON.parse(fs.readFileSync(new URL('../Fixtures/islands-scene-1.json', import.meta.url), 'utf8'));

test('the stamp is the last child of the scene and is added once', () => {
    const scene = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3025.27 4033.12"><g id="layer-base"/></svg>';
    const out = withWatermark(scene);
    assert.ok(out.endsWith(UNMINTED_WATERMARK + '</svg>'));
    assert.ok(out.startsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3025.27 4033.12"><g id="layer-base"/>'));
    assert.equal(withWatermark(out), out);
    assert.equal(out.split('id="isb-unminted"').length, 2);
    assert.equal(withWatermark('not svg'), 'not svg');
});

test('the stamp follows the Flovatar / Sportvatar builder watermarks: white, half opacity, top left, outlined text', () => {
    assert.ok(UNMINTED_WATERMARK.startsWith('<g id="isb-unminted" opacity=".5" fill="#fff"'));
    assert.ok(/^[\x20-\x7e]+$/.test(UNMINTED_WATERMARK));
    for (const bad of ['<text', '<script', 'href', 'font-family', '<image', 'style=']) { assert.ok(!UNMINTED_WATERMARK.includes(bad), bad); }
    // every coordinate stays inside the top-left stamp box (centre 748,748, radius 567) of the 3025 x 4033 canvas
    const nums = [...UNMINTED_WATERMARK.matchAll(/M(-?[\d.]+)[ ,](-?[\d.]+)/g)].map((m) => [Number(m[1]), Number(m[2])]);
    assert.ok(nums.length > 30);
    for (const [x, y] of nums) { assert.ok(x >= 170 && x <= 1330 && y >= 170 && y <= 1330, x + ',' + y); }
});

test('scenes of real islands never carry the stamp (it is added only by the builder\'s Save PNG)', () => {
    const out = composeScene(fx.cfg, fx.island.layers, fx.island.tint, fx.island.id, fx.templates);
    assert.ok(!out.includes('isb-unminted'));
    assert.equal(out, fx.expected);
});

test('every builder tab has an idle and a selected icon file', () => {
    for (const k of ['deeds', 'sky', 'base', 'structure', 'fub', 'foreground', 'fx', 'tint']) {
        for (const suffix of ['', '-on']) {
            const svg = fs.readFileSync(new URL('../../public/images/islands/tab-' + k + suffix + '.svg', import.meta.url), 'utf8');
            assert.ok(svg.startsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 420 380">'), k + suffix);
            for (const bad of ['<script', 'href', '<image', '<text']) { assert.ok(!svg.includes(bad), k + suffix + ' ' + bad); }
        }
        // idle = black / white / greys only; selected = brand colours
        const idle = fs.readFileSync(new URL('../../public/images/islands/tab-' + k + '.svg', import.meta.url), 'utf8');
        for (const c of idle.match(/#[0-9a-fA-F]{6}\b/g) || []) { const v = c.slice(1).toLowerCase(); assert.ok(v.slice(0, 2) === v.slice(2, 4) && v.slice(2, 4) === v.slice(4, 6) || v === '1a1e2d', k + ' ' + c); }
    }
});
