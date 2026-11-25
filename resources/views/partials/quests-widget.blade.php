{{-- ĐUST Quests progress in the passkey account panel (DUST_QUESTS_ENABLED only; static-safe, data from /quests/state) --}}
<div class="quests-widget" x-data="questsWidget" x-init="load()" data-quests="widget">
    <template x-if="state && state.eligible">
        <a href="/quests" class="quests-widget-link">
            <span class="quests-widget-head"><b>ĐUST Quests</b> <span class="quests-widget-sum" x-text="fmt(state.earned) + ' / ' + fmt(state.cap)"></span></span>
            <span class="quests-bar" role="progressbar" :aria-valuenow="pct" aria-valuemin="0" aria-valuemax="100"><span class="quests-bar-fill" :style="'width:' + pct + '%'"></span></span>
            <span class="quests-widget-sub"><span x-text="done + ' of ' + total + ' quests done'"></span> · Open quests &rarr;</span>
        </a>
    </template>
    <template x-if="state && !state.eligible && state.reason === 'email'">
        <p class="quests-widget-note">Confirm your email below to unlock <b>ĐUST Quests</b>: earn up to <span x-text="fmt(state.cap || 1000)"></span>.</p>
    </template>
</div>
