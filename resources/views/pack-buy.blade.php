<x-guest-layout>
    <section class="hero-pack"
        x-data="packData()"
             @pack-pre-reserved.window = "packData = $event.detail"
    >
        <div class="container">
            <div class="pack-img">
                <img src="/images/TerraKit-pulsating.svg" alt="">
                <div class="info-wrap">
                    <div class="info-circle">
                        <div class="inner">
                            <strong>49.00</strong>
                            <span>Flow</span>
                        </div>
                    </div>
                </div>
            </div>
            <div x-show="!sealingPack">
                <div class="btn-panel-info">
                    <span class="loader">{{ $availableTerraKit == 0 ? 'Sold out' : '( '.$availableTerraKit.' / 2,500 still available )'}}</span>
                </div>
                <div class="btn-panel-info" x-show="remainingMinutesToSale > 0">
                    <span class="loader" x-text="'Please reload this page in '+remainingMinutesToSale+' minutes to be able to buy a pack'"></span>
                </div>
                @if($availableTerraKit > 0)
                <div class="btn-panel">
                    <button x-show="loggedIn() && remainingMinutesToSale <= 0" @click.prevent.stop.debounce="purchasePack()" class="btn btn-pink btn-lg">
                        <span class="inner">BUY NOW</span>
                    </button>
                    <button x-show="!loggedIn()" @click.prevent.stop.debounce="{!! \App\Support\PasskeyLogin::connectAction('fcl.authenticate()') !!}" class="btn btn-pink btn-lg">
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
                    <span class="loader">Congratulations! You just purchased a TerraKit Pack.</span>
                </div>
                <div class="btn-panel" x-show="packPurchased">
                    <a x-bind:href="'/collection/'+address()" class="btn btn-purple btn-lg">
                        <span class="inner">VIEW your PACK</span>
                    </a>
                    <a x-bind:href="(isVipUser) ? '/pack/buy': '/pack/login'" class="btn btn-pink btn-lg">
                        <span class="inner">BUY ANOTHER</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    @include('partials/powered-by')

</x-guest-layout>
