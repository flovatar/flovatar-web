/**
 * Safe Markdown subset for assistant replies in the FUB studio (the reply text is UNTRUSTED model output).
 *
 * Supported: **bold** / __bold__, *italic* / _italic_, `inline code`, bullet lists (- * + •), numbered lists (1. / 1)),
 * paragraphs (blank line) and line breaks. Headings (# …) become a bold line; horizontal rules are dropped.
 * Everything else stays literal text: the whole input is HTML-escaped FIRST, and the only markup this function ever
 * emits is a fixed set of attribute-less tags (p, br, strong, em, code, ul, ol, li; ol may carry a numeric start).
 * No HTML passthrough, no links, no images.
 */

const ESC = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'};

export function escHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ESC[c]);
}

// emphasis on already-escaped text (escaping never produces * or _)
function emph(h) {
    return h
        .replace(/\*\*(?=\S)([^\n]*?\S)\*\*/g, '<strong>$1</strong>')
        .replace(/(^|[^_\w])__(?=\S)([^\n]*?\S)__(?![_\w])/g, '$1<strong>$2</strong>')
        .replace(/(^|[^*\w])\*(?=[^\s*])([^*\n]*?[^\s*])\*(?![*\w])/g, '$1<em>$2</em>')
        .replace(/(^|[^_\w])_(?=[^\s_])([^_\n]*?[^\s_])_(?![_\w])/g, '$1<em>$2</em>');
}

// one line of text -> inline HTML (code spans are literal: no emphasis inside)
export function mdInline(s) {
    return String(s ?? '').split(/(`[^`\n]+`)/).map((p, i) => (i % 2 ? '<code>' + escHtml(p.slice(1, -1)) + '</code>' : emph(escHtml(p)))).join('');
}

export function mdToHtml(src) {
    const lines = String(src ?? '').replace(/\r\n?/g, '\n').split('\n');
    const out = [];
    let para = [];
    let list = null; // {tag, start, items}
    const flushP = () => {
        if (para.length) { out.push('<p>' + para.map(mdInline).join('<br>') + '</p>'); para = []; }
    };
    const flushL = () => {
        if (!list) { return; }
        const start = list.tag === 'ol' && list.start !== 1 ? ' start="' + list.start + '"' : '';
        out.push('<' + list.tag + start + '>' + list.items.map((t) => '<li>' + mdInline(t) + '</li>').join('') + '</' + list.tag + '>');
        list = null;
    };
    for (const line of lines) {
        let m;
        if (!line.trim() || /^\s*([-*_])(\s*\1){2,}\s*$/.test(line)) { flushP(); flushL(); continue; }
        if ((m = /^\s*[-*+\u2022]\s+(.*)$/.exec(line))) {
            flushP();
            if (!list || list.tag !== 'ul') { flushL(); list = {tag: 'ul', start: 1, items: []}; }
            list.items.push(m[1]);
            continue;
        }
        if ((m = /^\s*(\d{1,6})[.)]\s+(.*)$/.exec(line))) {
            flushP();
            if (!list || list.tag !== 'ol') { flushL(); list = {tag: 'ol', start: Number(m[1]), items: []}; }
            list.items.push(m[2]);
            continue;
        }
        if ((m = /^\s{0,3}#{1,6}\s+(.*?)(?:\s+#+)?\s*$/.exec(line))) { flushP(); flushL(); out.push('<p><strong>' + mdInline(m[1]) + '</strong></p>'); continue; }
        if (list && /^\s{2,}\S/.test(line)) { list.items[list.items.length - 1] += ' ' + line.trim(); continue; } // wrapped list item
        flushL();
        para.push(line.trim());
    }
    flushP();
    flushL();
    return out.join('');
}
