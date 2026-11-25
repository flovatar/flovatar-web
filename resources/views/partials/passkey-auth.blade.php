{{--
    Passkey sign-in (testnet, PASSKEY_LOGIN_ENABLED) — included by layouts/guest only when App\Support\PasskeyLogin::enabled().
    Static-safe: no user data here; state comes from resources/js/passkey.js (Alpine component passkeyAuth).
--}}
<div x-data="passkeyAuth"
     @open-signin.window="openSignin()"
     @open-passkey-account.window="openAccount()"
     @passkey-confirm.window="showConfirm($event.detail)"
     @passkey-confirm-close.window="confirmOpen = false"
     @logged-out.window="panelOpen = false; account = null"
     @profile-loaded.window="dust = $event.detail.profile ? $event.detail.profile.dustBalance : null">

    {{-- Sign in: Email + passkey | Wallet --}}
    <div id="signin-modal" class="modal-wrapper passkey-modal jshidden" x-show="signinOpen" @keydown.escape.window="closeSignin()">
        <div class="modal-content">
            <div class="modal-inner">
                <div class="msg-box" @click.outside="closeSignin()">
                    <a href="#" class="close" @click.prevent.stop="closeSignin()"></a>

                    <div x-show="step === 'choice'">
                        <div class="heading"><h2>Sign in</h2></div>
                        <ul class="btn-list">
                            <li>
                                <button type="button" @click.prevent.stop="step = 'passkey'; error = ''" class="btn-grad btn-grad-orange" data-signin="passkey">
                                    <span class="ico-wrap passkey-ico" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="7" r="4"/><path d="M2 21v-1a6 6 0 0 1 9.5-4.9"/><circle cx="18" cy="13" r="2.5"/><path d="M18 15.5V22l1.5-1.2L18 19.5l1.5-1.2"/></svg>
                                    </span>
                                    <span class="lbl">Email + passkey<small>No wallet needed · Face ID / fingerprint</small></span>
                                </button>
                            </li>
                            <li>
                                <button type="button" @click.prevent.stop="useWallet()" class="btn-grad btn-grad-blue" data-signin="wallet">
                                    <span class="ico-wrap"><img src="/images/icon-flow.svg" width="56" alt=""></span>
                                    <span class="lbl">Wallet<small>Flow Wallet or Dapper</small></span>
                                </button>
                            </li>
                        </ul>
                        <p class="passkey-note">New to Flow? Pick <b>Email + passkey</b>: we create a Flow account secured by your device, and we pay the network fees.</p>
                    </div>

                    <div x-show="step === 'passkey'">
                        <div class="heading"><h2>Email + passkey</h2></div>
                        <template x-if="!supported">
                            <p class="passkey-error">This browser does not support passkeys. Try a recent Safari, Chrome, Edge or Firefox, or sign in with a wallet.</p>
                        </template>
                        <form class="passkey-form" novalidate @submit.prevent.stop="signUp()">
                            <h3 class="passkey-form-title">New here? Create your account</h3>
                            <label for="passkey-email">Your email <span class="req">required</span></label>
                            <input id="passkey-email" x-ref="passkeyEmail" type="email" inputmode="email" required maxlength="190" x-model.trim="email" autocomplete="email" placeholder="you@example.com" :disabled="busy || !!codeSentTo"
                                   :class="{ 'is-invalid': emailError }" :aria-invalid="emailError ? 'true' : 'false'" aria-describedby="passkey-email-help passkey-email-error"
                                   @input="emailError = ''" @blur="email && !codeSentTo && validateEmail()">
                            <p id="passkey-email-error" class="passkey-field-error" x-show="emailError" x-text="emailError" role="alert"></p>
                            <p id="passkey-email-help" class="passkey-field-help" x-show="!codeSentTo">We send you a 6-digit code to confirm it. We use it for important account updates and to help you recover your account. It is never shown publicly.</p>
                            <template x-if="codeSentTo && !existingEmail">
                                <div class="passkey-code">
                                    <label for="passkey-code">6-digit code from the email</label>
                                    <input id="passkey-code" x-ref="passkeyCode" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" x-model.trim="code" placeholder="123456" :disabled="busy" data-passkey="code">
                                    <p class="passkey-field-help">
                                        <a href="#" @click.prevent.stop="if(!resendIn && !busy){ codeSentTo = ''; sendCode() }" :class="{ 'is-disabled': resendIn }" data-passkey="resend"><span x-text="resendIn ? 'Send a new code in ' + resendIn + ' s' : 'Send a new code'"></span></a>
                                        · <a href="#" @click.prevent.stop="changeEmail()">Change email</a>
                                    </p>
                                </div>
                            </template>
                            <button type="submit" class="btn btn-pink" :disabled="busy" data-passkey="signup" x-show="!existingEmail"><span class="inner" x-text="codeSentTo ? 'Confirm & create passkey' : 'Send me a code'">Send me a code</span></button>
                            <p class="passkey-info" x-show="existingEmail">You already have an account with this email: use <b>Sign in with my passkey</b> below. <a href="#" @click.prevent.stop="changeEmail()">Use another email</a></p>
                        </form>
                        <p class="passkey-info" x-show="info" x-text="info"></p>
                        <p class="passkey-error" x-show="error" x-text="error"></p>
                        <div class="passkey-or"><span>already have an account?</span></div>
                        <button type="button" class="btn" @click.prevent.stop="signIn()" :disabled="busy" data-passkey="signin"><span class="inner">Sign in with my passkey</span></button>
                        <p class="passkey-note">Your passkey from another device works too: choose "phone or tablet" in the prompt and scan the QR code.</p>
                        <p><a href="#" class="passkey-back" @click.prevent.stop="step = 'choice'; error = ''" x-show="!busy">&larr; Back</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Passkey approval fallback (Safari needs a fresh tap for WebAuthn) --}}
    <div id="passkey-confirm-modal" class="modal-wrapper passkey-modal passkey-confirm jshidden" x-show="confirmOpen">
        <div class="modal-content">
            <div class="modal-inner">
                <div class="msg-box">
                    <div class="heading"><h2>Approve with your passkey</h2></div>
                    <p x-text="confirmRetry ? 'The prompt was closed. Tap again to try once more.' : 'Tap the button to continue with Face ID / fingerprint.'"></p>
                    <p class="passkey-action" x-text="confirmLabel"></p>
                    <div class="btn-hold">
                        <button type="button" class="btn btn-pink" @click.prevent.stop="confirmGo()" data-passkey="confirm"><span class="inner">Continue with passkey</span></button>
                        <button type="button" class="btn" @click.prevent.stop="confirmCancel()"><span class="inner">Cancel</span></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Passkey account panel --}}
    <div id="passkey-account-modal" class="modal-wrapper passkey-modal passkey-account jshidden" x-show="panelOpen" @keydown.escape.window="if(!busy){panelOpen = false}">
        <div class="modal-content">
            <div class="modal-inner">
                <div class="msg-box">
                    <a href="#" class="close" @click.prevent.stop="if(!busy){panelOpen = false}"></a>
                    <div class="heading"><h2>Your passkey account</h2></div>
                    <p class="passkey-error" x-show="accountError" x-text="accountError"></p>
                    <template x-if="account && account.authenticated">
                        <div class="passkey-account-body">
                            <dl class="passkey-dl">
                                <dt>Flow address</dt>
                                <dd><span class="mono" x-text="account.address" data-passkey="address"></span> <a href="#" @click.prevent.stop="copy(account.address)">copy</a> · <a :href="accountUrl()" target="_blank" rel="noopener">Flowscan</a></dd>
                                <dt>Signed in with</dt>
                                <dd>Passkey · <span x-text="account.credential.label"></span> (key #<span x-text="account.credential.key_index"></span>)</dd>
                                <dt>Email</dt>
                                <dd><span x-text="account.email || 'not set'"></span> <span class="chip" x-show="account.email_verified">verified</span><span class="chip warn" x-show="!account.email_verified">not verified</span></dd>
                                <dt>Balances</dt>
                                <dd><span x-text="fmt(account.flow_balance, 4)"></span> FLOW · <span x-text="fmt(dust)"></span> ĐUST</dd>
                            </dl>

@if(\App\Support\DustQuests::enabled())
                            @include('partials.quests-widget')
@endif
                            <template x-if="!account.email_verified">
                                <div class="passkey-verify" data-passkey="verify-email">
                                    <h3>Verify your email</h3>
                                    <p class="passkey-note" x-text="account.recovered ? 'We restored your account from the blockchain. Confirm your email so we can reach you and you can join ĐUST quests.' : 'Confirm your email with a 6-digit code: it keeps your account recoverable and is needed for ĐUST quests.'"></p>
                                    <form class="passkey-form" novalidate @submit.prevent.stop="verifySentTo ? confirmVerifyCode() : sendVerifyCode()">
                                        <input type="email" inputmode="email" maxlength="190" autocomplete="email" x-model.trim="verifyEmail" placeholder="you@example.com" :disabled="busy || !!verifySentTo" aria-label="Your email" data-passkey="verify-email-input">
                                        <template x-if="verifySentTo">
                                            <input type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" x-model.trim="verifyCode" placeholder="6-digit code" :disabled="busy" aria-label="6-digit code" data-passkey="verify-code">
                                        </template>
                                        <button type="submit" class="btn btn-pink btn-sm" :disabled="busy" data-passkey="verify-submit"><span class="inner" x-text="verifySentTo ? 'Confirm code' : 'Send me a code'">Send me a code</span></button>
                                        <a href="#" x-show="verifySentTo" @click.prevent.stop="verifySentTo = ''; verifyCode = ''">Change email</a>
                                    </form>
                                </div>
                            </template>

                            <h3>Deposit</h3>
                            <p class="passkey-note">Network fees are on us. To buy with FLOW or ĐUST, send tokens to your address above. @if(config('services.flow.network') === 'testnet')Testnet: get free FLOW from the <a :href="faucetUrl()" target="_blank" rel="noopener">Flow faucet</a>. @endif ĐUST also comes from claiming in your collection.</p>

                            <template x-if="!account.gift_tx">
                                <p><button type="button" class="btn btn-green" @click.prevent.stop="claimGift()" :disabled="busy" data-passkey="gift"><span class="inner">Get my starter Flobit</span></button></p>
                            </template>

                            <h3>Devices (passkeys)</h3>
                            <ul class="passkey-devices">
                                <template x-for="d in account.devices" :key="d.id">
                                    <li>
                                        <span x-text="d.label"></span>
                                        <span class="muted" x-show="d.key_index !== null">· key #<span x-text="d.key_index"></span></span>
                                        <span class="chip" x-show="d.this">this device</span>
                                        <span class="chip warn" x-show="d.status === 'pending'">waiting for approval</span>
                                        <button type="button" class="btn btn-sm" x-show="d.status === 'pending' && !d.this" @click.prevent.stop="approveDevice(d.id)" :disabled="busy"><span class="inner">Approve</span></button>
                                    </li>
                                </template>
                            </ul>
                            <p class="passkey-note">To use another phone or computer: open this site there, choose Sign in → Email + passkey → "Sign in with my passkey" and scan the QR code with this device. Then open this panel on the new device and tap <b>Add this device</b>.</p>
                            <p><button type="button" class="btn" @click.prevent.stop="addThisDevice()" :disabled="busy" data-passkey="add-device"><span class="inner">Add this device</span></button></p>

                            <h3>Link to a wallet</h3>
                            <template x-for="l in linkedWallets()" :key="l.parent">
                                <p class="passkey-linked" data-passkey="linked-wallet">Linked to <span x-text="walletName(l.wallet)"></span> <a :href="(window.PASSKEY_LOGIN ? window.PASSKEY_LOGIN.flowscan : '') + '/account/' + l.parent" target="_blank" rel="noopener" class="mono" x-text="l.parent"></a> <span class="chip">linked</span></p>
                            </template>
                            <template x-for="l in pendingLinks()" :key="'p' + l.parent">
                                <p class="passkey-note">Waiting for wallet <span class="mono" x-text="l.parent"></span> to accept the link: tap <b>Link</b> again and confirm in that wallet.</p>
                            </template>
                            <p class="passkey-note" x-show="!linkedWallets().length">Already have Flow Wallet or Dapper? Link this account to it: your wallet shows it under <b>linked accounts</b> and can manage your Flovatars and tokens, and you keep signing in here with your passkey.</p>
                            <p x-show="account.link_enabled"><button type="button" class="btn" @click.prevent.stop="linkWallet()" :disabled="busy" data-passkey="link-wallet"><span class="inner" x-text="linkedWallets().length ? 'Link another wallet' : 'Link to Flow Wallet / Dapper'">Link to Flow Wallet / Dapper</span></button></p>

                            <h3 x-show="account.transactions && account.transactions.length">Recent transactions</h3>
                            <ul class="passkey-txs">
                                <template x-for="tx in (account.transactions || [])" :key="tx.tx_id">
                                    <li><a :href="txUrl(tx.tx_id)" target="_blank" rel="noopener" class="mono" x-text="tx.tx_id.slice(0, 10) + '…'"></a> <span x-text="tx.kind"></span> <span class="muted" x-text="tx.status || ''"></span></li>
                                </template>
                            </ul>
                            <p class="passkey-info" x-show="info" x-text="info"></p>
                            <p class="passkey-error" x-show="error" x-text="error"></p>
                            <p class="passkey-note"><a href="/passkey" target="_blank" rel="noopener">Passkey debug page</a></p>
                        </div>
                    </template>
                    <template x-if="account && !account.authenticated">
                        <p>You are not signed in with a passkey.</p>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
