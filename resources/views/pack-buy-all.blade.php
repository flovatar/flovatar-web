<x-guest-layout>
    <section class="hero-pack"
        x-data="packData()"
             @pack-pre-reserved.window = "packData = $event.detail"
    >
        <div class="container">
            <div class="pack-img">
                <img src="/images/TerraKit-pulsating.svg" alt="">
                <div class="info-wrap">
                </div>
            </div>
            <div x-show="!sealingPack">
                <div class="btn-panel-info">
                    <span class="loader">{{ $availableDeluxe == 0 ? 'Sold out' : 'Genesis Deluxe ( '.$availableDeluxe.' / 3,000 still available )'}}</span>
                </div>
                @if($availableDeluxe > 0)
                    <div class="btn-panel">
                        <button x-show="loggedIn()" @click.prevent.stop.debounce="purchasePackAll('deluxe')" class="btn btn-pink btn-lg">
                            <span class="inner">BUY NOW</span>
                        </button>
                        <button x-show="!loggedIn()" @click.prevent.stop.debounce="fcl.authenticate()" class="btn btn-pink btn-lg">
                            <span class="inner">Connect your Wallet</span>
                        </button>
                    </div>
                @endif
            </div>
            <div x-show="!sealingPack">
                <div class="btn-panel-info">
                    <span class="loader">{{ $availableStandard == 0 ? 'Sold out' : 'Genesis Standard ( '.$availableStandard.' / 7,000 still available )'}}</span>
                </div>
                @if($availableStandard > 0)
                    <div class="btn-panel">
                        <button x-show="loggedIn()" @click.prevent.stop.debounce="purchasePackAll('standard')" class="btn btn-pink btn-lg">
                            <span class="inner">BUY NOW</span>
                        </button>
                        <button x-show="!loggedIn()" @click.prevent.stop.debounce="fcl.authenticate()" class="btn btn-pink btn-lg">
                            <span class="inner">Connect your Wallet</span>
                        </button>
                    </div>
                @endif
            </div>
            <div x-show="!sealingPack">
                <div class="btn-panel-info">
                    <span class="loader">{{ $availableFlobit == 0 ? 'Sold out' : 'Flobit ( '.$availableFlobit.' / 1,000 still available )'}}</span>
                </div>
                @if($availableFlobit > 0)
                    <div class="btn-panel">
                        <button x-show="loggedIn()" @click.prevent.stop.debounce="purchasePackAll('flobit')" class="btn btn-pink btn-lg">
                            <span class="inner">BUY NOW</span>
                        </button>
                        <button x-show="!loggedIn()" @click.prevent.stop.debounce="fcl.authenticate()" class="btn btn-pink btn-lg">
                            <span class="inner">Connect your Wallet</span>
                        </button>
                    </div>
                @endif
            </div>
            <div x-show="!sealingPack">
                <div class="btn-panel-info">
                    <span class="loader">{{ $availableTerraKit == 0 ? 'Sold out' : 'TerraKit ( '.$availableTerraKit.' / 2,500 still available )'}}</span>
                </div>
                @if($availableTerraKit > 0)
                    <div class="btn-panel">
                        <button x-show="loggedIn()" @click.prevent.stop.debounce="purchasePackAll('terrakit')" class="btn btn-pink btn-lg">
                            <span class="inner">BUY NOW</span>
                        </button>
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
                    <span class="loader">Congratulations! You just purchased a Pack.</span>
                </div>
                <div class="btn-panel" x-show="packPurchased">
                    <a x-bind:href="'/collection/'+address()" class="btn btn-purple btn-lg">
                        <span class="inner">VIEW your PACK</span>
                    </a>
                    <a href="/pack" class="btn btn-pink btn-lg">
                        <span class="inner">BUY ANOTHER</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    @include('partials/powered-by')

</x-guest-layout>
