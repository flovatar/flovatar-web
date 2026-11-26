// Asteroid Islands round 4 helpers (KB islands-build.md "Round 4 spec"): shared / mutable layers, the display layer and
// its screen (contract parseScreen / screenSvg / composeSvgWithResidents), pick-and-place batching (planOps).
// `node --test tests/js/*.test.mjs`
import test from 'node:test';
import assert from 'node:assert/strict';
import {
    composeScene, validateScene, isSharedLayer, isMutableLayer, templateFits, layerOptions, orderedLayers, parseScreen, templateScreen,
    screenFragment, insertScreen, screenBox, planOps, residentGroupSvg, injectResidents, slotBox, SCREEN_PLACEHOLDER,
} from '../../resources/js/islands/compose.mjs';

const cfg = {
    sharedLayers: ['sky', 'fx', 'display'], mutableLayers: ['sky'],
    sceneOrder: ['sky', 'base', 'structure', '@residents-back', 'structure:front', 'fub', '@caretaker', '@residents-front', 'display', 'foreground', 'fx'],
    layers: {sky: {required: [], allowed: ['common', 'epic']}, base: {required: ['common', 'epic'], allowed: ['common', 'epic']},
        display: {required: ['common', 'epic'], allowed: ['common', 'epic']}, fx: {required: [], allowed: ['common', 'epic']}},
    templates: [
        {id: 1, layer: 'sky', rarity: 'epic', enabled: true}, {id: 2, layer: 'sky', rarity: 'any', enabled: true},
        {id: 3, layer: 'base', rarity: 'common', enabled: true}, {id: 4, layer: 'base', rarity: 'epic', enabled: true},
        {id: 5, layer: 'display', rarity: 'any', enabled: true, metadata: {screen: '100,200,300,150'}},
        {id: 6, layer: 'sky', rarity: 'legendary', enabled: false},
    ],
};

test('shared layers list every enabled template for every rarity; other layers stay rarity-locked', () => {
    assert.ok(isSharedLayer(cfg, 'sky') && isSharedLayer(cfg, 'display') && !isSharedLayer(cfg, 'base'));
    assert.ok(isMutableLayer(cfg, 'sky') && !isMutableLayer(cfg, 'fx'));
    assert.deepEqual(layerOptions(cfg, 'sky', 'common').map((t) => t.id), [2, 1]); // "any" first, disabled never
    assert.deepEqual(layerOptions(cfg, 'base', 'common').map((t) => t.id), [3]);
    assert.ok(templateFits(cfg, cfg.templates[0], 'sky', 'common'));
    assert.ok(!templateFits(cfg, cfg.templates[3], 'base', 'common'));
    // without the v3 settings (contract v2): rarity lock, except templates of rarity "any"
    const v2 = {...cfg, sharedLayers: [], mutableLayers: []};
    assert.deepEqual(layerOptions(v2, 'sky', 'common').map((t) => t.id), [2]);
    const infos = Object.fromEntries(cfg.templates.map((t) => [t.id, t]));
    assert.deepEqual(validateScene(cfg, 'common', {sky: 1, base: 3, display: 5}, null, infos), []);
    assert.deepEqual(validateScene(v2, 'common', {sky: 1, base: 3, display: 5}, null, infos), ['sky: template rarity doesn\u2019t match']);
    assert.deepEqual(validateScene(cfg, 'common', {base: 3}, null, infos), ['Pick a display']);
});

test('the display tab sits just before Foreground', () => {
    assert.deepEqual(orderedLayers(cfg), ['sky', 'base', 'structure', 'fub', 'display', 'foreground', 'fx']);
    const old = {...cfg, sceneOrder: ['sky', 'base', 'foreground', 'fx']};
    assert.deepEqual(orderedLayers(old), ['sky', 'base', 'display', 'foreground', 'fx']);
    assert.deepEqual(orderedLayers({...old, layers: {}}), ['sky', 'base', 'foreground', 'fx']);
});

test('parseScreen follows the contract grammar (x,y,w,h or + 8 quad numbers) and the API shape', () => {
    assert.deepEqual(parseScreen('100,200,300,150'), {x: 100, y: 200, w: 300, h: 150, quad: []});
    assert.deepEqual(parseScreen('10,20,100,50,10,20,110,25,108,72,12,70').quad, [10, 20, 110, 25, 108, 72, 12, 70]);
    assert.deepEqual(parseScreen({x: 1, y: 2, w: 3, h: 4, quad: []}), {x: 1, y: 2, w: 3, h: 4, quad: []});
    assert.deepEqual(parseScreen('{"x":1,"y":2,"w":3,"h":4,"quad":[[1,2],[4,2],[4,6],[1,6]]}').quad, [1, 2, 4, 2, 4, 6, 1, 6]);
    for (const bad of ['', '1,2,3', '1,2,0,4', '1,2,3,-4', 'a,b,c,d', '1,2,3,4,5', '1,2,3,200000']) { assert.equal(parseScreen(bad), null, bad); }
    assert.deepEqual(templateScreen(cfg.templates[4]), {x: 100, y: 200, w: 300, h: 150, quad: []});
    assert.deepEqual(screenBox(parseScreen('10,20,100,50,10,20,110,25,108,72,12,70')), {x: 10, y: 20, w: 100, h: 52});
});

test('screen fragment = contract screenSvg (rect / quad) and goes right after the display layer body', () => {
    const rect = screenFragment(parseScreen('100,200,300,150'), 'https://x/a.png?a=1&b=2', 7, 'S1');
    assert.equal(rect, '<defs><clipPath id="screen-clip-7"><rect x="100" y="200" width="300" height="150"/></clipPath></defs>'
        + '<g id="screen-S1" class="resident standard screen" data-slot="S1" clip-path="url(#screen-clip-7)">'
        + '<image href="https://x/a.png?a=1&amp;b=2" x="100" y="200" width="300" height="150" preserveAspectRatio="xMidYMid slice"/></g>');
    const quad = screenFragment(parseScreen('0,0,100,50,10,20,110,20,110,70,10,70'), 'u', 1, 'S2');
    assert.ok(quad.includes('<polygon points="10,20 110,20 110,70 10,70"/>'));
    assert.ok(quad.includes('transform="matrix(1 0 0 1 10 20)"'));

    const tpl = {9: {svg: '<d/>', svgLength: 4, svgFront: '', svgFrontLength: 0}, 3: {svg: '<b/>', svgLength: 4, svgFront: '', svgFrontLength: 0}};
    const c = {svgOpen: '<svg', sceneOrder: ['base', 'display', 'foreground']};
    const scene = composeScene(c, {base: 3, display: 9}, null, 1, tpl);
    assert.equal(insertScreen(scene, '<S/>', tpl[9]), '<svg data-island="1"><g id="layer-base"><b/></g><g id="layer-display"><d/></g><S/></svg>');
    const withPh = composeScene({svgOpen: '<svg', sceneOrder: ['display', '@display-screen', 'fx']}, {display: 9}, null, null, tpl);
    assert.equal(insertScreen(withPh, '<S/>', tpl[9]), '<svg><g id="layer-display"><d/></g><g id="display-screen"><S/></g></svg>');
    assert.equal(insertScreen(scene, '', tpl[9]), scene);
    assert.ok(SCREEN_PLACEHOLDER.startsWith('data:image/svg+xml;base64,'));
});

test('pick-and-place: pending plan -> one transaction (clears first, then sets; a move = clear + set)', () => {
    const F = 'A.1.Flovatar.NFT', B = 'A.1.Flobit.NFT';
    const current = {P1: {type: F, id: 1}, P2: null, S1: {type: B, id: 5}, S2: null, S3: {type: B, id: 6}};
    // move Flovatar 1 P1 -> P2, add Flobit 7 on the display, remove Flobit 6
    const plan = {P1: null, P2: {type: F, id: 1}, S1: {type: B, id: 5}, S2: {type: B, id: 7}, S3: null};
    assert.deepEqual(planOps(current, plan), {clears: ['P1', 'S3'], sets: [{slot: 'P2', type: F, id: 1}, {slot: 'S2', type: B, id: 7}]});
    assert.deepEqual(planOps(current, {...current}), {clears: [], sets: []});
    // replace in place: clear + set on the same slot
    assert.deepEqual(planOps({S1: {type: B, id: 5}}, {S1: {type: B, id: 9}}), {clears: ['S1'], sets: [{slot: 'S1', type: B, id: 9}]});
    // slot order is numeric (S2 before S10)
    assert.deepEqual(planOps({}, {S10: {type: B, id: 1}, S2: {type: B, id: 2}}).sets.map((x) => x.slot), ['S2', 'S10']);
});

test('browser-composed residents match the server markup; slot hotspot boxes', () => {
    const g = residentGroupSvg('P1', [1512, 2610, 0.2, 'front', false], 'data:x', 0.16, 1);
    assert.equal(g, '<g class="island-resident" data-slot="P1"><ellipse cx="1512" cy="2610" rx="112" ry="11.6" fill="#000" opacity=".27"/>'
        + '<g transform="translate(1212 2065) scale(0.2)"><image width="3000" height="3000" href="data:x"/></g></g>');
    assert.equal(injectResidents('<svg><g id="residents-back"/><g id="residents-front"/></svg>', '', '<r/>', ''),
        '<svg><g id="residents-back"/><g id="residents-front"><r/></g></svg>');
    assert.deepEqual(slotBox([1500, 2725, 0.2, 'front', false], 0.16, 1), {x: 1350, y: 2240, w: 300, h: 485});
});
