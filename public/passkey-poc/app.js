/* Flovatar passkey PoC (testnet). Plain ES2017, no build step. */
(function () {
    'use strict';

    var CFG = window.PASSKEY_POC || {};
    var state = { me: null, busy: false, lastTx: null, lastError: null, templates: null, pick: null, env: {} };

    function $(id) { return document.getElementById(id); }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    // ---------------------------------------------------------------- base64url
    function b64uToBuf(s) {
        s = s.replace(/-/g, '+').replace(/_/g, '/');
        while (s.length % 4) s += '=';
        var bin = atob(s), out = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
        return out.buffer;
    }
    function bufToB64u(buf) {
        var bytes = new Uint8Array(buf), bin = '';
        for (var i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    // ---------------------------------------------------------------- http
    function csrf() { var m = document.querySelector('meta[name=csrf-token]'); return m ? m.getAttribute('content') : ''; }
    function api(method, url, body) {
        return fetch(url, {
            method: method, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: body ? JSON.stringify(body) : undefined
        }).then(function (r) {
            return r.text().then(function (t) {
                var j = {};
                try { j = t ? JSON.parse(t) : {}; } catch (e) { j = { error: 'Unexpected answer from the server (' + r.status + ')' }; }
                if (r.status === 419) j.error = 'Your session expired. Reload the page and try again.';
                if (j && j.csrf) { var m = document.querySelector('meta[name=csrf-token]'); if (m) m.setAttribute('content', j.csrf); }
                if (!r.ok) { var err = new Error(j.error || j.message || ('Request failed (' + r.status + ')')); err.status = r.status; throw err; }
                return j;
            });
        });
    }

    // ---------------------------------------------------------------- UI helpers
    function setStatus(step, html, cls) { var el = $('status-' + step); if (el) el.innerHTML = html ? '<span class="' + (cls || '') + '">' + html + '</span>' : ''; }
    function txLink(id) { return '<a class="mono" target="_blank" rel="noopener" href="' + CFG.flowscan + '/tx/' + id + '">' + id.slice(0, 10) + '…' + id.slice(-6) + '</a>'; }
    function friendlyWebauthnError(e) {
        var n = e && e.name;
        if (n === 'NotAllowedError') return 'The passkey prompt was cancelled or timed out. Nothing was signed. Try again when you\'re ready.';
        if (n === 'InvalidStateError') return 'This device already has a passkey for your account (probably synced via iCloud Keychain or Google Password Manager). You can just sign in here; no need to add it.';
        if (n === 'NotSupportedError') return 'This device can\'t create the kind of passkey we need (P-256). Try another browser or device.';
        if (n === 'SecurityError') return 'The browser refused the passkey for this address (' + CFG.rpId + ').';
        return (e && e.message) || String(e);
    }
    function busy(on) {
        state.busy = on;
        Array.prototype.forEach.call(document.querySelectorAll('button'), function (b) { if (on) { b.dataset.wasDisabled = b.disabled ? '1' : ''; b.disabled = true; } else { b.disabled = b.dataset.wasDisabled === '1'; } });
    }
    function wrap(step, fn) {
        return function () {
            if (state.busy) return;
            busy(true);
            Promise.resolve().then(fn).catch(function (e) {
                state.lastError = (e && (e.name ? e.name + ': ' : '') + e.message) || String(e);
                setStatus(step, '⚠️ ' + esc(e && e.name ? friendlyWebauthnError(e) : (e.message || e)), 'err');
            }).then(function () { busy(false); render(); });
        };
    }

    // ---------------------------------------------------------------- WebAuthn ceremonies
    function createCredential(options) {
        var pk = options.publicKey;
        pk.challenge = b64uToBuf(pk.challenge);
        pk.user.id = b64uToBuf(pk.user.id);
        pk.excludeCredentials = (pk.excludeCredentials || []).map(function (c) { return { type: 'public-key', id: b64uToBuf(c.id) }; });
        return navigator.credentials.create({ publicKey: pk }).then(function (cred) {
            return {
                id: cred.id,
                clientDataJSON: bufToB64u(cred.response.clientDataJSON),
                attestationObject: bufToB64u(cred.response.attestationObject)
            };
        });
    }
    function getAssertion(challengeB64u, allowIds) {
        var pk = { challenge: b64uToBuf(challengeB64u), rpId: CFG.rpId, userVerification: 'required', timeout: 120000 };
        if (allowIds && allowIds.length) pk.allowCredentials = allowIds.map(function (id) { return { type: 'public-key', id: b64uToBuf(id) }; });
        return navigator.credentials.get({ publicKey: pk }).then(function (a) {
            return {
                id: a.id,
                clientDataJSON: bufToB64u(a.response.clientDataJSON),
                authenticatorData: bufToB64u(a.response.authenticatorData),
                signature: bufToB64u(a.response.signature),
                userHandle: a.response.userHandle ? bufToB64u(a.response.userHandle) : null
            };
        });
    }

    // Safari (iOS/iPadOS/macOS) only shows the passkey sheet when the call comes straight from a tap. Our calls follow a
    // server round trip, so if the first attempt is refused (NotAllowedError) we keep the prepared request and show a
    // button that calls WebAuthn directly from the tap. The same button doubles as "try again" after a real cancel.
    function ceremony(step, label, call) {
        return call().catch(function (e) {
            if (!e || e.name !== 'NotAllowedError') throw e;
            return new Promise(function (resolve, reject) {
                var el = $('status-' + step);
                el.innerHTML = '<span class="wait">👆 Tap the button to ' + esc(label) + ' with Face ID / fingerprint.</span><br>'
                    + '<button class="btn" type="button" data-ceremony="go">Continue with passkey</button> '
                    + '<button class="btn ghost" type="button" data-ceremony="cancel">Cancel</button>';
                el.querySelector('[data-ceremony=go]').onclick = function () {
                    call().then(resolve, function (e2) {
                        if (e2 && e2.name === 'NotAllowedError') { setStatus(step, '', ''); ceremony(step, label, call).then(resolve, reject); return; }
                        reject(e2);
                    });
                };
                el.querySelector('[data-ceremony=cancel]').onclick = function () { var c = new Error('Cancelled. Nothing was signed.'); reject(c); };
            });
        });
    }

    // ---------------------------------------------------------------- transactions
    function waitTx(step, txId, label) {
        state.lastTx = { id: txId, label: label, status: 'Pending' };
        var started = Date.now();
        return new Promise(function (resolve, reject) {
            (function poll() {
                api('GET', '/passkey/tx/' + txId).then(function (r) {
                    state.lastTx.status = r.status;
                    renderDebug();
                    if (r.status === 'Sealed') { setStatus(step, '✅ ' + esc(label) + ' sealed on Flow testnet <span class="txline">tx ' + txLink(txId) + ' <span class="chip Sealed">Sealed</span></span>', 'ok'); return resolve(r); }
                    if (r.status === 'Failed' || r.status === 'Expired') { setStatus(step, '❌ ' + esc(label) + ' failed: ' + esc((r.error || '').slice(0, 400)) + '<span class="txline">tx ' + txLink(txId) + '</span>', 'err'); return reject(new Error('Transaction failed')); }
                    setStatus(step, '<span class="spinner"></span> ' + esc(label) + ': ' + esc(r.status) + '… <span class="txline">tx ' + txLink(txId) + ' <span class="chip">' + esc(r.status) + '</span></span>', 'wait');
                    if (Date.now() - started > 90000) return reject(new Error('Still not sealed after 90 s; check Flowscan.'));
                    setTimeout(poll, 1500);
                }).catch(function () { setTimeout(poll, 2500); });
            })();
        });
    }

    function signAndSend(step, action, params, label) {
        setStatus(step, '<span class="spinner"></span> Preparing the transaction…', 'wait');
        return api('POST', '/passkey/tx/prepare', { action: action, params: params || {} }).then(function (prep) {
            setStatus(step, '👆 Approve the passkey prompt to sign "' + esc(label) + '"…', 'wait');
            return ceremony(step, 'sign "' + label + '"', function () { return getAssertion(prep.challenge, [prep.credentialId]); }).then(function (assertion) {
                setStatus(step, '<span class="spinner"></span> Sending to Flow…', 'wait');
                return api('POST', '/passkey/tx/submit', { token: prep.token, assertion: assertion });
            });
        }).then(function (r) { return waitTx(step, r.txId, label); }).then(refresh);
    }

    // ---------------------------------------------------------------- actions
    var doCreate = wrap(1, function () {
        setStatus(1, '<span class="spinner"></span> Asking your device for a passkey…', 'wait');
        return api('POST', '/passkey/register/options', { email: $('email').value.trim() || null }).then(function (opts) {
            return ceremony(1, 'create your passkey', function () { return createCredential(JSON.parse(JSON.stringify(opts))); });
        }).then(function (att) {
            setStatus(1, '<span class="spinner"></span> Passkey saved. Creating your Flow account on testnet (about 10–20 seconds)…', 'wait');
            return api('POST', '/passkey/register/verify', att);
        }).then(function (r) {
            setStatus(1, '✅ Account created: <span class="mono">' + esc(r.address) + '</span><span class="txline">tx ' + txLink(r.txId) + ' <span class="chip Sealed">Sealed</span></span>', 'ok');
            state.lastTx = { id: r.txId, label: 'Create account', status: 'Sealed' };
            return refresh();
        });
    });

    var doLogin = wrap(1, function () {
        setStatus(1, '<span class="spinner"></span> Choose your passkey…', 'wait');
        return api('POST', '/passkey/login/options').then(function (o) {
            return ceremony(1, 'sign in', function () { return getAssertion(o.publicKey.challenge, []); });
        }).then(function (a) {
            return api('POST', '/passkey/login/verify', a);
        }).then(function (r) {
            setStatus(1, '✅ Signed in as <span class="mono">' + esc(r.address) + '</span> (key #' + esc(r.key_index) + ')', 'ok');
            return refresh();
        });
    });

    var doLogout = wrap(1, function () { return api('POST', '/passkey/logout').then(function () { setStatus(1, 'Signed out.', ''); state.me = null; return refresh(); }); });
    var doSetup = wrap(2, function () { return signAndSend(2, 'setup', {}, 'Set up collections'); });
    var doGift = wrap(3, function () {
        setStatus(3, '<span class="spinner"></span> Sending your starter Flobit (about 10 seconds)…', 'wait');
        return api('POST', '/passkey/gift').then(function (r) {
            state.lastTx = { id: r.txId, label: 'Starter gift', status: 'Sealed' };
            setStatus(3, '🎁 You received <b>' + esc(r.item.name) + '</b> (' + esc(r.item.category) + ' #' + esc(r.item.id) + ')<span class="txline">tx ' + txLink(r.txId) + ' <span class="chip Sealed">Sealed</span></span>', 'ok');
            return refresh();
        });
    });
    function doSend(id, name) {
        return wrap(4, function () {
            var to = $('send-to').value.trim() || state.me.sponsor;
            if (!/^0x[0-9a-fA-F]{16}$/.test(to)) throw new Error('Enter a Flow address like 0x1234567890abcdef');
            return signAndSend(4, 'send_flobit', { componentId: id, to: to }, 'Send ' + name);
        })();
    }
    var doCreateFlovatar = wrap(4, function () {
        var spark = sparks()[0];
        if (!spark || !state.pick) throw new Error('No Spark available');
        var p = { spark: spark.id };
        ['body', 'hair', 'eyes', 'nose', 'mouth', 'clothing'].forEach(function (c) { p[c] = state.pick[c].id; });
        return api('POST', '/passkey/combination', p).then(function (r) {
            if (r.taken) { shuffle(); throw new Error('Someone already has that exact look. We shuffled it for you, try again.'); }
            return signAndSend(4, 'create_flovatar', p, 'Create Flovatar');
        });
    });

    var doAddDevice = wrap(5, function () {
        setStatus(5, '<span class="spinner"></span> Saving a passkey on this device…', 'wait');
        return api('POST', '/passkey/device/options').then(function (opts) {
            return ceremony(5, 'save a passkey on this device', function () { return createCredential(JSON.parse(JSON.stringify(opts))); });
        }).then(function (att) {
            return api('POST', '/passkey/device/verify', att);
        }).then(function (r) {
            setStatus(5, '✅ Passkey saved on this device (' + esc(r.label) + '). Now approve it with the passkey you signed in with…', 'ok');
            return refresh().then(function () { return approve(r.pending, r.label); });
        });
    });
    var doTestKey = wrap(5, function () { return signAndSend(5, 'setup', {}, 'No-op test signed by key #' + state.me.credential.key_index); });
    function approve(pendingId, label) {
        return signAndSend(5, 'add_key', { credential: pendingId }, 'Add device ' + (label || '')).then(function () {
            return new Promise(function (res) { setTimeout(res, 800); }).then(refresh);
        });
    }

    // ---------------------------------------------------------------- builder
    function sparks() { var s = state.me && state.me.state; return s ? (s.components || []).filter(function (c) { return c.category === 'spark'; }) : []; }
    function shuffle() {
        if (!state.templates) return;
        var pick = {};
        ['body', 'hair', 'eyes', 'nose', 'mouth', 'clothing'].forEach(function (cat) {
            var opts = state.templates.filter(function (t) { return t.category === cat && String(t.series) === '1' && t.rarity === 'common'; });
            if (!opts.length) opts = state.templates.filter(function (t) { return t.category === cat; });
            pick[cat] = opts[Math.floor(Math.random() * opts.length)];
        });
        state.pick = pick;
        $('builder').innerHTML = Object.keys(pick).map(function (c) { return '<div><b>' + c + '</b>' + esc(pick[c] ? pick[c].name : '?') + '</div>'; }).join('');
    }
    function loadTemplates() {
        if (state.templates) return Promise.resolve();
        return api('GET', '/passkey/templates').then(function (r) { state.templates = r.templates || []; shuffle(); });
    }

    // ---------------------------------------------------------------- render
    function refresh() { return api('GET', '/passkey/me').then(function (me) { state.me = me; render(); }); }

    function stepState(id, done, unlocked) {
        var el = $(id); el.classList.toggle('done', !!done); el.classList.toggle('locked', !unlocked);
    }

    function render() {
        var me = state.me || {};
        var auth = !!me.authenticated;
        var st = me.state || {};
        $('signed-out').classList.toggle('hidden', auth);
        $('signed-in').classList.toggle('hidden', !auth);
        $('step1-title').textContent = auth ? 'Your account' : 'Create your account';
        if (me.stock) $('stock-line').textContent = 'Starter stock right now: ' + me.stock.flobits + ' Flobit(s), ' + me.stock.sparks + ' Spark(s).';
        if (auth) {
            $('addr').textContent = me.address;
            $('lnk-wallet').href = '/collection/' + me.address;
            $('lnk-flowscan').href = CFG.flowscan + '/account/' + me.address;
            $('signed-in-as').textContent = 'Signed in with the passkey of ' + (me.credential.label || 'this device') + ' (key #' + me.credential.key_index + ')' + (me.email ? ' · ' + me.email : '');
            if (!$('send-to').value) $('send-to').value = me.sponsor;
        }
        var initialized = !!st.initialized;
        var comps = st.components || [];
        stepState('step-1', auth, true);
        stepState('step-2', initialized, auth);
        stepState('step-3', !!me.gift_tx, auth && initialized);
        stepState('step-4', (st.flovatars || []).length > 0, auth && initialized);
        var keysOn = (me.keys || []).filter(function (k) { return !k.revoked; }).length;
        stepState('step-5', keysOn > 1, auth);
        if (!state.busy) {
            $('btn-setup').disabled = !auth || initialized;
            $('btn-setup').textContent = initialized ? 'Collections ready' : 'Set up with Face ID';
            $('btn-gift').disabled = !auth || !initialized || !!me.gift_tx;
            $('btn-gift').textContent = me.gift_tx ? 'Gift received' : 'Send me a starter Flobit';
            $('btn-add-device').disabled = !auth;
            $('btn-test-key').disabled = !auth || !initialized;
        }
        if (me.gift_tx && !$('status-3').innerHTML) setStatus(3, '🎁 Gift received <span class="txline">tx ' + txLink(me.gift_tx) + '</span>', 'ok');
        if (initialized && !$('status-2').innerHTML) setStatus(2, '✅ Collections are set up.', 'ok');

        // items
        $('items').innerHTML = comps.length ? comps.map(function (c) {
            return '<li><span><span class="rar ' + esc(c.category === 'spark' ? 'spark' : c.rarity) + '">' + esc(c.category) + '</span> ' + esc(c.name) + ' <span class="hint">#' + esc(c.id) + '</span></span>'
                + '<button class="btn ghost" data-send="' + esc(c.id) + '" data-name="' + esc(c.name) + '">Send (Face ID)</button></li>';
        }).join('') : '<li class="hint">No Flobits yet' + (initialized ? ' (get one in step 3).' : '.') + '</li>';
        Array.prototype.forEach.call(document.querySelectorAll('[data-send]'), function (b) {
            b.onclick = function () { doSend(b.getAttribute('data-send'), b.getAttribute('data-name')); };
            if (state.busy) b.disabled = true;
        });
        var hasSpark = sparks().length > 0;
        $('builder-box').classList.toggle('hidden', !hasSpark);
        if (hasSpark) loadTemplates().catch(function () {});
        var fl = st.flovatars || [];
        $('flovatars-line').innerHTML = fl.length ? 'Your Flovatars: ' + fl.map(function (f) { return '<a href="/flovatars/' + esc(f.id) + '/' + esc(me.address) + '" target="_blank" rel="noopener">#' + esc(f.id) + (f.name ? ' ' + esc(f.name) : '') + '</a>'; }).join(', ') : '';

        // pending devices + keys
        var pend = (me.credentials || []).filter(function (c) { return c.status === 'pending'; });
        $('pending-box').classList.toggle('hidden', !pend.length);
        $('pending').innerHTML = pend.map(function (c) {
            return '<li><span>' + esc(c.label || 'Device') + ' <span class="hint">' + esc(c.public_key) + '</span></span>'
                + (c.this ? '' : '<button class="btn ghost" data-approve="' + c.id + '" data-label="' + esc(c.label) + '">Approve (Face ID)</button>') + '</li>';
        }).join('');
        Array.prototype.forEach.call(document.querySelectorAll('[data-approve]'), function (b) {
            b.onclick = wrap(5, function () { return approve(b.getAttribute('data-approve'), b.getAttribute('data-label')); });
            if (state.busy) b.disabled = true;
        });
        var creds = me.credentials || [];
        $('keys').innerHTML = auth ? '<tr><th>#</th><th>Device</th><th>Key</th><th>Weight</th></tr>' + (me.keys || []).map(function (k) {
            var c = creds.filter(function (x) { return x.key_index === k.index; })[0];
            return '<tr><td>' + k.index + '</td><td>' + esc(c ? c.label : '?') + (c && c.this ? ' <span class="chip">this device</span>' : '') + (k.revoked ? ' <span class="chip Failed">revoked</span>' : '') + '</td><td class="mono">' + esc(k.public_key.slice(0, 12)) + '…</td><td>' + k.weight + '</td></tr>';
        }).join('') : '<tr><td class="hint">Sign in to see your keys.</td></tr>';
        renderDebug();
    }

    function renderDebug() {
        var me = state.me || {};
        var d = {
            address: me.address || null,
            signedInWith: me.credential || null,
            keysOnChain: (me.keys || []).map(function (k) { return { index: k.index, alg: k.signing_algorithm + '/' + k.hashing_algorithm, weight: k.weight, seq: k.sequence_number, revoked: k.revoked, publicKey: k.public_key }; }),
            balanceFLOW: me.balance,
            storage: me.state ? me.state.storageUsed + ' / ' + me.state.storageCapacity + ' bytes' : null,
            lastTx: state.lastTx ? { label: state.lastTx.label, status: state.lastTx.status, id: state.lastTx.id, flowscan: CFG.flowscan + '/tx/' + state.lastTx.id } : null,
            recentTxs: me.transactions || [],
            sponsor: me.sponsor, starterStock: me.stock,
            rpId: CFG.rpId, env: state.env, lastError: state.lastError, userAgent: navigator.userAgent
        };
        $('debug-pre').textContent = JSON.stringify(d, null, 2);
    }

    // ---------------------------------------------------------------- boot
    function boot() {
        if (!window.PublicKeyCredential || !navigator.credentials) {
            $('unsupported').classList.remove('hidden');
            $('btn-create').disabled = true; $('btn-login').disabled = true;
        } else {
            if (PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) {
                PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().then(function (v) { state.env.platformAuthenticator = v; renderDebug(); }).catch(function () {});
            }
            if (PublicKeyCredential.isConditionalMediationAvailable) {
                PublicKeyCredential.isConditionalMediationAvailable().then(function (v) { state.env.conditionalMediation = v; renderDebug(); }).catch(function () {});
            }
        }
        $('btn-create').onclick = doCreate;
        $('btn-login').onclick = doLogin;
        $('btn-logout').onclick = doLogout;
        $('btn-setup').onclick = doSetup;
        $('btn-gift').onclick = doGift;
        $('btn-shuffle').onclick = function () { shuffle(); };
        $('btn-create-flovatar').onclick = doCreateFlovatar;
        $('btn-add-device').onclick = doAddDevice;
        $('btn-test-key').onclick = doTestKey;
        refresh().catch(function (e) { state.lastError = e.message; renderDebug(); });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
