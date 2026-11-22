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
        <section data-origin-x="0" data-origin-y="0" class="hero" id="home-hero-box">
            <div></div>
            <div class="sky" data-depth="0.1">
                <img src="/images/hero/hero-sky.avif" alt="">
            </div>
            <div class="skyline" data-depth="0.2">
                <img src="/images/hero/hero-skyline.avif" alt="">
            </div>
            <div class="distant-building" data-depth="0.3">
                <img src="/images/hero/hero-distant-buildings.avif" alt="">
            </div>
            <div class="far-building" data-depth="0.4">
                <img src="/images/hero/hero-far-buildings.avif" alt="">
            </div>
            <div class="midground-building" data-depth="0.5">
                <img src="/images/hero/hero-midground-buildings.svg" alt="">
            </div>
            <div class="side-building" data-depth="0.6">
                <img src="/images/hero/hero-side-buildings.avif" alt="">
            </div>
            <div class="portal" data-depth="0.7">
                <img src="/images/hero/hero-portal-spark-anim.svg" alt="">
            </div>
            <div class="foreground-rooftop" data-depth="0.8">
                <img src="/images/hero/hero-foreground-rooftop.avif" alt="">
            </div>
        </section>
    </div>
        <section class="features">
            <div class="container">
                <div class="intro">
                    <h1>Enter a world of NFTs<br>powered by your creativity</h1>
                    <div class="two-cols">
                        <div class="col">
                            <p>Flovatars have arrived in our universe on a rescue mission to save their planet’s existence from a predicted future.</p>
                        </div>
                        <div class="col">
                            <p>Their survival will depend on StarDust - the mysterious energy source that powers their world - and <span class="txt-blue">StarDust depends on YOU!</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="features-list">
            <div class="earth">
                <div class="stars left">
                    <img src="/images/stars-l.png" width="307" alt="">
                </div>
                <img src="/images/earth.png" alt="">
                <div class="stars right">
                    <img src="/images/stars-r.png" width="364" alt="">
                </div>
                <div class="spark">
                    <img src="/images/earth-crescent-sparks-landing.svg" alt="">
                </div>
            </div>

            <div class="rows">
                <div class="container">
                    <div class="row">
                        <div class="img-col">
                            <div class="img">
                                <div class="bg">
                                    <img src="/images/shadow.svg" alt="">
                                </div>
                                <div class="wrap">
                                    <img src="/images/spark-overcharged.svg" width="420" alt="">
                                </div>
                            </div>
                        </div>
                        <div class="txt-col">
                            <strong class="block-title">The Spark of Life</strong>
                            <h2>9,999 Sparks have crash landed on Earth</h2>
                            <div class="highlight-txt">
                                <p>In order to make the meta-leap to our world, Flovans have had to transtate into Sparks.</p>
                            </div>
                            <p>However, during their journey here, an unexpected power surge over-charged them and fried the navigation circuits scattering them through different times and locations.</p>
                            <p><span class="txt-blue">Bringing these Sparks back to life now depends on YOU</span>, by unleashing your creativity with our unique NFT Builder to <span class="txt-blue">transform them into Flovatars.</span></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="img-col">
                            <div class="img">
                                <div class="bg" style="transform: rotate(19deg);">
                                    <img src="/images/feature-bg-swirl.svg" width="580" alt="" class="rotating">
                                </div>
                                <div class="wrap">
                                    <img src="/images/animated-builder.svg" width="546" alt="">
                                </div>
                            </div>
                        </div>
                        <div class="txt-col">
                            <strong class="block-title">YOU are the Creator</strong>
                            <h2>Each flovatar is custom made by you</h2>
                            <div class="highlight-txt">
                                <p>Einstein said, “Creativity is intelligence having fun” – <span class="txt-blue">so be like Einstein.</span></p>
                            </div>
                            <p>Unlike most other NFT projects, with Flovatar you get to <span class="txt-blue">create your own avatar.</span></p>
                            <p>The 11 different categories of traits and accessories <span class="txt-blue">(Flobits)</span> offer over 100 billion possible unique combinations.</p>
                            <p>Accessing rarer traits for your Flovatar can be achieved by adding <span class="txt-blue">Boosters</span> to each <span class="txt-blue">Spark.</span></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="img-col">
                            <div class="img">
                                <div class="bg" style="transform: rotate(71deg);">
                                    <img src="/images/feature-bg-swirl.svg" width="466" alt="" class="rotating">
                                </div>
                                <div class="wrap">
                                    <div class="tokens">
                                        <div class="token dust">
                                            <img src="/images/token-dust.png" width="250" alt="">
                                        </div>

                                        <div class="token flow">
                                            <img src="/images/token-flow.png" width="290" alt="">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="txt-col">
                            <strong class="block-title">Earn Revenue</strong>
                            <h2>Owners will earn ÐUST tokens every day</h2>
                            <div class="highlight-txt">
                                <p>Our own utility token ÐUST will be released later this year and distributed to each individual Flovatar based on their rarity score <span class="txt-blue">for a period of 10 years!</span></p>
                            </div>
                            <p>ÐUST will unlock lots of features and utilities within our world and will be fully tradable on crypto exchanges.</p>
                            <p>Also, as the creator, if you decide to sell you will earn 1% lifetime royalties on every future sale of your Flovatar.</p>
                            <p>You can <a href="https://blog.flovatar.com/all-about-dust-613ce49cb51a" target="_blank">learn more about ÐUST</a> on our blog.</p>
                        </div>
                    </div>
                </div>
            </div>

            @include('partials/newsletter-middle')
        </section>
        <section class="story-world">
            <div class="top">
                <div class="container">
                    <strong class="block-title">The Flovatar Storyworld</strong>
                    <h2>Follow our illustrated podcast,<br> Flovatar – A Dust Odyssey</h2>
                    <div class="desc">
                        <p>Listen along to the Flovatars origin story and watch out to see whether your Flovatar stars in a cameo role.</p>
                    </div>
                </div>
            </div>
            <div class="btm">
                <div class="container">
                    <div class="story-wrap">
                        <a href="/story">
                            <img src="/images/story-transtator-pods.jpg" alt="">
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="featured-in">
            <div class="inner">
                <div class="container">
                    <h3>Featured In</h3>
                    <ul class="logos">
                        <li>
                            <a href="https://alexablockchain.com/flovatar-is-shaking-up-the-flow-blockchain-nft-space/" target="_blank">
                                <img src="/images/logo-alexa-blockchain.png" alt="" class="logo-square">
                            </a>
                        </li>
                        <li>
                            <a href="https://finance.yahoo.com/news/flovatar-releases-anticipated-tokenomics-dust-130000420.html" target="_blank">
                                <img src="/images/logo-yahoo-finance.svg" alt="" class="logo-rect">
                            </a>
                        </li>
                        <li>
                            <a href="https://www.onflow.org/post/flovatar-nft-flow-blockchain-case-study" target="_blank">
                                <img src="/images/logo-flow-black.svg" alt="" class="logo-rect">
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="decorated-block">
            <div class="road-map-section">
                <div class="container">
                    <div class="illustration bl">
                        <img src="/images/planet-rings.png" width="213" alt="">
                    </div>
                    <div class="illustration br">
                        <img src="/images/planet.png" width="155" alt="">
                    </div>
                    <div class="heading">
                        <h3>The Roadmap</h3>
                        <h2>we’ve come a long way but there’s plenty more to come!</h2>
                    </div>
                    <span class="map-label">Series 1</span>
                    <ul class="road-map">
                        <li>
                            <div class="wrap">
                                <span class="time">16th Dec. 2021</span>
                                <h4>Genesis Drop</h4>
                                <p>Flovatars have landed on Earth! Go catch a Spark of life and shape your own Flovatar exactly the way YOU want!</p>
                            </div>
                        </li>
                        <li>
                            <div class="wrap">
                                <span class="time">23rd Dec. 2021</span>
                                <h4>Holiday pack airdrop</h4>
                                <p>To celebrate the holiday season we airdropped some awesome Flobits to our early Flovatar creators</p>
                            </div>
                        </li>

                        <li>
                            <div class="wrap">
                                <span class="time">January 2022</span>
                                <h4>Background airdrop</h4>
                                <p>We introduced a brand new Flobit category and airdropped one to each Flovatar created</p>
                            </div>
                        </li>

                        <li>
                            <div class="wrap">
                                <span class="time">February 2022</span>
                                <h4>Revamped website and rebranding</h4>
                                <p>Unveiled a fresh new look to the whole website and created a brand new logo!</p>
                            </div>
                        </li>

                        <li>
                            <div class="wrap">
                                <span class="time">March 2022</span>
                                <h4>ÐUST tokenomics</h4>
                                <p>Released all the details about how the ÐUST token will work and what the numerous utilities will be, including exclusive and brand ÐUST-only NFT series.</p>
                            </div>
                        </li>

                        <li>
                            <div class="wrap">
                                <span class="time">April 2022</span>
                                <h4>3D Models</h4>
                                <p>All the traits and Flobits have been transformed into a 3D model and can be previewed in the Builder and the details page. 3D-printable high-poly versions are also ready, but waiting to be unlocked by the ÐUST token.</p>
                            </div>
                        </li>

                        <li>
                            <div class="wrap">
                                <span class="time">May 2022</span>
                                <h4>Meme Maker</h4>
                                <p>To further empower our community in their creative expression we released a custom Flovatar Meme Maker.</p>
                            </div>
                        </li>

                        <li>
                            <div class="wrap">
                                <span class="time">August 2022</span>
                                <h4>Flobit S2 Pack Sale</h4>
                                <p>Over 60 brand new animated and interactive Flobits ideated or created by the community members are brought to life.</p>
                            </div>
                        </li>

                        <li>
                            <div class="wrap">
                                <span class="time">August 2022</span>
                                <h4>Dapper Wallet</h4>
                                <p>Integrating with the Dapper Wallet will allow us to open the door to many new users and widen our audience to people who aren't necessarily crypto-natives.</p>
                            </div>
                        </li>

                        <li>
                            <div class="wrap">
                                <span class="time">Sep 2022</span>
                                <h4>TerraKit pre-sale</h4>
                                <p>Flobots, Asteroid land deeds, and pre-ownership rights to the Flovatar Universe Buidler - FUB.</p>
                            </div>
                        </li>

                        <li>
                            <div class="wrap">
                                <span class="time">May 2023</span>
                                <h4>Sportvatar release</h4>
                                <p>Launching the new home for all sport fans in collaboration with the RCS media group (MARCA, Gazzetta dello Sport).</p>
                            </div>
                        </li>

                        <li>
                            <div class="wrap">
                                <span class="time">May 2023</span>
                                <h4>Storyworld première</h4>
                                <p>First Episode released for our illustrated podcasts series. Flovatars will have the chance to star in cameo roles and expand the storyworld with spin-offs.</p>
                            </div>
                        </li>

                        <li>
                            <div class="wrap">
                                <span class="time">July 2023</span>
                                <h4>ÐUST token mainnet release</h4>
                                <p>Every Flovatar can claim their daily ÐUST and unlock the many exclusive utilities available.</p>
                            </div>
                        </li>

                        <li>
                            <div class="wrap">
                                <span class="time">July 2023</span>
                                <h4>Psyche Likee release</h4>
                                <p>The first ÐUST powered NFT series featuring our adorable Flovatar pet companions.</p>
                            </div>
                        </li>

                        <li>
                            <div class="wrap">
                                <span class="time">September 2023</span>
                                <h4>Flobit ÐUST pack sale</h4>
                                <p>First official Flobit pack sale that can be purchased exclusively with ÐUST tokens.</p>
                            </div>
                        </li>

                        <li class="incomplete">
                            <div class="wrap">
                                <span class="time">TBD</span>
                                <h4>TerraKit mainnet release</h4>
                                <p>Make your own asteroid island to home your NFT collection and use the FUB to create your very own NFT series.</p>
                            </div>
                        </li>

                    </ul>
                    <span class="map-label">Whatever you want!</span>
                </div>
            </div>

            @include('partials/newsletter')
        </section>

        @include('partials/powered-by')

    </div>

</x-guest-layout>
