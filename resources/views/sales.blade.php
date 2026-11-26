<x-guest-layout>
    <script id="flovatars">
        @if(isset($flovatars))
            window.flovatars = @json($flovatars);
        @endif
            @if(isset($templates))
            window.templates = @json($templates);
        @endif

        window.assets_url = '/webgl/images/';
    </script>
    <div x-data="homepageData(window.flovatars, window.templates)"
         x-init="initHomepage($dispatch)"
    >

        <div class="hero-wrapper">
            <section data-origin-x="0" data-origin-y="0" class="sales-hero" id="sales-hero-box">
                <div></div>
                <div class="sky" data-depth="0.1">
                    <img src="/images/sale-hero/hero-sky.svg" alt="">
                </div>
                <div class="island-very-far" data-depth="0.2">
                    <img src="/images/sale-hero/hero-floating-islands-very-far.svg" alt="">
                </div>
                <div class="island-far" data-depth="0.3">
                    <img src="/images/sale-hero/hero-floating-islands-far.svg" alt="">
                </div>
                <div class="island-city" data-depth="0.4">
                    <img src="/images/sale-hero/hero-flova-city-island.svg" alt="">
                </div>
                <div class="island-desert" data-depth="0.4">
                    <img src="/images/sale-hero/hero-FUB-islands.svg" alt="">
                </div>
                <div class="island-desert" data-depth="0.5">
                    <img src="/images/sale-hero/hero-floating-island-rock.svg" alt="">
                </div>
            </section>
        </div>

        <section class="features sales-features">
            <div class="container">
                <div class="intro">
                    <strong class="block-title">Flovatar Land Pre-Sale</strong>
                    <h1>Flova Labs is launching the TerraKit<sup>TM</sup> <br>Pack Pre-sale - SOLD OUT</h1>

                    @if(config('flovatar.flobitSaleEnabled'))
                        <div class="btn-hold">
                            <a href="/pack/login" class="btn btn-pink btn-lg">
                                <span class="inner">Buy Now</span>
                            </a>
                        </div>
                    @endif
                    <div class="two-cols">
                        <div class="col">
                            <p>Flova Labs has identified an asteroid belt in a nearby barren sector of the Flovaverse as the optimal location for the deployment of their new terraforming product, the TerraKit.</p>
                        </div>
                        <div class="col">
                            <p>Each TerraKit pack will contain an Asteroid TerraKit, a custom Flobot used to terraform the rock and a FUB (Flovatar Universe Builder) that has the power to generate new life.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>


        <div class="video-wrap">
            <div class="container">
                <a href="#" class="video play-video" data-video="/images/terrakit.mp4">
                    <img src="/images/video-cover.jpg" alt="">
                    <div class="icon-play">
                        <img src="/images/icon-play.svg" alt="">
                    </div>
                </a>
            </div>
        </div>

        <section class="features-list sales-features-list">

            <div class="rows">
                <div class="container">
                    <div class="row">
                        <div class="img-col">
                            <div class="img">
                                <div class="bg" style="transform: rotate(19deg);">
                                    <img src="/images/feature-bg-swirl.svg" width="580" alt="" class="rotating">
                                </div>
                                <div class="wrap">
                                    <img src="/images/TerraKit-pulsating.svg" width="540" alt="">
                                </div>
                            </div>
                        </div>
                        <div class="txt-col">
                            <strong class="block-title">The TerraKit</strong>
                            <h2>Breathing life into new sectors of the Flovaverse</h2>
                            <div class="highlight-txt">
                                <p>The TerraKit is Flova Labs latest invention designed to help colonize previously uninhabitable sectors of the Flovaverse.</p>
                            </div>
                            <p>Planet Flova’s eco-system is in peril and  it’s imperative that alternate off-planet colonies are established to ensure the continuation of Flovan life as we know it.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="img-col">
                            <div class="img">
                                <div class="bg" style="transform: rotate(19deg);">
                                    <img src="/images/feature-bg-swirl.svg" width="580" alt="" class="rotating">
                                </div>
                                <div class="wrap">
                                    <img class="img-desert" src="/images/islands-changing.svg" width="619" alt="">
                                </div>
                            </div>
                        </div>
                        <div class="txt-col">
                            <strong class="block-title">The Land</strong>
                            <h2>Customize your own Floating space rock</h2>
                            <div class="highlight-txt">
                                <p>Be a space pioneer in helping colonize a barren asteroid belt within the Flovaverse.</p>
                            </div>
                            <p>Your asteroid island(s) will be your personal digital home where you showcase and interact with all your new Flovatar Universe creations.</p>
                            <p>You can add Flovatars and Flobots of course, but in the future you'll also be able to add ÐUST series NFTs, pet companions, houses, spaceships, and much more.</p>
                            <p>Just as with Series 1 Flovatars these islands will begin life in 2D, but will, at a later date, turn into a more immersive 3D world.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="img-col">
                            <div class="img">
                                <div class="bg" style="transform: rotate(71deg);">
                                    <img src="/images/feature-bg-swirl.svg" width="510" alt="" class="rotating">
                                </div>
                                <div class="wrap">
                                    <img src="/images/robot-changing.svg" width="726" alt="">
                                </div>
                            </div>
                        </div>
                        <div class="txt-col">
                            <strong class="block-title">The Flobot Constructor Kit</strong>
                            <h2>Configure your own flobot helper</h2>
                            <div class="highlight-txt">
                                <p>Your trusty Flobot will help terraform your new floating space rock as well as setting up your FUB.</p>
                            </div>
                            <p>You will be able to use the familiar Flovatar builder to create your own unique Flobot choosing from 5 different body parts offering over 25 million possible combinations.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="img-col">
                            <div class="img">
                                <div class="bg" style="transform: rotate(19deg);">
                                    <img src="/images/feature-bg-swirl.svg" width="580" alt="" class="rotating">
                                </div>
                                <div class="wrap">
                                    <img src="/images/custom-FUB.png" width="575" alt="">
                                </div>
                            </div>
                        </div>
                        <div class="txt-col">
                            <strong class="block-title">The FUB</strong>
                            <h2>Now you have the power to make your own NFTs</h2>
                            <div class="highlight-txt">
                                <p>The FUB (Flovatar Universe Builder) is a fully customizable version of the now famous Flovatar Series 1 Builder.</p>
                            </div>
                            <p>Imagine an empty version of the Builder that you can fill with your creativity. You can even reskin and embed the new Builder on your own website and start selling your new NFT series without writing a line of code!</p>
                            <p>The sky is the limit, bring anything from your imagination to life.</p>
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
                                    <img src="/images/combine-islands.png" width="612" alt="">
                                </div>
                            </div>
                        </div>
                        <div class="txt-col">
                            <strong class="block-title">Merging TerraKit Components</strong>
                            <h2>Merge components together to make them more powerful </h2>
                            <div class="highlight-txt">
                                <p>Before activation multiple NFTs can be merged together to to expand their potential.</p>
                            </div>
                            <p>Merge Asteroid TerraKits to take charge of larger asteroids, merge Flobot Constructor Kits to unlock rarer traits, or even merge FUBs to increase the number of traits available for the designs in your builder.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="img-col">
                            <div class="img">
                                <div class="bg" style="transform: rotate(71deg);">
                                    <img src="/images/feature-bg-swirl-dark.svg" width="510" alt="" class="rotating">
                                </div>
                                <div class="wrap">
                                    <div class="tokens">
                                        <div class="token dust">
                                            <img src="/images/token-dust.png" width="250" alt="">
                                        </div>

                                        <div class="token dust2">
                                            <img src="/images/token-flow2.png" width="290" alt="">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="txt-col">
                            <strong class="block-title">Monetizing Creativity</strong>
                            <h2>Monetize creations from your imagination</h2>
                            <div class="highlight-txt">
                                <p>Once you publish your FUB anyone can use it to create unique NFTs </p>
                            </div>
                            <p>In order to mint NFTs with your builder, users are required to pay you ÐUST or any other token, and the amount they have to pay will be determined by you before publishing.</p>
                            <p>When new builders (FUBs) are published they will appear as additional builders on the Flovatar website – similar to an App Store for NFT builders.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="img-col">
                            <div class="img">
                                <div class="wrap">
                                    <img src="/images/creation-of-david.svg" width="540" alt="">
                                </div>
                            </div>
                        </div>
                        <div class="txt-col">
                            <strong class="block-title">Web 3 Revolution</strong>
                            <h2>You can now Give Life to the Flovaverse</h2>
                            <div class="highlight-txt">
                                <p>Audiences can now be much more than passive consumers of content.</p>
                            </div>
                            <p>If you don’t have any smart contract coding experience, but always dreamed of having a more active role in the web3 space, and selling your own NFT series, now is the time to act because we removed all the barriers for you to get started!</p>
                            <p>Quite simply, we built a tool unleashing your awesome creativity, bringing to life your creations and in turn giving life to the Flovaverse itself – your NFTs will have the chance to form part of the emerging storyworld.</p>
                        </div>
                    </div>
                </div>
            </div>

        </section>

        <section class="pre-sale-registration">
            <div class="bg">
                <img src="/images/space-bg.png" alt="">
            </div>
            <div class="container">
                <strong class="block-title">Pre-sale registration</strong>
                <h2>2,500 TerraKits<sup>TM</sup> pre-sale packs SOLD OUT!</h2>

                <div class="price-row">
                    <div class="price-col strike">
                        <strong class="price">98</strong>
                        <div class="desc">
                            <strong>Flow</strong>
                            <span>Full Price</span>
                        </div>
                    </div>
                    <div class="price-col">
                        <strong class="price">49</strong>
                        <div class="desc">
                            <strong>Flow</strong>
                            <span>Pre Sale</span>
                        </div>
                    </div>
                </div>

                @if(config('flovatar.flobitSaleEnabled'))
                    <div class="btn-hold">
                        <a href="/pack/login" class="btn btn-pink btn-lg">
                            <span class="inner">Buy Now</span>
                        </a>
                    </div>
                @else
                    <div class="newsletter">
                        <div class="bg">
                            <img src="/images/stars.png" alt="">
                        </div>
                        <div class="container">
                            <div class="heading">
                                <h2>For future updates about upcoming TerraKit pack sales!</h2>
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
                                        <span class="join-newsletter txt">Join our Newsletter</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                <p>Each component of the TerraKit will be a Voucher NFT that will be redeemable and transformed into its final form as it becomes available over the coming months:</p>
                <dl>
                    <dt>Sep 2022</dt>
                    <dd>Flobot constructor kit</dd>
                    <dt>Q4 22 - Q1 23</dt>
                    <dd>Asteroid Island</dd>
                    <dt>Q1 - Q2 23</dt>
                    <dd>FUB - Flovatar universe builder</dd>
                </dl>
                <p>Check out our <a href="https://blog.flovatar.com/introducing-the-flovatar-terrakit-pack-and-the-exclusive-land-pre-sale-735e697f0563" target="_blank">TerraKit blog post</a> for full details of the pre-sale and an in-depth view of how it will work.</p>
            </div>
        </section>


        @include('partials/powered-by')

    </div>

</x-guest-layout>
