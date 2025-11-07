<x-guest-layout>

    <section class="flovatar-detail">
        <div class="container">
            <div class="row">
                <div class="col-l">
                    <div class="img-holder">
                        <div class="img">
                            <img src="/images/fd-img2.png" alt="">
                        </div>
                        <div class="info-circles">
                            <div class="circle">
                                <div class="txt-img-t">
                                    <img src="/images/text-on-sale-t.svg" width="58" alt="On Sale">
                                </div>
                                <span class="score text-green">52.00<sub>F</sub></span>
                                <div class="txt-img-b">
                                    <img src="/images/text-on-sale-b.svg" width="58" alt="On Sale">
                                </div>
                            </div>
                        </div>
                        <div class="controller">
                            <a href="#" class="controller-item">
                                <i class="icon-upgrade"></i>
                            </a>
                            <a href="#" class="controller-item">
                                <i class="icon-zoom"></i>
                            </a>
                        </div>
                    </div>
                    <div class="btns-container">
                        <div class="btns-row">
                            <div class="btns-list-wrap">
                                <ul class="btns-list">
                                    <li>
                                        <a href="#" class="btn btn-sm btn-lilac"><span class="inner"><span class="long-label">View in Marketplace</span><span class="short-label">Marketplace</span></span></a>
                                    </li>
                                    <li>
                                        <a href="#" class="btn btn-sm btn-lilac"><span class="inner"><span class="long-label">Use for upgrade</span><span class="short-label">Upgrade</span></span></a>
                                    </li>
                                    <li class="right-align">
                                        <a href="#" class="btn btn-icon btn-gray"><span class="inner"><i class="icon-close"></i></span></a>
                                    </li>
                                </ul>
                            </div>
                            <div class="foot">
                                <div class="input-frame nowrap-frame">
                                    <input class="input" type="text" value="0x8aafa47a2a82fab4">
                                    <span class="label">WALLET</span>
                                </div>
                                <a href="#" class="btn btn-sm btn-green">
                                    <span class="inner">Gift</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-r">
                    <div class="content-box">
                        <div class="title-row">
                            <h2>Stats</h2>
                        </div>
                        <div class="box">
                            <ul class="stat-list">
                                <li>
                                    <strong>Mint</strong>
                                    <span>#1532</span>
                                </li>
                                <li>
                                    <strong>Category</strong>
                                    <span>Eyeglasses</span>
                                </li>
                                <li>
                                    <strong>Rarity</strong>
                                    <span>Rare</span>
                                </li>
                                <li>
                                    <strong>Series</strong>
                                    <span>1</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="content-box">
                        <div class="title-row">
                            <h2>Marketplace</h2>
                        </div>
                        <div class="box">
                            <ul class="content-list">
                                <li>
                                    <strong>Sale Price</strong>
                                    <div class="col-r">
                                        <div class="input-frame">
                                            <input class="input" type="number" value="52.00">
                                            <span class="label">Flow</span>
                                        </div>
                                        <div class="btn-hold">
                                            <button class="btn btn-icon btn-green">
                                                <span class="inner"><i class="icon-check"></i></span>
                                            </button>
                                            <button class="btn btn-sm btn-red">
                                                <span class="inner"><span class="long-label">Cancel Sale</span><span class="short-label">Cancel</span></span>
                                            </button>
                                        </div>
                                    </div>
                                </li>
                                <li>
                                    <strong>Marketplace</strong>
                                    <div class="col-r">
                                        <div class="logo-wrap">
                                            <div class="logo">
                                                <img src="/images/logo-dapper.svg" width="40" alt="">
                                            </div>
                                            <span>DAPPER</span>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </section>
    @include('partials/powered-by')

</x-guest-layout>
