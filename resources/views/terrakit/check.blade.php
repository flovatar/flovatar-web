<x-guest-layout>
{{-- TerraKit wallet check (TERRAKIT_CHECK_ENABLED; KB notes/terrakit-check.md). Static shell: no user data, no DB.
     resources/js/app/terrakit-check.js (terrakitCheck) reads the address on chain with one FCL script. --}}
<script>window.TERRAKIT_CHECK = @json(\App\Support\TerrakitCheck::bootstrap());</script>
<section class="tk-check" x-data="terrakitCheck" x-init="init()" data-terrakit="page">
    <div class="container">
        <header class="tk-hero">
            <span class="tk-eyebrow">TerraKit · wallet check</span>
            <h1>Check your TerraKit</h1>
            <p>Your TerraKit vouchers are safe on the blockchain. Check that you can still access them: paste a Flow address or connect your wallet to see your Asteroid Island vouchers, FUB vouchers, Flobot Constructor Kits and unopened TerraKit packs.</p>
        </header>

        <form class="tk-form" @submit.prevent="check()" novalidate role="search" aria-label="Check a Flow address">
            <label for="tk-address">Flow address</label>
            <div class="tk-form-row">
                <input id="tk-address" type="text" inputmode="text" autocomplete="off" autocapitalize="off" spellcheck="false"
                       placeholder="0x1234abcd5678ef90" maxlength="40" x-model="input"
                       :aria-invalid="inputError ? 'true' : 'false'" aria-describedby="tk-address-help">
                <button type="submit" class="btn btn-pink" :disabled="status === 'loading'"><span class="inner">Check</span></button>
            </div>
            <p id="tk-address-help" class="tk-help" :class="inputError ? 'err' : ''" x-text="inputError || 'A Flow address is 0x followed by 16 characters (0–9, a–f). Read only: nothing is signed or stored.'">A Flow address is 0x followed by 16 characters (0–9, a–f). Read only: nothing is signed or stored.</p>
            <div class="tk-connect">
                <template x-if="connected">
                    <button type="button" class="btn btn-sm" @click.prevent="useConnected()"><span class="inner">Check my connected wallet <span class="mono" x-text="short(connected)"></span></span></button>
                </template>
                <template x-if="!connected">
                    <button type="button" class="btn btn-sm" @click.prevent="connect()"><span class="inner">Connect Flow Wallet or Dapper</span></button>
                </template>
            </div>
        </form>

        <div class="tk-live" aria-live="polite">
            <p class="tk-loading" x-show="status === 'loading'" x-cloak>Reading <span class="mono" x-text="address"></span> on the Flow blockchain…</p>
            <div class="tk-panel tk-error" x-show="status === 'error'" x-cloak>
                <h2 x-text="errorTitle"></h2>
                <p x-text="errorText"></p>
            </div>
        </div>

        <div x-show="status === 'done'" x-cloak data-terrakit="result">
            <p class="tk-for">Results for <span class="mono" x-text="address"></span> <span class="chip" x-text="networkLabel"></span></p>

            {{-- wallet class + status --}}
            <div class="tk-panel tk-wallet" :class="'tk-wallet-' + wallet.class" data-terrakit="wallet">
                <div class="tk-wallet-head">
                    <span class="tk-badge" :class="'tk-badge-' + wallet.class" x-text="walletLabel" data-terrakit="wallet-class"></span>
                    <span class="tk-sign" :class="'tk-sign-' + signState" x-text="signLabel"></span>
                </div>

                <template x-if="wallet.class === 'passkey'">
                    <p><b>Ready.</b> This is your Flovatar passkey account, and you're signed in with it right now.</p>
                </template>
                <template x-if="wallet.class === 'flowwallet'">
                    <div>
                        <p><b>Ready.</b> This account has a Flow Wallet key. Connect it with Flow Wallet to use your items.</p>
                        <p x-show="wallet.formerBlocto" class="muted">This used to be a Blocto account. It now has a Flow Wallet key, at the same address.</p>
                        <p x-show="wallet.bloctoKeysActive" class="muted">The old Blocto keys are still active on this account. That's fine: Flow Wallet is the one that can sign.</p>
                    </div>
                </template>
                <template x-if="wallet.class === 'dapper'">
                    <div>
                        <p><b>Works with Dapper Wallet.</b> Connect with Dapper Wallet to use your items. Some ĐUST features aren't available to Dapper accounts.</p>
                    </div>
                </template>
                <template x-if="wallet.class === 'blocto'">
                    <div>
                        <p><b>This is a Blocto account.</b> Blocto closed its wallet service, so it can't sign with Blocto any more. Your items are still at this address on the blockchain. To use them, move the account to Flow Wallet with your <b>Blocto Recovery Kit</b>: you keep the same address and everything in it.</p>
                        <p><a href="#tk-blocto-guide">How to import your Blocto Recovery Kit ↓</a></p>
                    </div>
                </template>
                <template x-if="wallet.class === 'keyless'">
                    <p><b>No active keys.</b> This account can't sign by itself. If it's linked to another account (for example a Flow Wallet or a Flovatar passkey account), use it from there.</p>
                </template>
                <template x-if="wallet.class === 'other'">
                    <p><b>We couldn't tell which wallet manages this account.</b> It has its own keys. If you can connect it with Flow Wallet or Dapper Wallet, you're all set.</p>
                </template>
                <p class="tk-connected-note" x-show="isConnectedAddress" x-cloak>You're connected with this account right now.</p>
            </div>

            {{-- vouchers --}}
            <h2>TerraKit vouchers <span class="tk-count" x-text="summary.total"></span></h2>
            <p class="muted" x-show="summary.total === 0">No TerraKit vouchers at this address<span x-show="packCount > 0"> (but there are unopened TerraKit packs below)</span>.</p>
            <div class="tk-grid" x-show="summary.total > 0" data-terrakit="vouchers">
                <template x-for="row in summary.rows" :key="row.category">
                    <div class="tk-type" x-show="row.total > 0" :data-category="row.category">
                        <h3><span x-text="row.label"></span> <span class="tk-count" x-text="row.total"></span></h3>
                        <ul class="tk-rarities">
                            <template x-for="cell in row.cells" :key="cell.rarity">
                                <li class="tk-cell" :class="['tk-' + cell.rarity, cell.count ? '' : 'tk-empty']" :data-rarity="cell.rarity" :data-count="cell.count">
                                    <div class="tk-thumb">
                                        <template x-if="cell.templateId">
                                            <img :src="'/api/image/template/' + cell.templateId" :alt="cell.label + ' ' + row.label" loading="lazy" width="120" height="120">
                                        </template>
                                    </div>
                                    <span class="tk-rarity" x-text="cell.label"></span>
                                    <span class="tk-n" x-text="'× ' + cell.count"></span>
                                    <span class="tk-listed" x-show="cell.listed > 0" x-text="cell.listed + ' listed for sale'"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </div>

            {{-- packs --}}
            <h2>Unopened TerraKit packs <span class="tk-count" x-text="packCount" data-terrakit="packs"></span></h2>
            <p class="muted" x-show="packCount > 0">Each TerraKit pack holds one Asteroid Island voucher, one FUB voucher and one Flobot Constructor Kit. Their rarities show once the pack is opened.</p>
            <p class="muted" x-show="packCount === 0">No unopened TerraKit packs at this address.</p>
        </div>

        {{-- Blocto Recovery Kit guide (always on the page; highlighted for Blocto accounts) --}}
        <div class="tk-panel tk-guide" id="tk-blocto-guide" :class="wallet && wallet.class === 'blocto' ? 'tk-guide-active' : ''">
            <h2>Blocto Recovery Kit → Flow Wallet</h2>
            <p>Blocto closed its wallet service. If your TerraKit sits in a Blocto account, import that account into Flow Wallet. You keep the same address and everything in it.</p>
            <ol>
                <li>Find your <b>Blocto Recovery Kit</b> (the PDF Blocto emailed you when you turned on Non-Custodial Mode) and the <b>Recovery Password</b> you chose then.</li>
                <li>Install the <a href="https://wallet.flow.com/" target="_blank" rel="noopener">Flow Wallet</a> browser extension.</li>
                <li>Open Flow Wallet and choose <b>Import Wallet</b>.</li>
                <li>Upload the Recovery Kit PDF (Flow Wallet 3.1.5 or later). On older versions, open the PDF, copy the JSON text inside it and paste it.</li>
                <li>Enter your Recovery Password, then your Flow address, and click <b>Import</b>.</li>
                <li>Come back here and check your address again: it should now show <b>Flow Wallet</b>.</li>
            </ol>
            <p>Flow's official guide: <a href="https://flow.com/post/action-required-for-blocto-wallet-users---migrate-your-assets-before-blocto-closes-down" target="_blank" rel="noopener">Action required for Blocto Wallet users</a>.</p>
            <p><b>No Recovery Kit, or lost the password?</b> We're looking at options for holders in this situation. We can't promise an outcome. Your items stay at your address on the blockchain in the meantime.</p>
            <p class="tk-safety">Never share your Recovery Kit, Recovery Password, seed phrase or private keys with anyone, including people who say they're from the Flovatar team. We will never ask for them.</p>
        </div>

        {{-- FAQ --}}
        <div class="tk-faq">
            <h2>FAQ</h2>
            <details>
                <summary>What is a TerraKit?</summary>
                <p>The TerraKit holds an Asteroid Island voucher, a FUB voucher and a Flobot Constructor Kit, each Common, Epic or Legendary. They're Flobits in your Flovatar collection on the Flow blockchain.</p>
            </details>
            <details>
                <summary>Does this page change anything in my wallet?</summary>
                <p>No. It only reads public data from the Flow blockchain. Nothing is signed, nothing is sent, nothing is stored, and we don't ask for your email.</p>
            </details>
            <details>
                <summary>Why does the wallet type matter?</summary>
                <p>To use your vouchers you sign with the wallet that controls your address. Flow Wallet and Dapper Wallet work on Flovatar today. Blocto doesn't any more.</p>
            </details>
            <details>
                <summary>I used Blocto. Are my vouchers gone?</summary>
                <p>No. They're still at your address on the blockchain. Import your Blocto Recovery Kit into Flow Wallet (steps above) to sign with that address again.</p>
            </details>
            <details>
                <summary>I use Dapper Wallet. Anything I should know?</summary>
                <p>Your vouchers work with Dapper Wallet. Some ĐUST features aren't available to Dapper accounts.</p>
            </details>
            <details>
                <summary>Can I check someone else's address?</summary>
                <p>Yes. Blockchain data is public, so any Flow address can be checked here.</p>
            </details>
            <details>
                <summary>The result looks wrong.</summary>
                <p>The wallet type is a best guess from the account's keys. Vouchers listed for sale on the Flovatar marketplace are counted too. If something looks off, message <a href="https://x.com/flovatar" target="_blank" rel="noopener">@flovatar on X</a>, and never share keys or passwords with anyone.</p>
            </details>
        </div>
    </div>
</section>
</x-guest-layout>
