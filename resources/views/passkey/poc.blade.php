<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Passkey demo · Flovatar testnet</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=Staatliches&display=swap" rel="stylesheet">
    <style>
        :root {
            --night: #180830; --night-violet: #1A083A; --indigo: #411793; --royal: #5F37CB; --pink: #EF2AC1;
            --sky: #91E3F9; --yellow: #FFEE50; --yellow-shade: #CCBB1D; --ink: #1A1E2D; --panel: #212741;
            --muted: #9DA4C7; --green: #52F618; --red: #F84F64; --cyan: #23EDFC; --white: #fff;
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            margin: 0; background: var(--night); color: var(--white);
            font-family: 'Roboto Condensed', 'Arial Narrow', Arial, sans-serif; font-size: 17px; line-height: 1.45;
            background-image: radial-gradient(circle at 20% -10%, rgba(95,55,203,.55), transparent 55%), radial-gradient(circle at 110% 30%, rgba(239,42,193,.25), transparent 45%);
            background-attachment: fixed; padding-bottom: 60px;
        }
        a { color: var(--sky); }
        .wrap { max-width: 720px; margin: 0 auto; padding: 18px 16px calc(24px + env(safe-area-inset-bottom)); }
        header { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
        header img { height: 34px; }
        .badge { font-family: 'Staatliches', sans-serif; letter-spacing: .04em; background: var(--pink); color: var(--white); padding: 2px 10px; border-radius: 999px; font-size: 15px; }
        h1, h2 { font-family: 'Staatliches', 'Arial Narrow', sans-serif; font-weight: 400; letter-spacing: .02em; margin: 0; }
        h1 { font-size: 40px; line-height: 1.05; margin: 18px 0 8px; }
        h1 em { font-style: normal; color: var(--yellow); }
        h2 { font-size: 26px; display: flex; align-items: center; gap: 10px; }
        .lead { color: #E4E6F5; margin: 0 0 18px; }
        .card { background: rgba(33,39,65,.92); border: 1px solid rgba(174,215,247,.14); border-radius: 10px; padding: 18px 16px; margin: 14px 0; }
        .card.done { border-color: rgba(82,246,24,.45); }
        .card.locked { opacity: .55; }
        .num { font-family: 'Roboto Condensed', Arial, sans-serif; font-weight: 700; display: inline-flex; width: 32px; height: 32px; border-radius: 50%; background: var(--indigo); align-items: center; justify-content: center; font-size: 19px; flex: none; }
        .done .num { background: var(--green); color: var(--ink); }
        p { margin: 10px 0; }
        .hint { color: var(--muted); font-size: 15px; }
        .prompt { display: flex; gap: 10px; align-items: flex-start; background: rgba(65,23,147,.35); border-left: 3px solid var(--sky); padding: 10px 12px; border-radius: 6px; font-size: 15px; margin: 12px 0; }
        .prompt b { color: var(--sky); }
        label { display: block; font-weight: 700; margin: 8px 0 4px; }
        input[type=email], input[type=text] { width: 100%; font: inherit; padding: 12px; border-radius: 5px; border: 1px solid #4a5280; background: #12152a; color: var(--white); }
        .btn {
            -webkit-appearance: none; appearance: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            font-family: 'Staatliches', sans-serif; font-size: 21px; letter-spacing: .03em; color: var(--ink); background: var(--yellow);
            border: 2px solid var(--ink); border-radius: 6px; padding: 12px 18px; margin: 10px 8px 0 0; cursor: pointer;
            box-shadow: 5px 5px 0 0 var(--ink); min-height: 50px; touch-action: manipulation; text-decoration: none;
        }
        .btn:active { transform: translate(3px, 3px); box-shadow: 2px 2px 0 0 var(--ink); }
        .btn.secondary { background: var(--sky); }
        .btn.ghost { background: transparent; color: var(--white); border-color: var(--muted); box-shadow: none; font-size: 18px; min-height: 42px; padding: 8px 14px; }
        .btn[disabled] { opacity: .5; cursor: not-allowed; }
        .btn.full { width: 100%; margin-right: 0; }
        .row { display: flex; flex-wrap: wrap; gap: 0 8px; align-items: center; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 14px; word-break: break-all; }
        .status { margin-top: 12px; font-size: 15px; }
        .status .ok { color: var(--green); } .status .err { color: var(--red); } .status .wait { color: var(--yellow); }
        .txline { display: block; margin-top: 6px; }
        .chip { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 13px; background: #343B5A; margin-right: 4px; }
        .chip.Sealed { background: var(--green); color: var(--ink); } .chip.Failed, .chip.Expired { background: var(--red); }
        .items { list-style: none; padding: 0; margin: 10px 0; }
        .items li { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,.08); flex-wrap: wrap; }
        .rar { font-size: 12px; text-transform: uppercase; padding: 1px 6px; border-radius: 4px; background: #F5F5F5; color: var(--ink); }
        .rar.rare { background: var(--cyan); } .rar.epic { background: var(--yellow); } .rar.legendary { background: #EB3CF2; } .rar.spark { background: #B196FE; }
        .builder { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 12px; margin: 10px 0; }
        .builder div { font-size: 15px; } .builder b { display: block; color: var(--muted); font-weight: 400; font-size: 13px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 6px 4px; border-bottom: 1px solid rgba(255,255,255,.08); vertical-align: top; }
        details.debug { margin-top: 22px; background: #0e0b1f; border: 1px dashed #4a5280; border-radius: 8px; padding: 10px 12px; }
        details.debug summary { cursor: pointer; font-family: 'Staatliches', sans-serif; font-size: 19px; color: var(--muted); }
        details.debug pre { white-space: pre-wrap; word-break: break-all; font-size: 12px; color: #cfd3ee; max-height: 360px; overflow: auto; }
        .spinner { width: 18px; height: 18px; border: 3px solid rgba(255,255,255,.25); border-top-color: var(--yellow); border-radius: 50%; display: inline-block; animation: spin 1s linear infinite; vertical-align: -3px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .spinner { animation: none; } }
        .hidden { display: none !important; }
        .warn { background: rgba(248,79,100,.15); border: 1px solid var(--red); border-radius: 8px; padding: 10px 12px; }
        footer { color: var(--muted); font-size: 14px; margin-top: 28px; }
        @media (min-width: 640px) { h1 { font-size: 52px; } .card { padding: 22px 24px; } }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <a href="/"><img src="/images/flovatar-logo.svg" alt="Flovatar"></a>
        <span class="badge">Testnet demo</span>
    </header>

    <h1>Your Flovatar account, <em>no wallet needed</em></h1>
    <p class="lead">Sign up with a passkey: Face ID, Touch ID, your fingerprint or your screen lock. No app to install, no seed phrase, no fees. Every step below is a real transaction on the Flow <b>testnet</b>; nothing here is real money and items can be reset.</p>

    <div id="unsupported" class="warn hidden">This browser can't use passkeys. Try Safari on iPhone/iPad (iOS 16+), Chrome on Android, or a recent Chrome, Edge or Safari on a computer.</div>

    {{-- Step 1: create / sign in --}}
    <section class="card" id="step-1">
        <h2><span class="num">1</span> <span id="step1-title">Create your account</span></h2>
        <div id="signed-out">
            <label for="email">Email <span class="hint">(required)</span></label>
            <input id="email" type="email" required maxlength="190" inputmode="email" autocomplete="email" placeholder="you@example.com">
            <p class="hint">We email you a 6-digit code to confirm the address (testnet: from info@flovatar.com).</p>
            <div id="code-box" class="hidden">
                <label for="email-code">6-digit code from the email</label>
                <input id="email-code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]*" placeholder="123456">
                <p class="hint"><button class="btn secondary" id="btn-resend" type="button">Send a new code</button></p>
            </div>
            <button class="btn full" id="btn-create">Send me a code</button>
            <div class="prompt"><span>🔐</span><span>Your device will ask to <b>save a passkey for {{ $rpId }}</b>. Confirm with Face ID, Touch ID, fingerprint or your PIN. On a computer without a fingerprint reader you'll get a <b>QR code</b>: scan it with your phone and approve there.</span></div>
            <p style="margin-top:18px">Already created one? <button class="btn secondary" id="btn-login">Sign in with passkey</button></p>
            <p class="hint">Signing in on a new computer or tablet? Pick <b>"Use a phone or tablet"</b> (or "Other device") in the passkey window and scan the QR code with the phone that has your passkey.</p>
        </div>
        <div id="signed-in" class="hidden">
            <p>✅ Signed in. Your Flow account:</p>
            <p class="mono" id="addr"></p>
            <div class="row">
                <a class="btn ghost" id="lnk-wallet" target="_blank" rel="noopener">My Flovatar wallet page</a>
                <a class="btn ghost" id="lnk-flowscan" target="_blank" rel="noopener">See it on Flowscan</a>
                <button class="btn ghost" id="btn-logout">Sign out</button>
            </div>
            <p class="hint" id="signed-in-as"></p>
        </div>
        <div class="status" id="status-1"></div>
    </section>

    {{-- Step 2: setup --}}
    <section class="card locked" id="step-2">
        <h2><span class="num">2</span> Set up your Flovatar collections</h2>
        <p>Before you can hold Flovatars, Flobits and ĐUST, your account needs its collections. This is the same "initialize" transaction the site always uses, but <b>you sign it with your passkey</b> and Flovatar pays the fee.</p>
        <div class="prompt"><span>👆</span><span>You'll see a <b>sign-in prompt for {{ $rpId }}</b> (Face ID / fingerprint). That approval is your signature on the transaction.</span></div>
        <button class="btn" id="btn-setup" disabled>Set up with Face ID</button>
        <div class="status" id="status-2"></div>
    </section>

    {{-- Step 3: gift --}}
    <section class="card locked" id="step-3">
        <h2><span class="num">3</span> Get a starter Flobit</h2>
        <p>Flovatar sends a starter Flobit to your new account (a Spark when we have one in stock). No prompt on your side: we sign this one.</p>
        <button class="btn" id="btn-gift" disabled>Send me a starter Flobit</button>
        <p class="hint" id="stock-line"></p>
        <div class="status" id="status-3"></div>
    </section>

    {{-- Step 4: user action --}}
    <section class="card locked" id="step-4">
        <h2><span class="num">4</span> Use it, signed with Face ID</h2>
        <div id="builder-box" class="hidden">
            <p><b>You have a Spark!</b> Pick a look and create your own Flovatar. The Spark is used up and a brand-new Flovatar lands in your account.</p>
            <div class="builder" id="builder"></div>
            <div class="row">
                <button class="btn ghost" id="btn-shuffle">🎲 Shuffle</button>
                <button class="btn" id="btn-create-flovatar">Create my Flovatar</button>
            </div>
        </div>
        <div id="items-box">
            <p>Your Flobits. Send one on to someone else (by default back to the starter pool, so the next tester gets it too).</p>
            <ul class="items" id="items"><li class="hint">No Flobits yet.</li></ul>
            <label for="send-to">Send to address</label>
            <input id="send-to" type="text" class="mono" autocomplete="off" spellcheck="false">
        </div>
        <div class="prompt"><span>👆</span><span>Each button opens the passkey prompt. Approving it signs the transaction; Flovatar pays the fee.</span></div>
        <div class="status" id="status-4"></div>
        <p class="hint" id="flovatars-line"></p>
    </section>

    {{-- Step 5: devices --}}
    <section class="card locked" id="step-5">
        <h2><span class="num">5</span> Add another device</h2>
        <p>Your account can have a passkey on several devices. Each one is a separate key on your Flow account, so losing one device doesn't lock you out.</p>
        <ol class="hint" style="padding-left:20px">
            <li>On the <b>new</b> device (iPad, laptop, another phone) open <span class="mono">https://{{ $rpId }}/passkey</span>.</li>
            <li>Tap <b>Sign in with passkey</b> and choose <b>"Use a phone or tablet"</b>; scan the QR code with the device that already has your passkey and approve there.</li>
            <li>Tap <b>Add this device</b>: the new device saves its own passkey.</li>
            <li>Approve it with the passkey that is already on your account (QR code again if that is your phone). After about 10 seconds the new key is on your account.</li>
        </ol>
        <p class="hint">iPhone, iPad and Mac with the same Apple ID (or Android devices with the same Google account) already share passkeys: there you can simply sign in, no need to add the device.</p>
        <div class="row">
            <button class="btn" id="btn-add-device" disabled>Add this device</button>
            <button class="btn ghost" id="btn-test-key" disabled>Test my key (sign a no-op tx)</button>
        </div>
        <div id="pending-box" class="hidden">
            <p><b>Waiting for approval:</b></p>
            <ul class="items" id="pending"></ul>
        </div>
        <div class="status" id="status-5"></div>
        <h2 style="font-size:20px;margin-top:16px">Keys on your account</h2>
        <table id="keys"><tr><td class="hint">Sign in to see your keys.</td></tr></table>
    </section>

    <details class="debug" id="debug">
        <summary>Debug panel</summary>
        <pre id="debug-pre">loading…</pre>
    </details>

    <footer>
        Proof of concept for walletless onboarding on Flow (passkeys, FLIP-264). Flow testnet only. Fees are paid by the Flovatar sponsor account <span class="mono">{{ $sponsor }}</span>.
    </footer>
</div>
@includeIf('partials.env-banner')
<script>
    window.PASSKEY_POC = { flowscan: @json($flowscan), rpId: @json($rpId), sponsor: @json($sponsor) };
</script>
<script src="/passkey-poc/app.js?v={{ @filemtime(public_path('passkey-poc/app.js')) }}"></script>
</body>
</html>
