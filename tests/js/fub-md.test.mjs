import {test} from 'node:test';
import assert from 'node:assert/strict';
import {mdToHtml, mdInline, escHtml} from '../../resources/js/fub/md.mjs';

test('inline: bold, italic, code', () => {
    assert.equal(mdInline('**Red Pom Beanie** is *new*'), '<strong>Red Pom Beanie</strong> is <em>new</em>');
    assert.equal(mdInline('__bold__ and _it_'), '<strong>bold</strong> and <em>it</em>');
    assert.equal(mdInline('use `fill="red"` here'), 'use <code>fill=&quot;red&quot;</code> here');
    assert.equal(mdInline('`**not bold**`'), '<code>**not bold**</code>');
    assert.equal(mdInline('**a *b* c**'), '<strong>a <em>b</em> c</strong>');
});

test('inline: things that only look like markup stay literal', () => {
    assert.equal(mdInline('2 * 3 * 4'), '2 * 3 * 4');
    assert.equal(mdInline('snake_case_name and file_v2_final'), 'snake_case_name and file_v2_final');
    assert.equal(mdInline('**unclosed bold'), '**unclosed bold');
    assert.equal(mdInline('a ** b'), 'a ** b');
});

test('blocks: paragraphs, line breaks, lists', () => {
    assert.equal(mdToHtml('Hello\nworld\n\nSecond'), '<p>Hello<br>world</p><p>Second</p>');
    assert.equal(mdToHtml('Added:\n- **Hat** (layer 2)\n- Scarf\n\nDone.'), '<p>Added:</p><ul><li><strong>Hat</strong> (layer 2)</li><li>Scarf</li></ul><p>Done.</p>');
    assert.equal(mdToHtml('* one\n* two'), '<ul><li>one</li><li>two</li></ul>');
    assert.equal(mdToHtml('1. first\n2. second'), '<ol><li>first</li><li>second</li></ol>');
    assert.equal(mdToHtml('3) third\n4) fourth'), '<ol start="3"><li>third</li><li>fourth</li></ol>');
    assert.equal(mdToHtml('- a long item\n  that wraps\n- b'), '<ul><li>a long item that wraps</li><li>b</li></ul>');
    assert.equal(mdToHtml('- a\n1. b'), '<ul><li>a</li></ul><ol><li>b</li></ol>');
    assert.equal(mdToHtml('## Summary\ntext'), '<p><strong>Summary</strong></p><p>text</p>');
    assert.equal(mdToHtml('a\n\n---\n\nb'), '<p>a</p><p>b</p>');
    assert.equal(mdToHtml('*italic start* of a line'), '<p><em>italic start</em> of a line</p>');
    assert.equal(mdToHtml('a\r\nb'), '<p>a<br>b</p>');
    assert.equal(mdToHtml(''), '');
    assert.equal(mdToHtml(null), '');
});

test('untrusted text: no HTML passthrough, no links, no images', () => {
    const evil = [
        '<img src=x onerror=alert(1)>',
        '<script>alert(1)</script>',
        '[click](javascript:alert(1))',
        '![x](https://evil.example/p.png)',
        '**<b onmouseover="x()">hi</b>**',
        '- <a href="https://evil.example">link</a>',
        '`<svg onload=alert(1)>`',
        '"><iframe src=//evil>',
        '<https://evil.example>',
    ];
    for (const s of evil) {
        const h = mdToHtml(s);
        // only our own attribute-less tags (plus ol start="N") may appear
        const tags = h.match(/<[^>]*>/g) || [];
        for (const t of tags) { assert.match(t, /^<\/?(p|br|strong|em|code|ul|ol|li)>$|^<ol start="\d+">$/, s + ' -> ' + h); }
        assert.ok(!/<(img|script|a|iframe|svg|b)\b/i.test(h), h);
    }
    assert.equal(mdToHtml('[click](javascript:alert(1))'), '<p>[click](javascript:alert(1))</p>');
    assert.equal(mdToHtml('<b>x</b>'), '<p>&lt;b&gt;x&lt;/b&gt;</p>');
    assert.equal(escHtml(`<&>"'`), '&lt;&amp;&gt;&quot;&#39;');
});
