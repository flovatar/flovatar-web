<x-guest-layout>
    <section class="hero-pack">
        <div class="container">
            <div class="pack-img">
                <img src="/images/pack_flobits_loop.svg" alt="">
                <div class="info-wrap">
                    <div class="info-circle">
                        <div class="inner">
                            <strong>1,000</strong>
                            <span>Packs</span>
                        </div>
                    </div>
                    <div class="info-circle">
                        <div class="inner">
                            <strong>39.00</strong>
                            <span>Flow</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="features sales-features">
                <div class="intro">
                    <strong class="block-title">23 August - 4pm UTC - 12pm ET</strong>
                    <h1>Only 420 packs available for sale on the first drop!</h1>
                    <div class="two-cols">
                        <div class="col">
                            <p>Take your Flovatar to the next level with the Series 2 Flobits. Over 60 new items have been created, many of which are fully animated.</p>
                        </div>
                        <div class="col">
                            <p>Unlock hidden easter eggs when you combine different Flobits with traits and other Flobits, making your Flovatar truly one of a kind!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="features-list sales-features-list skew-bg-purple">
        <div class="rows">
            <div class="container">
                <div class="row">
                    <div class="img-col">
                        <div class="img">
                            <div class="bg" style="transform: rotate(19deg);">
                                <img src="/images/feature-bg-swirl-dark.svg" width="523" alt="" class="rotating">
                            </div>
                            <div class="wrap">
                                <img src="/images/user-creativity.png" width="509" alt="">
                            </div>
                        </div>
                    </div>
                    <div class="txt-col">
                        <strong class="block-title">Made by YOU</strong>
                        <h2>Each Flobit was ideated or created by our community</h2>
                        <div class="highlight-txt">
                            <p>We asked for your input and you came through! Now here they are, see your imagination brought to life in the Series 2 Flobits.</p>
                        </div>
                        <p>Empowering your creativity is the most important thing at Flovatar, so let your ideas go wild!</p>
                    </div>
                </div>
                <div class="row">
                    <div class="img-col">
                        <div class="img">
                            <div class="bg" style="transform: rotate(19deg);">
                                <img src="/images/feature-bg-swirl-dark.svg" width="523" alt="" class="rotating">
                            </div>
                            <div class="wrap">
                                <img src="/images/flobits-rarity.svg" width="509" alt="">
                            </div>
                        </div>
                    </div>
                    <div class="txt-col">
                        <strong class="block-title">Different Rarity</strong>
                        <h2>Guaranteed at least one epic flobit in each pack</h2>
                        <div class="highlight-txt">
                            <p>Each pack contains 8 randomly selected Flobits with the guarantee of one Epic in each pack</p>
                        </div>
                        <p>In total  8,000 Flobits have been minted featuring 66 brand new illustrations with the following rarity tiers:</p>
                        <dl>
                            <dt>Rare</dt>
                            <dd>35</dd>
                            <dt>Epic</dt>
                            <dd>24</dd>
                            <dt>Legendary</dt>
                            <dd>7</dd>
                        </dl>
                    </div>
                </div>
                <div class="row">
                    <div class="img-col">
                        <div class="img">
                            <div class="bg" style="transform: rotate(71deg);">
                                <img src="/images/feature-bg-swirl-dark.svg" width="510" alt="" class="rotating">
                            </div>
                            <div class="wrap">
                                <img src="/images/flobit-animation.svg" width="510" alt="">
                            </div>
                        </div>
                    </div>
                    <div class="txt-col">
                        <strong class="block-title">Fully Animated</strong>
                        <h2>NEW Epic and Legendary Flobits ARE fully  animated</h2>
                        <div class="highlight-txt">
                            <p>Now you can bring your Flovatar to life with a fully animated Flobit.</p>
                        </div>
                        <p>If the animation isn’t obvious, you may need to unlock it by combining it with something else. Let the hunt begin!</p>
                    </div>
                </div>
                <div class="row">
                    <div class="img-col">
                        <div class="img">
                            <div class="bg" style="transform: rotate(71deg);">
                                <img src="/images/feature-bg-swirl-light-blue.svg" width="510" alt="" class="rotating">
                            </div>
                            <div class="wrap">
                                <img src="/images/parrot-combo.svg" width="510" alt="">
                            </div>
                        </div>
                    </div>
                    <div class="txt-col">
                        <strong class="block-title">Hidden Easter Eggs</strong>
                        <h2>Combine elements together to unlock hidden easter eggs</h2>
                        <div class="highlight-txt">
                            <p>Certain Flobits have surprises hidden within them - can you find them all?</p>
                        </div>
                        <p>You can unlock hidden animations and objects by combining certain Flobits together or with other traits.</p>
                    </div>
                </div>
            </div>
        </div>

    </section>
    @if(config('flovatar.flobitSaleEnabled'))
    <section class="pre-sale-registration alt">
        <div class="bg">
            <img src="/images/space-bg.png" alt="">
        </div>
        <div class="container">
            <h2>Only 420 Flobit Packs will be released for the first drop</h2>
            <p class="txt">All packs are the same price and contain 8 Flobits</p>
            <div class="btn-hold">
                <a href="/pack/login" class="btn btn-pink btn-lg">
                    <span class="inner">BUY NOW</span>
                </a>
            </div>
            <span class="note">*You are guaranteed to receive at least one Epic Flobit in each pack.</span>
        </div>
    </section>
    @else
        <div class="newsletter">
            <div class="bg">
                <img src="/images/stars.png" alt="">
            </div>
            <div class="container">
                <div class="heading">
                    <h3>Stay up-to-date</h3>
                    <h2>Be the first to receive updates and sneak peaks about the Pack sale!</h2>
                </div>
                <div class="form-content">
                    <div class="form-content-holder">
                        <div class="join-discord">
                            <div class="ico-wrap">
                                <a href="{{ config('flovatar.discordUrl') }}" target="_blank"><i class="icon-discord"></i></a>
                            </div>
                            <span class="txt">
                                    Join our <a href="{{ config('flovatar.discordUrl') }}" target="_blank"> Discord</a>
                                </span>
                        </div>
                        <div class="form-col">
                            <div class="form-wrap">
                                <form action="{{ route('subscribe') }}" method="POST">
                                    <div x-show="showSubscription" class="form-row">
                                        <div class="input-hold">
                                            <input x-model="emailSubscription" name="email" type="email" placeholder="Email address">
                                        </div>
                                        <button @click.prevent.stop="submitSubscription" type="submit" value="Get Notified" class="btn btn-lilac"><span class="inner">Sign up</span></button>
                                    </div>
                                    <div x-show="!showSubscription" class="success-message">Thank you</div>
                                </form>
                            </div>
                            <span class="join-newsletter">Join our Newsletter</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif


    @include('partials/powered-by')


</x-guest-layout>
