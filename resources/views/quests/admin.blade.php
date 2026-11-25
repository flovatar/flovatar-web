<x-guest-layout>
{{-- ĐUST Quests admin (testnet). Static shell; data from /quests/admin/data (wallet session in ADMIN_ADDRESSES only). --}}
<section class="quests-page quests-admin" x-data="questsAdmin" x-init="load()" data-quests="admin">
    <div class="container">
        <header class="quests-hero">
            <span class="quests-eyebrow">Admin</span>
            <h1>ĐUST Quests</h1>
        </header>
        <p class="quests-msg err" x-show="error" x-text="error"></p>
        <p class="quests-msg ok" x-show="info" x-text="info"></p>

        <template x-if="data">
            <div>
                <div class="qa-tiles">
                    <div class="qa-tile"><span>Season budget</span><b x-text="fmt(data.settings.season_budget)"></b><small x-text="'committed ' + fmt(data.totals.committed)"></small></div>
                    <div class="qa-tile"><span>Paid</span><b x-text="fmt(data.totals.paid)"></b><small x-text="data.totals.counts.paid + ' claims'"></small></div>
                    <div class="qa-tile"><span>Waiting payout</span><b x-text="fmt(data.totals.verified)"></b><small x-text="data.totals.counts.verified + ' claims · reserved ' + fmt(data.totals.reserved)"></small></div>
                    <div class="qa-tile"><span>Under review</span><b x-text="fmt(data.totals.pending)"></b><small x-text="data.totals.counts.pending + ' claims'"></small></div>
                    <div class="qa-tile"><span>Today (daily cap)</span><b x-text="fmt(data.totals.paid_today)"></b><small x-text="'of ' + fmt(data.settings.daily_cap)"></small></div>
                    <div class="qa-tile"><span>Rewards wallet</span><b x-text="data.rewards.balance === null ? '—' : fmt(data.rewards.balance)"></b><small class="mono" x-text="data.rewards.address || 'not configured'"></small></div>
                </div>

                <h2>Settings</h2>
                <form class="qa-settings" @submit.prevent="saveSettings()">
                    <label>Season budget <input class="quests-input" type="number" min="0" step="1" x-model="settings.season_budget"></label>
                    <label>Daily cap <input class="quests-input" type="number" min="0" step="1" x-model="settings.daily_cap"></label>
                    <label>Per-account cap <input class="quests-input" type="number" min="0" step="1" x-model="settings.per_user_cap"></label>
                    <label class="qa-check"><input type="checkbox" x-model="settings.claims_paused"> Pause claims</label>
                    <label class="qa-check"><input type="checkbox" x-model="settings.payouts_paused"> Pause payouts</label>
                    <button type="submit" class="btn btn-pink btn-sm" :disabled="busy"><span class="inner">Save settings</span></button>
                </form>

                <h2>Claims</h2>
                <div class="qa-filter">
                    <select class="quests-input" x-model="status" @change="load()">
                        <option value="pending">Under review</option>
                        <option value="verified">Waiting payout</option>
                        <option value="paid">Paid</option>
                        <option value="rejected">Rejected</option>
                        <option value="all">All</option>
                    </select>
                    <input class="quests-input" type="search" placeholder="address 0x…" x-model="q" @keydown.enter.prevent="load()">
                    <button type="button" class="btn btn-sm" @click.prevent="load()"><span class="inner">Refresh</span></button>
                </div>
                <div class="qa-table-wrap">
                    <table class="qa-table">
                        <thead><tr><th>#</th><th>Account</th><th>Quest</th><th>Đ</th><th>Status</th><th>Evidence</th><th></th></tr></thead>
                        <tbody>
                            <template x-for="c in data.claims" :key="c.id">
                                <tr :data-claim="c.id">
                                    <td x-text="c.id"></td>
                                    <td><span class="mono" x-text="c.address"></span><span class="chip warn" x-show="c.frozen">frozen</span><span class="chip" x-show="c.cap_override !== null && c.cap_override !== undefined" x-text="'cap ' + c.cap_override"></span></td>
                                    <td x-text="c.key"></td>
                                    <td x-text="c.amount"></td>
                                    <td><span x-text="c.status"></span><template x-if="c.tx_id"><a :href="txUrl(c.tx_id)" target="_blank" rel="noopener"> tx</a></template><span class="muted" x-show="c.batch_id && c.status === 'verified'"> (in batch)</span></td>
                                    <td class="qa-ev"><code x-text="JSON.stringify(c.evidence)"></code><div class="muted" x-show="c.note" x-text="c.note"></div></td>
                                    <td class="qa-act">
                                        <button type="button" class="btn btn-sm btn-green" x-show="c.status === 'pending'" @click.prevent="approve(c)"><span class="inner">Approve</span></button>
                                        <button type="button" class="btn btn-sm" x-show="(c.status === 'pending' || c.status === 'verified') && !c.batch_id" @click.prevent="reject(c)"><span class="inner">Reject</span></button>
                                        <button type="button" class="btn btn-sm" @click.prevent="freeze(c.address, !c.frozen)"><span class="inner" x-text="c.frozen ? 'Unfreeze' : 'Freeze'"></span></button>
                                        <button type="button" class="btn btn-sm" @click.prevent="capOverride(c.address)"><span class="inner">Cap</span></button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p class="muted" x-show="!data.claims.length">No claims.</p>
                </div>

                <h2 x-show="data.frozen.length">Frozen accounts</h2>
                <ul class="qa-list">
                    <template x-for="f in data.frozen" :key="f.address">
                        <li><span class="mono" x-text="f.address"></span> — <span x-text="f.reason || ''"></span> <button type="button" class="btn btn-sm" @click.prevent="freeze(f.address, false)"><span class="inner">Unfreeze</span></button></li>
                    </template>
                </ul>

                <h2>Quests &amp; amounts</h2>
                <div class="qa-table-wrap">
                    <table class="qa-table">
                        <thead><tr><th>Key</th><th>Title</th><th>Đ</th><th>Active</th><th>Sort</th><th>Claims</th><th></th></tr></thead>
                        <tbody>
                            <template x-for="c in data.challenges" :key="c.id">
                                <tr>
                                    <td><span class="mono" x-text="c.key"></span><div class="muted" x-text="c.verify"></div></td>
                                    <td><input class="quests-input" type="text" x-model="c.title" maxlength="160"></td>
                                    <td><input class="quests-input qa-num" type="number" min="0" step="1" x-model="c.amount"></td>
                                    <td><input type="checkbox" x-model="c.active"></td>
                                    <td><input class="quests-input qa-num" type="number" step="1" x-model="c.sort"></td>
                                    <td x-text="c.claims"></td>
                                    <td><button type="button" class="btn btn-sm" @click.prevent="saveChallenge(c)" :disabled="busy"><span class="inner">Save</span></button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p class="muted" x-text="'Active total: ' + fmt(data.challenges.filter(c => c.active).reduce((s, c) => s + Number(c.amount || 0), 0)) + ' (cap ' + fmt(data.settings.per_user_cap) + ')'"></p>
                </div>

                <h2>Payout batches</h2>
                <div class="qa-table-wrap">
                    <table class="qa-table">
                        <thead><tr><th>Batch</th><th>Status</th><th>Claims</th><th>Đ</th><th>Tx</th><th>Reported</th><th></th></tr></thead>
                        <tbody>
                            <template x-for="b in data.batches" :key="b.id">
                                <tr>
                                    <td class="mono" x-text="b.id.slice(0, 8)"></td>
                                    <td x-text="b.status"></td>
                                    <td x-text="b.count"></td>
                                    <td x-text="b.total"></td>
                                    <td><template x-if="b.tx_id"><a :href="txUrl(b.tx_id)" target="_blank" rel="noopener" class="mono" x-text="b.tx_id.slice(0, 10) + '…'"></a></template><div class="muted" x-show="b.error" x-text="b.error"></div></td>
                                    <td x-text="b.reported_at || ''"></td>
                                    <td><button type="button" class="btn btn-sm" x-show="b.status === 'reserved'" @click.prevent="release(b)"><span class="inner">Release</span></button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</section>
</x-guest-layout>
