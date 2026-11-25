/**
 * ĐUST Quests (testnet, DUST_QUESTS_ENABLED) — KB notes/dust-quests.md.
 *
 * Inert unless the layout printed window.DUST_QUESTS (App\Support\DustQuests::enabled()). Pages stay static (no user
 * data in HTML): everything personal comes from /quests/state with the passkey session. Wallet sessions get
 * {eligible:false} and see nothing (the nav link and the panel widget are passkey-only).
 *
 * Alpine components: dustQuests (the /quests page), questsWidget (passkey account panel), questsAdmin (/quests/admin).
 */
import Alpine from 'alpinejs';

const CFG = window.DUST_QUESTS || null;
const SEEN_KEY = 'fl_quests_seen';

async function api(method, url, data) {
    const r = await window.axios({method, url, data, headers: {Accept: 'application/json'}});
    return r.data;
}
function errorText(e) {
    const d = e && e.response && e.response.data;
    if (d && d.error) { return d.error; }
    if (d && d.message) { return d.message; }
    return (e && e.message) || String(e);
}
export function fmtDust(v) {
    const n = Number(v || 0);
    return 'Đ ' + n.toLocaleString('en-US', {maximumFractionDigits: n % 1 ? 2 : 0});
}
window.fmtDust = fmtDust;

// Streak: tell the server once per UTC day that this passkey account was here.
function pingSeen(address) {
    if (!CFG || !address) { return; }
    const today = new Date().toISOString().slice(0, 10);
    const mark = address + ':' + today;
    try { if (window.localStorage.getItem(SEEN_KEY) === mark) { return; } } catch (e) { /* private mode */ }
    api('post', '/quests/seen').then(() => {
        try { window.localStorage.setItem(SEEN_KEY, mark); } catch (e) { /* private mode */ }
    }).catch(() => {});
}
if (CFG && CFG.enabled) {
    window.addEventListener('server-auth', (ev) => {
        const s = ev.detail || {};
        if (s.authenticated && s.authType === 'passkey') { pingSeen(s.address); }
    });
    window.addEventListener('passkey-signed-in', (ev) => pingSeen((ev.detail || {}).address));
}

const STATUS_LABEL = {
    verified: 'Done · on its way',
    paid: 'Paid',
    pending: 'Under review',
    rejected: 'Not approved',
};

Alpine.data('dustQuests', () => ({
    enabled: !!CFG,
    loaded: false,
    loading: false,
    state: {eligible: false, reason: null, challenges: [], quiz: []},
    busy: '',
    msg: '',
    err: '',
    msgKey: '',
    now: Date.now(),
    tick: null,
    honorReady: {},
    quizOpen: false,
    answers: [],
    shareUrl: '',

    init() {
        if (!this.enabled) { return; }
        this.load();
        this.tick = setInterval(() => { this.now = Date.now(); }, 1000);
        window.addEventListener('server-auth', () => this.load());
        window.addEventListener('passkey-signed-in', () => this.load());
        window.addEventListener('logged-out', () => { this.state = {eligible: false, reason: 'signed_out', challenges: [], quiz: []}; });
        window.addEventListener('passkey-tx', () => setTimeout(() => this.load(), 4000));
        this.discordNotice();
    },
    async load() {
        if (this.loading) { return; }
        this.loading = true;
        try {
            this.state = await api('get', '/quests/state');
            if (this.state.quiz && this.answers.length !== this.state.quiz.length) { this.answers = this.state.quiz.map(() => null); }
        } catch (e) {
            this.err = errorText(e);
        } finally {
            this.loading = false;
            this.loaded = true;
        }
    },
    discordNotice() {
        const m = (window.location.hash || '').match(/discord=([^&]+)/);
        if (!m) { return; }
        const v = decodeURIComponent(m[1]);
        const map = {
            ok: ['Discord connected: quest complete!', ''],
            join: ['', 'Your Discord account is connected, but it is not in the Flovatar server yet. Join the server, then tap "Connect Discord" again.'],
            taken: ['', 'This Discord account is already connected to another Flovatar account.'],
            cancelled: ['', 'Discord sign-in was cancelled.'],
            expired: ['', 'The Discord sign-in expired. Please try again.'],
            error: ['', 'Discord sign-in failed. Please try again.'],
            unavailable: ['', 'The Discord quest is not available yet.'],
            signin: ['', 'Sign in with your passkey first.'],
            ineligible: ['', 'Confirm your email first (passkey account panel).'],
        };
        const [ok, bad] = map[v] || ['', v];
        this.msg = ok;
        this.err = bad;
        this.msgKey = 'discord';
        history.replaceState(null, '', window.location.pathname);
    },
    get cap() { return Number(this.state.cap || (CFG ? CFG.cap : 1000)); },
    get earned() { return Number(this.state.earned || 0); },
    get pct() { return Math.min(100, Math.round((this.earned / (this.cap || 1)) * 100)); },
    get done() { return (this.state.challenges || []).filter((c) => ['verified', 'paid'].includes(c.status)).length; },
    fmt: fmtDust,
    statusLabel(c) { return STATUS_LABEL[c.status] || ''; },
    isOpen(c) { return c.status === 'open'; },
    txUrl(id) { return (this.state.flowscan || 'https://testnet.flowscan.io') + '/tx/' + id; },
    honorWait(c) {
        if (this.honorReady[c.key] !== undefined) { return Math.max(0, Math.ceil((this.honorReady[c.key] - this.now) / 1000)); }
        if (c.wait !== undefined) { return c.wait; }
        return null;
    },
    signIn() { window.dispatchEvent(new CustomEvent('open-signin')); },
    openPanel() { window.dispatchEvent(new CustomEvent('open-passkey-account')); },
    flash(key, ok, bad) { this.msgKey = key; this.msg = ok || ''; this.err = bad || ''; },
    async startHonor(c) {
        window.open(c.url, '_blank', 'noopener');
        try {
            const r = await api('post', '/quests/' + c.key + '/start');
            this.honorReady = {...this.honorReady, [c.key]: Date.now() + (r.wait || 10) * 1000};
        } catch (e) { this.flash(c.key, '', errorText(e)); }
    },
    async claim(c, input) {
        if (this.busy) { return; }
        this.busy = c.key;
        this.flash(c.key, '', '');
        try {
            const r = await api('post', '/quests/' + c.key + '/claim', input || {});
            if (r.state) { this.state = r.state; }
            this.flash(c.key, r.message, '');
            if (c.key === 'lore_quiz') { this.quizOpen = false; }
            window.dispatchEvent(new CustomEvent('quests-updated', {detail: r.state || null}));
        } catch (e) {
            this.flash(c.key, '', errorText(e));
        } finally {
            this.busy = '';
        }
    },
    submitQuiz(c) {
        if (this.answers.some((a) => a === null)) { this.flash(c.key, '', 'Answer all questions first.'); return; }
        this.claim(c, {answers: this.answers.map(Number)});
    },
    submitShare(c) {
        if (!/^https:\/\//i.test(this.shareUrl.trim())) { this.flash(c.key, '', 'Paste the full link of your post (https://…).'); return; }
        this.claim(c, {url: this.shareUrl.trim()});
    },
    shareIntent() {
        const addr = this.state.address || '';
        const url = window.location.origin + '/collection/' + addr;
        const text = 'Meet my Flovatar! I am building in the Megaverse with @flovatar #Flovatar (quest reward: ĐUST)';
        return 'https://x.com/intent/post?text=' + encodeURIComponent(text) + '&url=' + encodeURIComponent(url);
    },
}));

// Progress widget in the passkey account panel
Alpine.data('questsWidget', () => ({
    enabled: !!CFG,
    state: null,
    async load() {
        if (!this.enabled) { return; }
        try { this.state = await api('get', '/quests/state'); } catch (e) { this.state = null; }
    },
    init() {
        window.addEventListener('quests-updated', (ev) => { if (ev.detail) { this.state = ev.detail; } });
    },
    get pct() { return this.state ? Math.min(100, Math.round((Number(this.state.earned || 0) / Number(this.state.cap || 1)) * 100)) : 0; },
    get done() { return this.state ? (this.state.challenges || []).filter((c) => ['verified', 'paid'].includes(c.status)).length : 0; },
    get total() { return this.state ? (this.state.challenges || []).length : 0; },
    fmt: fmtDust,
}));

// Admin page
Alpine.data('questsAdmin', () => ({
    data: null,
    error: '',
    info: '',
    status: 'pending',
    q: '',
    busy: false,
    settings: {},
    async load() {
        this.error = '';
        try {
            this.data = await api('get', '/quests/admin/data?status=' + encodeURIComponent(this.status) + '&q=' + encodeURIComponent(this.q));
            this.settings = {...this.data.settings};
        } catch (e) { this.data = null; this.error = errorText(e); }
    },
    fmt: fmtDust,
    txUrl(id) { return ((this.data && this.data.flowscan) || 'https://testnet.flowscan.io') + '/tx/' + id; },
    async post(url, body, ok) {
        if (this.busy) { return; }
        this.busy = true;
        this.error = '';
        this.info = '';
        try { await api('post', url, body || {}); this.info = ok || 'Saved.'; await this.load(); } catch (e) { this.error = errorText(e); } finally { this.busy = false; }
    },
    approve(c) { this.post('/quests/admin/claims/' + c.id + '/approve', {note: ''}, 'Approved claim #' + c.id); },
    reject(c) {
        const note = window.prompt('Reason shown to the user (optional):', 'Not approved.');
        if (note === null) { return; }
        this.post('/quests/admin/claims/' + c.id + '/reject', {note}, 'Rejected claim #' + c.id);
    },
    freeze(address, frozen) {
        const reason = frozen ? window.prompt('Freeze reason (internal):', 'review') : '';
        if (frozen && reason === null) { return; }
        this.post('/quests/admin/accounts/' + address, {frozen, reason}, (frozen ? 'Frozen ' : 'Unfrozen ') + address);
    },
    capOverride(address) {
        const v = window.prompt('Cap for ' + address + ' in ĐUST (empty = default):', '');
        if (v === null) { return; }
        this.post('/quests/admin/accounts/' + address, {cap_override: v}, 'Cap updated');
    },
    saveChallenge(c) {
        this.post('/quests/admin/challenges/' + c.id, {amount: Number(c.amount), active: !!c.active, title: c.title, sort: Number(c.sort)}, 'Saved ' + c.key);
    },
    saveSettings() {
        const s = this.settings;
        this.post('/quests/admin/settings', {season_budget: Number(s.season_budget), daily_cap: Number(s.daily_cap), per_user_cap: Number(s.per_user_cap), payouts_paused: !!s.payouts_paused, claims_paused: !!s.claims_paused}, 'Settings saved');
    },
    release(b) {
        const v = window.prompt('Only if the worker did NOT send it. Type the batch id to release:', '');
        if (v === null) { return; }
        this.post('/quests/admin/batches/' + b.id + '/release', {confirm: v}, 'Batch released');
    },
}));
