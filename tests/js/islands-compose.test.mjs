// Asteroid Islands composition helpers: `node --test tests/js/*.test.mjs`
// Fixture = real testnet chain output (island #1 getSvg + its template), so the browser builder's preview is pinned to
// the contract's composeSvg byte for byte.
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {composeScene, sceneBytes, islandRarity, validateScene, slotList, defaultAnchors, residentTransform, httpUrl, designation, normalizeAddress} from '../../resources/js/islands/compose.mjs';

const fx = JSON.parse(fs.readFileSync(new URL('../Fixtures/islands-scene-1.json', import.meta.url), 'utf8'));

test('composeScene is byte-identical to the contract getSvg()', () => {
    const out = composeScene(fx.cfg, fx.island.layers, fx.island.tint, fx.island.id, fx.templates);
    assert.equal(out, fx.expected);
});

test('composeScene: tint, preview (no id), placeholders, front parts', () => {
    const cfg = {svgOpen: '<svg a="1"', sceneOrder: ['sky', 'base', 'structure', '@residents-back', 'structure:front', '@residents-front']};
    const tpl = {1: {svg: '<p/>', svgLength: 4, svgFront: '', svgFrontLength: 0}, 2: {svg: '<s/>', svgLength: 4, svgFront: '<f/>', svgFrontLength: 4}};
    assert.equal(composeScene(cfg, {base: 1, structure: 2}, '#aabbcc', null, tpl),
        '<svg a="1" style="--island-tint:#aabbcc"><g id="layer-base"><p/></g><g id="layer-structure"><s/></g><g id="residents-back"/><g id="layer-structure-front"><f/></g><g id="residents-front"/></svg>');
    // invalid tint is ignored, like the contract
    assert.ok(!composeScene(cfg, {base: 1}, 'red', 7, tpl).includes('--island-tint'));
    assert.ok(composeScene(cfg, {base: 1}, null, 7, tpl).startsWith('<svg a="1" data-island="7">'));
});

test('rarity rules: 1 deed same tier, 5 deeds next tier, no legendary fusion', () => {
    assert.equal(islandRarity('common', 1), 'common');
    assert.equal(islandRarity('common', 5), 'epic');
    assert.equal(islandRarity('epic', 5), 'legendary');
    assert.equal(islandRarity('legendary', 5), null);
    assert.equal(islandRarity('common', 2), null);
});

test('budget + validation mirror validateScene', () => {
    const cfg = {layers: {base: {required: ['common'], allowed: ['common', 'epic']}, sky: {required: [], allowed: ['common']}},
        budget: {common: 10}, tintRarities: ['epic']};
    const infos = {1: {id: 1, layer: 'base', rarity: 'common', enabled: true, svgLength: 6, svgFrontLength: 0},
        2: {id: 2, layer: 'sky', rarity: 'common', enabled: true, svgLength: 5, svgFrontLength: 0},
        3: {id: 3, layer: 'base', rarity: 'epic', enabled: true, svgLength: 1, svgFrontLength: 0}};
    assert.deepEqual(validateScene(cfg, 'common', {base: 1}, null, infos), []);
    assert.equal(sceneBytes({base: 1, sky: 2}, infos), 11);
    assert.ok(validateScene(cfg, 'common', {base: 1, sky: 2}, null, infos).some((e) => /budget/.test(e)));
    assert.ok(validateScene(cfg, 'common', {}, null, infos).some((e) => /base/.test(e)));
    assert.ok(validateScene(cfg, 'common', {base: 3}, null, infos).some((e) => /rarity/.test(e)));
    assert.ok(validateScene(cfg, 'common', {base: 1}, '#112233', infos).some((e) => /Tint/.test(e)));
});

test('slot model: P/S natively, mapped onto pad ids for the legacy grammar', () => {
    const ps = slotList('common', 5, 3, 'ps');
    assert.deepEqual(ps.map((s) => s.slot), ['P1', 'P2', 'P3', 'S1', 'S2']);
    assert.deepEqual(ps.map((s) => s.chain), ['P1', 'P2', 'P3', 'S1', 'S2']);
    const pad = slotList('epic', 25, 6, 'pad');
    assert.equal(pad[0].chain, 'A1');
    assert.equal(pad[5].chain, 'B1');
    assert.equal(pad[6].slot, 'S1');
    assert.equal(slotList('legendary', 125, 10, 'pad')[124].chain, 'D5E5');
});

test('resident placement keeps the foot point on the anchor', () => {
    const a = defaultAnchors(3);
    assert.deepEqual(Object.keys(a), ['P1', 'P2', 'P3']);
    const t = residentTransform([1512, 2610, 0.2, 'front', false], 0.16, 1);
    assert.equal(t.k, 0.2);
    assert.equal(Math.round(1500 * t.k + t.tx), 1512);
    assert.equal(Math.round(2725 * t.k + t.ty), 2610);
    assert.ok(residentTransform([1512, 2610, 0, 'front', true], 0.16, 0.62).transform.includes('scale(-1,1)'));
});

test('misc helpers', () => {
    assert.equal(httpUrl('ipfs://Qm123/x.png'), 'https://ipfs.io/ipfs/Qm123/x.png');
    assert.equal(httpUrl('javascript:alert(1)'), '');
    assert.equal(designation(7), 'K-334/0007');
    assert.equal(normalizeAddress('2538515561c0c431'), '0x2538515561c0c431');
});

test('displayFix declares xmlns:xlink only when needed', async () => {
    const {displayFix} = await import('../../resources/js/islands/compose.mjs');
    const a = '<svg xmlns="http://www.w3.org/2000/svg"><use xlink:href="#a"/></svg>';
    assert.ok(displayFix(a).startsWith('<svg xmlns:xlink="http://www.w3.org/1999/xlink" xmlns='));
    assert.equal(displayFix(displayFix(a)), displayFix(a));
    const b = '<svg xmlns="http://www.w3.org/2000/svg"><g/></svg>';
    assert.equal(displayFix(b), b);
});
