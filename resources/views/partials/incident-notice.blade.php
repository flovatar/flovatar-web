{{-- Shown on pages of wallets/NFTs listed in config flovatar.blockedAddresses or flagged_items --}}
<div class="incident-notice" role="alert" style="background:#3b0a0a;color:#fff;padding:12px 16px;border-radius:8px;margin:12px 0;font-weight:600;">
    {{ \App\Support\Blocklist::notice() }}
</div>
