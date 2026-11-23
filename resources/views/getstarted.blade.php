<x-guest-layout>
    <section class="tab-section"
             x-data="getStartedData()"
             x-init="initGetStarted($dispatch);"
             @hashchange.window="loadHashParams();"
    >
        <div class="container">
            <div class="tab-frame">
                <ul class="tabset">
                    <li><a x-bind:class="getActiveClass('about')" href="#about">ABOUT FLOVATAR</a></li>
                    <li><a x-bind:class="getActiveClass('builder')" href="#builder">BUILDING A FLOVATAR</a></li>
                    <li><a x-bind:class="getActiveClass('blocto')" href="#blocto">SET UP A FLOW WALLET</a></li>
                    <li><a x-bind:class="getActiveClass('flow')" href="#flow">GET FLOW CURRENCY</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab" x-bind:class="getTabClass('about')" id="about-flovatar">
                        <div class="heading">
                            <h1>GETTING STARTED WITH <span class="highlight-txt">THE WORLD’S BEST PFP</span>  NFT BUILDER! </h1>
                            <p>In our Getting Started guide you’ll find all the information you’ll need to create your very own Flovatar masterpiece, but first up here’s some background info on Flovatar and what makes us different from every other NFT project.</p>
                        </div>

                        <div class="media-a">
                            <div class="img">
                                <img src="/images/a-img01.png" alt="">
                            </div>

                            <div class="desc">
                                <div class="inner">
                                    <strong class="subtitle">The Basics</strong>
                                    <h2>WHAT IS A <span class="highlight-txt">PFP NFT?</span></h2>
                                    <p>Personal Profile Picture (PFP) is the thing someone uploads when using something like TikTok, Facebook, Twitter, etc. Profile Pictures are a representation of ourselves on the internet, our avatar. A Flo-avatar if you will. </p>

                                    <p>An NFT is a “non-fungible token” – which in simple terms means a unique asset that has a verifiable owner. </p>

                                    <p>So a PFP NFT is a unique profile picture that can only ever be owned by one person at any one time.</p>
                                </div>
                            </div>
                        </div>

                        <div class="feature-block">
                            <div class="media-a alt">
                                <div class="img lg">
                                    <img src="/images/a-img02.png" alt="">
                                </div>

                                <div class="desc">
                                    <div class="inner">
                                        <strong class="subtitle">Why we’re different</strong>
                                        <h2>WHAT MAKES FLOVATAR NFT<span class="lowercase">s</span> SO SPECIAL?</h2>
                                        <p>Most NFTs are just pre-made static images that you buy, keep and admire and then maybe sell one day – hopefully for a profit.</p>
                                        <p>However,  Flovatars are sooooooo much more than that!</p>
                                    </div>
                                </div>
                            </div>

                            <div class="feature-list">
                                <div class="feature">
                                    <h3>BE THE CREATOR</h3>
                                    <p>Our unique NFT Builder lets you choose each body part so that you get total creative control in what your Flovatar looks like. </p>
                                </div>
                                <div class="feature">
                                    <h3>EARN ROYALTIES</h3>
                                    <p>Because you’re the creator you will earn 1% lifetime royalties on all future sales of your Flovatar - forever!</p>
                                </div>
                                <div class="feature">
                                    <h3>CONTINUALLY CUSTOMIZE</h3>
                                    <p>You can add accessories and backgrounds to your Flovatar at any time, even after minting.</p>
                                </div>
                                <div class="feature">
                                    <h3>ANIMATED</h3>
                                    <p>Flovatars are animated and you can unlock hidden animations by combining certain accessories and backgrounds.</p>
                                </div>
                                <div class="feature">
                                    <h3>INTERACTIVE</h3>
                                    <p>Find the right combinations of character traits, accessories and backgrounds to unlock hidden easter eggs!</p>
                                </div>
                                <div class="feature">
                                    <h3>EARN TOKENS</h3>
                                    <p>All Series 1 Flovatars will exclusively earn our utility token ÐUST for a period of 10 years! <br> Learn more about ÐUST on our <a href="https://blog.flovatar.com/flovatar-milestone-updates-c779bffd1f1a" target="_blank">blog</a>.</p>
                                </div>
                            </div>
                        </div>

                        <div class="media-a">
                            <div class="img">
                                <img src="/images/a-img03.png" alt="">
                            </div>

                            <div class="desc">
                                <div class="inner">
                                    <strong class="subtitle">Built on Flow</strong>
                                    <h2>Flovatar is built on the <span class="highlight-txt">Flow blockchain</span></h2>
                                    <p>There are many different blockchains out there in the crypto world. The most well known one in the NFT space is Ethereum, however, Flovatar uses a different blockchain called Flow - we do this for a number of reasons:</p>
                                    <ol>
                                        <li>Flow is super easy to use </li>
                                        <li>Gas fees are extremely low on Flow (Gas fees on Ethereum are similar to transaction charges and they can be quite expensive)</li>
                                        <li>Flow is eco-friendly as it’s built on the “<a href="https://www.onflow.org/primer" target="_blank">proof-of-stake</a>” model rather than “proof-of-work”.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <div class="blocto-cta">
                            <h2>Next, let's see how to use the Builder to mint your first Flovatar</h2>
                            <div class="btn-wrap">
                                <a href="#builder" class="btn-plain">How to build a Flovatar</a>
                            </div>
                        </div>
                    </div>
                    <div class="tab" x-bind:class="getTabClass('blocto')" id="setup-flow-wallet">
                        <div class="heading">
                            <h1>GETTING STARTED WITH THE <span class="highlight-txt">FLOW WALLET</span></h1>
                            <p>Flovatar works with two wallets: <b>Flow Wallet</b> (the official Flow wallet, non-custodial) and <b>Dapper Wallet</b>.</p>
                        </div>
                        <section class="timeline-section">
                            <strong class="timeline-title">Setting up Flow Wallet</strong>
                            <div class="timeline">
                                <div class="timeline-heading">
                                    <span class="no">1</span>
                                    <h2>Install <span class="highlight-txt">Flow Wallet</span></h2>
                                    <p>Get the browser extension (Chrome, Brave, Edge) or the iOS / Android app from <a href="https://wallet.flow.com" target="_blank" rel="noopener">wallet.flow.com</a>, create your account and back up your recovery phrase somewhere safe. Nobody from Flovatar will ever ask you for it.</p>
                                </div>
                                <div class="timeline-heading">
                                    <span class="no">2</span>
                                    <h2>Connect it to <span class="highlight-txt">Flovatar</span></h2>
                                    <p>Click <b>Connect Wallet</b>, choose <b>Flow Wallet</b> and approve the connection. The wallet also asks you to sign a login proof: it only proves you own the address and never moves funds.</p>
                                </div>
                                <div class="timeline-heading">
                                    <span class="no">3</span>
                                    <h2>Add some <span class="highlight-txt">FLOW</span></h2>
                                    <p>You need a little FLOW for storage and purchases. See <a href="#flow">Get FLOW currency</a>.</p>
                                </div>
                            </div>
                        </section>
                        <section class="timeline-section">
                            <strong class="timeline-title">Using Dapper Wallet</strong>
                            <div class="timeline">
                                <div class="timeline-heading">
                                    <p>Already on Dapper (NBA Top Shot, NFL All Day…)? Choose <b>Dapper</b> in the Connect Wallet window and sign in with your Dapper account. Purchases with Dapper are paid in USD.</p>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="tab" x-bind:class="getTabClass('flow')" id="get-flow-currency">
                        <div class="heading">
                            <h1>First let's find out what your <span class="highlight-txt">level of experience</span> is with using crypto</h1>
                        </div>
                        <div class="option-row">
                            <strong class="title">Have you ever bought any crypto currency?</strong>
                            <ul class="btn-list">
                                <li><button @click.prevent.stop="hasCrypto = 'yes'; cryptoWallet = null;" class="btn-o" x-bind:class="hasCrypto == 'yes' ? 'active' : ''">Yes</button></li>
                                <li><button @click.prevent.stop="hasCrypto = 'no'; cryptoWallet = 'moonpay';" class="btn-o" x-bind:class="hasCrypto == 'no' ? 'active' : ''">No</button></li>
                            </ul>
                        </div>
                        <div class="option-row"  x-show="hasCrypto == 'yes'">
                            <strong class="title">In which exchange or wallet do you store your crypto?</strong>
                            <ul class="btn-list">
                                <li><button @click.prevent.stop="cryptoWallet = 'metamask'" x-bind:class="cryptoWallet == 'metamask' ? 'active' : ''" class="btn-o"><img src="/images/icon-metamask.svg" alt="" width="37">MetaMask</button></li>
                                <li><button @click.prevent.stop="cryptoWallet = 'kraken'" x-bind:class="cryptoWallet == 'kraken' ? 'active' : ''" class="btn-o"><img src="/images/icon-kraken.svg" alt="" width="40">Kraken</button></li>
                                <li><button @click.prevent.stop="cryptoWallet = 'crypto'" x-bind:class="cryptoWallet == 'crypto' ? 'active' : ''" class="btn-o"><img src="/images/icon-crypto.svg" alt="" width="38">Crypto.com</button></li>
                                <li><button @click.prevent.stop="cryptoWallet = 'binance'" x-bind:class="cryptoWallet == 'binance' ? 'active' : ''" class="btn-o"><img src="/images/icon-binance.svg" alt="" width="38">Binance</button></li>
                                <li><button @click.prevent.stop="cryptoWallet = 'other'" x-bind:class="cryptoWallet == 'other' ? 'active' : ''" class="btn-o">Other</button></li>
                            </ul>
                        </div>


                        <section class="timeline-section" x-show="cryptoWallet == 'metamask'">
                            <strong class="timeline-title">MetaMask Instructions</strong>
                            <div class="timeline">
                                <div class="timeline-heading">
                                    <span class="no">1</span>
                                    <h2>USE UNISWAP TO CONVERT <span class="highlight-txt">ETH</span> TO <span class="highlight-txt">WFLOW</span></h2>
                                    <div class="btn-wrap">
                                        <a href="https://app.uniswap.org/#/swap?inputCurrency=ETH&outputCurrency=0x5c147e74d63b1d31aa3fd78eb229b65161983b2b&chain=mainnet" target="_blank" class="btn-o btn-pink"><img src="/images/icon-uniswap.svg" alt="" width="38">Open Uniswap</a>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img10.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Connect your MetaMask wallet to Uniswap</h3>
                                            <p>Once you have opened the Uniswap app click <b>Connect Wallet</b> and choose MetaMask.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img11.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Convert ETH to WFLOW</h3>
                                            <p>Enter the amount of ETH in the top input field that you’d like to convert to WFLOW (Wrapped Flow).</p>
                                            <p>Then click the <b>Swap</b> button.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img12.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Approve the transaction</h3>
                                            <p>Approve the transaction and wait for it to be sealed. Once complete the WFLOW will appear in your MetaMask wallet.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="timeline-heading">
                                    <span class="no">2</span>
                                    <h2>use blocto to teleport <span class="highlight-txt">WFLOW</span> TO <span class="highlight-txt">flow</span></h2>
                                    <div class="btn-wrap">
                                        <a href="https://swap.blocto.app/#/teleport?inputCurrency=0x5c147e74D63B1D31AA3Fd78Eb229B65161983B2b&outputCurrency=0x1654653399040a61" target="_blank" class="btn-o"><img src="/images/icon-bloctoswap.svg" alt="" width="29">Open BloctoSwap Teleport</a>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img13.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Connect your FLOW wallet to BloctoSwap</h3>
                                            <p>This will link your Blocto Wallet with Blocto Swap to enable you to Teleport currency.</p>
                                            <p>Just click the <b>Confirm</b> button to complete the Flow sign in.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img14.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Connect your MetaMask to BloctoSwap</h3>
                                            <p>Click <b>Connect Wallets</b> and then select <b>Connect to Ethereum account</b> and choose <b>MetaMask</b>.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img15.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Teleport your WFLOW to FLOW</h3>
                                            <p>Enter the amount of WFLOW you’d like to transfer from Metamask to your Blocto wallet and then then click the <b>Approve WFLOW</b> button</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img16.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Approve the transaction in MetaMask</h3>
                                            <p>Give BloctoSwap permission to access the WFLOW  in your MetaMask wallet.</p>
                                            <p>Now wait just a few minutes and you will then see your FLOW in your Blocto Wallet.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img31.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>View your FLOW balance</h3>
                                            <p>After a few minutes you can check your FLOW balance in your Blocto Wallet just by opening the user dropdown menu on the Flovatar website and you will see it underneath the wallet ID.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img32.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Managing your FLOW with Flow Port</h3>
                                            <p>To manage your FLOW head on over to <a href="https://port.onflow.org/" target="_blank">Flow Port</a> and login with your Blocto email address.</p>
                                            <p>Now that you have your wallet ready you can also use it with many other <a href="https://www.flowverse.co/projects" target="_blank">projects</a> built on <a href="https://flow.com/" target="_blank">Flow</a>.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>




                        <section class="timeline-section" x-show="cryptoWallet == 'kraken'">
                            <strong class="timeline-title">Kraken Instructions</strong>
                            <div class="timeline">
                                <div class="timeline-heading">
                                    <span class="no">1</span>
                                    <h2>SIGN IN TO KRAKEN TO BUY <span class="highlight-txt">FLOW</span></h2>
                                    <div class="btn-wrap">
                                        <a href="https://www.kraken.com" target="_blank" class="btn-o"><img src="/images/icon-kraken.svg" alt="" width="38">Open Kraken</a>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/kraken-1.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Login or Register with Kraken</h3>
                                            <p>First step is to login or register to be able to use their <b>exchange platform</b>.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/kraken-2.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Buy FLOW token</h3>
                                            <p>From the main navigation bar click <b>Buy Crypto</b></p>
                                            <p>Then filter and select the <b>FLOW</b> token from the list.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/kraken-3.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Enter amount and approve</h3>
                                            <p>Select the amount you would like to buy and the payment method to complete the purchase.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="timeline-heading">
                                    <span class="no">2</span>
                                    <h2>Withdraw <span class="highlight-txt">FLOW</span> to the <span class="highlight-txt">Blocto wallet</span></h2>
                                    <div class="btn-wrap">
                                        <a href="https://www.kraken.com/u/funding/withdraw?asset=FLOW" target="_blank" class="btn-o"><img src="/images/icon-kraken.svg" alt="" width="29">Open Kraken Withdraw</a>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img31.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Copy your Blocto address</h3>
                                            <p>To view your Blocto wallet address just open the user dropdown menu on the Flovatar website and you will see it on the first line.</p>
                                            <p>Copy that address (e.g. 0xc12ba34567890f9a) and use it for the next step.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/kraken-5.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Add Blocto address to the withdrawl list</h3>
                                            <p>Click <b>Add new withdrawl address</b> and then copy the address from the previous step along with a name and save it.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/kraken-4.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Choose amount to withdraw</h3>
                                            <p>Enter the amount of FLOW that you would like to withdraw from Kraken to Blocto and click the <b>Withdraw FLOW</b> button.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/kraken-6.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Approve transaction</h3>
                                            <p>Check that the values are correct and click the <b>Withdraw FLOW</b> button</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img31.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>View your FLOW balance</h3>
                                            <p>After a few minutes (unless extra verification is needed) you can check your FLOW balance in your Blocto Wallet just by opening the user dropdown menu on the Flovatar website and you will see it underneath the wallet ID.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img32.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Managing your FLOW with Flow Port</h3>
                                            <p>To manage your FLOW head on over to <a href="https://port.onflow.org/" target="_blank">Flow Port</a> and login with your Blocto email address.</p>
                                            <p>Now that you have your wallet ready you can also use it with many other <a href="https://www.flowverse.co/projects" target="_blank">projects</a> built on <a href="https://flow.com" target="_blank">Flow</a>.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>




                        <section class="timeline-section" x-show="cryptoWallet == 'crypto'">
                            <strong class="timeline-title">Crypto.com Instructions</strong>
                            <div class="timeline">
                                <div class="timeline-heading">
                                    <span class="no">1</span>
                                    <h2>USE CRYPTO.COM MOBILE APP TO BUY <span class="highlight-txt">FLOW</span></h2>
                                    <div class="btn-wrap">
                                        <a href="https://www.crypto.com" target="_blank" class="btn-o"><img src="/images/icon-crypto.svg" alt="" width="38">Open Crypto.com</a>
                                    </div>
                                    <br>
                                    <p>Please note:<br>
                                    In some cases (especially if paying with bank transfer) you will need to wait up to 7 days to withdraw your FLOW tokens.</p>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/crypto-1.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Open or Install the mobile app</h3>
                                            <p>First step is to open the mobile app and login or register to be able to use their <b>exchange platform</b>.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/crypto-2.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Buy FLOW token</h3>
                                            <p>From the main navigation bar at the bottom click the Crypto.com logo icon and then <b>+ Buy</b></p>
                                            <p>Then filter and select the <b>FLOW</b> token from the list.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/crypto-3.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Enter amount and approve</h3>
                                            <p>Select the amount you would like to buy and the payment method to complete the purchase.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="timeline-heading">
                                    <span class="no">2</span>
                                    <h2>Withdraw <span class="highlight-txt">FLOW</span> to the <span class="highlight-txt">Blocto wallet</span></h2>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img31.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Copy your Blocto address</h3>
                                            <p>To view your Blocto wallet address just open the user dropdown menu on the Flovatar website and you will see it on the first line.</p>
                                            <p>Copy that address (e.g. 0xc12ba34567890f9a) and use it for the next step.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/crypto-4.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Open your Crypto wallet for FLOW</h3>
                                            <p>Click <b>Accounts</b> in the main navigation and then <b>Crypto Wallet</b> to open your FLOW wallet.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/crypto-5.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Open the Withdraw section</h3>
                                            <p>Click <b>Transfer</b>, then <b>Withdraw</b> and finally <b>External wallet</b> in the main navigation.</p>
                                            <p>At this point click the <b>+</b> on the top right and choose <b>Wallet address</b> to enter the Blocto address from the previous step and click <b>Continue</b>.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/crypto-6.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Enter amount and approve transaction</h3>
                                            <p>Enter the amount of <b>FLOW</b> you would like to transfer, click the <b>Withdraw</b> button and finally <b>Continue</b> to complete the process.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img31.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>View your FLOW balance</h3>
                                            <p>After a few minutes (unless extra verification is needed) you can check your FLOW balance in your Blocto Wallet just by opening the user dropdown menu on the Flovatar website and you will see it underneath the wallet ID.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img32.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Managing your FLOW with Flow Port</h3>
                                            <p>To manage your FLOW head on over to <a href="https://port.onflow.org/" target="_blank">Flow Port</a> and login with your Blocto email address.</p>
                                            <p>Now that you have your wallet ready you can also use it with many other <a href="https://www.flowverse.co/projects" target="_blank">projects</a> built on <a href="https://flow.com" target="_blank">Flow</a>.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>




                        <section class="timeline-section" x-show="cryptoWallet == 'binance'">
                            <strong class="timeline-title">Binance Instructions</strong>
                            <div class="timeline">
                                <div class="timeline-heading">
                                    <span class="no">1</span>
                                    <h2>SIGN IN TO BINANCE TO BUY <span class="highlight-txt">FLOW</span></h2>
                                    <div class="btn-wrap">
                                        <a href="https://www.binance.com" target="_blank" class="btn-o"><img src="/images/icon-binance.svg" alt="" width="38">Open Binance</a>
                                    </div>
                                    <br>
                                    <p>Please note:<br>
                                    In some cases (especially if paying with bank transfer) you will need to wait up to 7 days to withdraw your FLOW tokens.</p>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/binance-1.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Login or Register with Binance</h3>
                                            <p>First step is to login or register to be able to use their <b>exchange platform</b>.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/binance-2.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Buy BUSD token</h3>
                                            <p>From the main navigation bar click <b>Buy Crypto</b> and select your preferred payment method.</p>
                                            <p>Then choose the <b>BUSD</b> token, enter the amount and complete the purchase.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img lg">
                                        <img src="/images/binance-3.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Convert BUSD to FLOW</h3>
                                            <p>From the main navigation bar click <b>Trade</b> and then <b>Convert</b> .</p>
                                            <p>Select <b>BUSD</b> in the first line and <b>FLOW</b> in the second line. Enter the amount you would like to convert and complete the exchange.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="timeline-heading">
                                    <span class="no">2</span>
                                    <h2>Withdraw <span class="highlight-txt">FLOW</span> to the <span class="highlight-txt">Blocto wallet</span></h2>
                                    <div class="btn-wrap">
                                        <a href="https://www.binance.com/en/my/wallet/account/main/withdrawal/crypto/FLOW?origin=MBX" target="_blank" class="btn-o"><img src="/images/icon-binance.svg" alt="" width="29">Open Binance Withdraw</a>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img31.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Copy your Blocto address</h3>
                                            <p>To view your Blocto wallet address just open the user dropdown menu on the Flovatar website and you will see it on the first line.</p>
                                            <p>Copy that address (e.g. 0xc12ba34567890f9a) and use it for the next step.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img lg">
                                        <img src="/images/binance-4.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Enter details to withdraw</h3>
                                            <p>Paste the <b>Blocto address</b> from the previous step, select the FLOW network, and enter the amount to complete the withdraw process.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img31.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>View your FLOW balance</h3>
                                            <p>After a few minutes (unless extra verification is needed) you can check your FLOW balance in your Blocto Wallet just by opening the user dropdown menu on the Flovatar website and you will see it underneath the wallet ID.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img32.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Managing your FLOW with Flow Port</h3>
                                            <p>To manage your FLOW head on over to <a href="https://port.onflow.org/" target="_blank">Flow Port</a> and login with your Blocto email address.</p>
                                            <p>Now that you have your wallet ready you can also use it with many other <a href="https://www.flowverse.co/projects" target="_blank">projects</a> built on <a href="https://flow.com" target="_blank">Flow</a>.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>





                        <section class="timeline-section" x-show="cryptoWallet == 'other'">
                            <strong class="timeline-title">Other Exchanges Instructions</strong>
                            <div class="timeline">
                                <div class="timeline-heading">
                                    <span class="no">1</span>
                                    <h2>OTHER EXCHANGES TO BUY <span class="highlight-txt">FLOW</span></h2>
                                    <div class="btn-wrap">
                                        <a href="https://www.flowverse.co/categories/exchange" target="_blank" class="btn-o">Open Flowverse</a>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/other-exchanges-1.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Login or Register with any exchange</h3>
                                            <p>First step is to login or register with any of the other exchanges listed in the <b>Flowverse page</b>.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/kraken-3.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Buy FLOW token</h3>
                                            <p>Then proceed to buy the FLOW token from the exchange.</p>
                                            <p>This process can vary depending on your selection, but should be intuitive enough.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="timeline-heading">
                                    <span class="no">2</span>
                                    <h2>Withdraw <span class="highlight-txt">FLOW</span> to the <span class="highlight-txt">Blocto wallet</span></h2>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img31.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Copy your Blocto address</h3>
                                            <p>To view your Blocto wallet address just open the user dropdown menu on the Flovatar website and you will see it on the first line.</p>
                                            <p>Copy that address (e.g. 0xc12ba34567890f9a) and use it for the next step.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/kraken-4.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Enter details to withdraw</h3>
                                            <p>Paste the <b>Blocto address</b> from the previous step and enter the amount to complete the withdraw process.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img31.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>View your FLOW balance</h3>
                                            <p>After a few minutes (unless extra verification is needed) you can check your FLOW balance in your Blocto Wallet just by opening the user dropdown menu on the Flovatar website and you will see it underneath the wallet ID.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img32.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Managing your FLOW with Flow Port</h3>
                                            <p>To manage your FLOW head on over to <a href="https://port.onflow.org/" target="_blank">Flow Port</a> and login with your Blocto email address.</p>
                                            <p>Now that you have your wallet ready you can also use it with many other <a href="https://www.flowverse.co/projects" target="_blank">projects</a> built on <a href="https://flow.com" target="_blank">Flow</a>.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>



                        <section class="timeline-section" x-show="cryptoWallet == 'moonpay'">
                            <strong class="timeline-title">Credit Card / Bank Account Instructions</strong>
                            <div class="timeline">
                                <div class="timeline-heading">
                                    <span class="no">1</span>
                                    <h2>TRY <span class="highlight-txt">MOONPAY</span></h2>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img17.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>By far the simplest way is with MoonPay but it’s not available to everyone</h3>
                                            <p>MoonPay only works in the <b>EU, UK</b> and <b>USA</b> (except New York, Louisiana, and Texas). Also, not all banks allow purchase of crypto currencies.</p>
                                            <p>If you're in a country that isn't on that list then we recommend using either:</p>
                                            <div class="btn-wrap">
                                                <button @click.prevent.stop="cryptoWallet = 'kraken'" class="btn-o"><img src="/images/icon-kraken.svg" alt="" width="40">Kraken</button>
                                                <button @click.prevent.stop="cryptoWallet = 'crypto'" class="btn-o"><img src="/images/icon-crypto.svg" alt="" width="38">Crypto.com</button>
                                                <button @click.prevent.stop="cryptoWallet = 'binance'" class="btn-o"><img src="/images/icon-binance.svg" alt="" width="38">Binance</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img18.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Enter the amount of FLOW you’d like to purchase</h3>
                                            <p>You will automatically see the $USD equivalent so you know how much money you’re spending.</p>
                                            <p>Then click <b>Continue</b></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img19.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Register for an account</h3>
                                            <p>Enter your email address to setup a new account with Moonpay.</p>
                                            <p>Then click <b>Continue</b></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img20.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Enter your details</h3>
                                            <p>Enter your <b>Name</b>, <b>DOB</b> and <b>Country</b> of residence.</p>
                                            <p>Then click <b>Continue</b></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img21.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Continue with verification on your smartphone</h3>
                                            <p>If you’re not already using your phone you’ll be prompted to finish verifying yourself on there so just scan the QR code with your phone’s camera then open the link.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img22.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Add a payment method</h3>
                                            <p>Next you’ll be asked how you’d like to pay for your FLOW, either by bank transfer or Credit/Debit Card.</p>
                                            <p>Select an option then enter all the details.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img23.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Confirm the payment</h3>
                                            <p>Make sure to check all the details are correct before clicking the <b>Buy now</b> button.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="media-a alt sm">
                                    <div class="img">
                                        <img src="/images/a-img24.png" alt="">
                                    </div>
                                    <div class="desc">
                                        <div class="inner">
                                            <h3>Processing the transfer</h3>
                                            <p>It will take a few seconds to process the bank transfer, once it’s complete you will see a <b>Crypto Delivered</b> confimation. </p>
                                            <p>You’ll receive an email from MoonPay informing you your FLOW is on its way. </p>
                                            <p>The actual transfer of the monies may take up to 30 mins but when it’s completed you will recieve another email. Then if you refresh the Flovatar website you will see the funds in your account.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="media-a info-row">
                                <div class="desc">
                                    <div class="inner">
                                        <h3>Card not accepted?</h3>
                                        <p>If you’re having issues getting your card accepted with MoonPay then we recommend using either of these alternative methods:</p>
                                        <div class="btn-wrap">
                                            <button @click.prevent.stop="cryptoWallet = 'kraken'; window.scrollTo({top: 0, behavior: 'smooth'});" class="btn-o"><img src="/images/icon-kraken.svg" alt="" width="40">Kraken</button>
                                            <button @click.prevent.stop="cryptoWallet = 'crypto'; window.scrollTo({top: 0, behavior: 'smooth'});" class="btn-o"><img src="/images/icon-crypto.svg" alt="" width="38">Crypto.com</button>
                                            <button @click.prevent.stop="cryptoWallet = 'binance'; window.scrollTo({top: 0, behavior: 'smooth'});" class="btn-o"><img src="/images/icon-binance.svg" alt="" width="38">Binance</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                        <div class="blocto-cta" x-show="cryptoWallet">
                            <h2>Congratulations, you did it!<br> Now that the hard part is done, let's have some fun and create your very own Flovatar in the Builder!</h2>
                            <div class="btn-wrap">
                                <a href="/builder" class="btn-plain">Open the Builder</a>
                            </div>
                        </div>
                    </div>
                    <div class="tab" x-bind:class="getTabClass('builder')" id="building-flovatar">
                        <div class="heading">
                            <h1><span class="highlight-txt">HOW TO BUILD</span> <br> A FLOVATAR </h1>
                            <p>Unlike most NFT projects, Flovatar lets you get creative by building your own avatar however you want rather than just choosing one that’s been pre-made.</p>
                        </div>

                        <div class="media-a alt sm">
                            <div class="img lg">
                                <img src="/images/a-img25.svg" alt="">
                            </div>

                            <div class="desc">
                                <div class="inner">
                                    <strong class="subtitle">The Spark of Life</strong>
                                    <h2>Everything begins with a Spark</h2>
                                    <p>Spark is the secret ingredient that will allow you to build a Flovatar.</p>
                                    <p>Each time you create a Flovatar the Spark will be burnt so to build another Flovatar you will need a new Spark.</p>
                                </div>
                            </div>
                        </div>
                        <div class="media-a sm">
                            <div class="img">
                                <img src="/images/a-img26.svg" alt="">
                            </div>

                            <div class="desc">
                                <div class="inner">
                                    <strong class="subtitle">The Builder</strong>
                                    <h2>Shaping your Flovatar</h2>
                                    <p>First select a category (e.g. body, clothing, eyes etc) and then choose the trait that you’d like to use.</p>
                                    <p>In addition to the traits you can also add Flobits which are optional accessories that you can add and remove from your Flovatar at any time.</p>
                                </div>
                            </div>
                        </div>
                        <div class="media-a alt sm">
                            <div class="img xl">
                                <img src="/images/a-img27.svg" alt="">
                            </div>

                            <div class="desc">
                                <div class="inner">
                                    <strong class="subtitle">Boosters</strong>
                                    <h2>Using boosters to increase rarity</h2>
                                    <p>When building your Flovatar you’ll notice different traits are color coded - each color represents a different rarity level:</p>
                                    <dl>
                                        <dt>White</dt>
                                        <dd>Common</dd>
                                        <dt>Blue</dt>
                                        <dd>Rare</dd>
                                        <dt>Yellow</dt>
                                        <dd>Epic</dd>
                                        <dt>Purple</dt>
                                        <dd>Legendary</dd>
                                    </dl>
                                    <p>To be able to use a trait that is Rare, Epic or Legendary you will need to purchase the relevant Booster before being able to create your Flovatar. One Booster is needed per trait used.</p>
                                    <p>The higher your rarity score the more valuable your Flovatar will be.</p>
                                </div>
                            </div>
                        </div>
                        <div class="media-a sm">
                            <div class="img">
                                <img src="/images/a-img28.svg" alt="">
                            </div>

                            <div class="desc">
                                <div class="inner">
                                    <strong class="subtitle">Flobits</strong>
                                    <h2>Customizing your look with accessories</h2>
                                    <p>Flobits are what we call the accessories that you can add to your Flovatar for extra character.</p>
                                    <p>One of the things that makes Flovatar unique from other NFT projects is that Flobits can be added and removed from your Flovatar even after you’ve minted it.</p>
                                </div>
                            </div>
                        </div>
                        <div class="media-a alt sm">
                            <div class="img xl">
                                <img src="/images/a-img29.png" alt="">
                            </div>

                            <div class="desc">
                                <div class="inner">
                                    <strong class="subtitle">Missing Components</strong>
                                    <h2>Purchasing any missing components</h2>
                                    <p>If your creation includes any components that you don’t have you’ll be shown a shopping list of the missing items that you can buy with just one click.</p>
                                    <p>Once you have all the necessary items just click the <b>Create Flovatar</b> button to mint your NFT.</p>
                                </div>
                            </div>
                        </div>
                        <div class="media-a sm">
                            <div class="img">
                                <img src="/images/a-img30.png" alt="">
                            </div>

                            <div class="desc">
                                <div class="inner">
                                    <strong class="subtitle">After Minting</strong>
                                    <h2>Things you can do even after minting</h2>
                                    <p>Once created you can view your Flovatar by selecting My Collection from the dropdown menu then clicking your Flovatar.</p>
                                    <p>From here you’ll be able to do a number of things including:</p>
                                    <ul>
                                        <li>Download an image of your Flovatar</li>
                                        <li>Customize the accessories (Flobits)</li>
                                        <li>Put your Flovatar up for sale in the Marketplace</li>
                                        <li>Gift your Flovatar to someone else</li>
                                    </ul>
                                    <p>... and so much more once our ÐUST token will be released!</p>
                                </div>
                            </div>
                        </div>
                        <div class="blocto-cta">
                            <h2>You’re now ready to setup your Blocto wallet and get started</h2>
                            <div class="btn-wrap">
                                <a href="#blocto" class="btn-plain">Create a Blocto wallet</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @include('partials/powered-by')

</x-guest-layout>
