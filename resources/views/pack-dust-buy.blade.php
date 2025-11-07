<x-guest-layout>
    <section class="hero-pack"
        x-data="packData()"
             @pack-pre-reserved.window = "packData = $event.detail"
    >
        <div class="container">
            <div class="pack-img">
                <img src="/images/pack-dust.svg" alt="">
                <div class="info-wrap">
                    <div class="info-circle">
                        <div class="inner">
                            <strong>3,000</strong>
                            <span>ÐUST</span>
                        </div>
                    </div>
                </div>
            </div>
            <div x-show="!sealingPack">
                <div class="btn-panel-info">
                    <span class="loader">{{ $availableDustPacks == 0 ? 'Sold out' : '( '.$availableDustPacks.' / 500 still available )'}}</span>
                </div>
                <div class="btn-panel-info" x-show="remainingMinutesToSale > 0">
                    <span class="loader" x-text="'Please reload this page in '+remainingMinutesToSale+' minutes to be able to buy a pack'"></span>
                </div>
                @if($availableDustPacks > 0 && config('flovatar.dustPackLive'))
                <div class="btn-panel jshidden" x-show="remainingMinutesToSale <= 0">
                    <button x-show="loggedIn() && dustBalance() >= 3000" @click.prevent.stop.debounce="purchasePack()" class="btn btn-pink btn-lg">
                        <span class="inner">BUY NOW</span>
                    </button>
                    <a x-show="loggedIn() && dustBalance() < 3000" href="https://app.increment.fi/swap?in=A.1654653399040a61.FlowToken&out=A.921ea449dffec68a.FlovatarDustToken" target="_blank" class="btn btn-pink btn-lg">
                        <span class="inner" x-text="'GET '+missingDustPackBalanceFormatted()+' ÐUST TO BUY A PACK'"></span>
                    </a>
                    <button x-show="!loggedIn()" @click.prevent.stop.debounce="fcl.authenticate()" class="btn btn-pink btn-lg">
                        <span class="inner">Connect your Wallet</span>
                    </button>
                </div>
                @endif
            </div>
            <div x-show="sealingPack" class="jshidden">
                <div class="btn-panel" x-show="!packPurchased">
                    <span class="loader">Please wait a few floments <img src="/images/loader-white.svg" width="60px" alt=""></span>
                </div>
                <div class="btn-panel-info" x-show="packPurchased">
                    <span class="loader">Congratulations! You just purchased a Dust Flobit Pack.</span>
                </div>
                <div class="btn-panel" x-show="packPurchased">
                    <a x-bind:href="'/collection/'+address()" class="btn btn-purple btn-lg">
                        <span class="inner">VIEW your PACK</span>
                    </a>
                    <a href="/pack/dust/buy" class="btn btn-pink btn-lg">
                        <span class="inner">BUY ANOTHER</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    @include('partials/powered-by')

</x-guest-layout>
