<nav id="main-nav">
    <div class="container">
        <div class="logo">
            <a href="/"><img src="/images/flovatar-logo.svg" alt="Flovatar"></a>
        </div>
        <div class="nav-wrap">
            <button class="nav-opener"><i class="icon-menu"></i></button>
            <ul class="nav">
                <li x-show="!loggedIn() && !userCheckLoading" class="jshidden show-mobile">
                    <button @click.prevent.stop="fcl.authenticate()" class="btn"><span class="inner">Connect Wallet</span></button>
                </li>
                <!--
                <li class="{{request()->routeIs('getstarted') ? 'active ' : ''}}"><a href="{{ route('getstarted') }}">Get Started</a></li>
                -->
                <li class="{{request()->routeIs('builder') ? 'active ' : ''}}" id="nav-builder">
                    <a href="{{ route('builder') }}">Builder</a>
                    <div class="sub-menu menu-drop" id="sub-nav-builder">
                        <ul class="sub-nav">
                            <li><a href="{{ route('builder') }}">Flovatars</a></li>
                            <li><a href="{{ route('builder.flobot') }}">Flobots</a></li>
                            <li><a href="{{ route('builder.dust') }}">Psyche Likee</a></li>
                            <li><a href="{{ route('builder.asteroid') }}">Asteroid</a></li>
                        </ul>
                    </div>
                </li>
                <li class="{{request()->routeIs('marketplace') ? 'active ' : ''}}"><a href="{{ route('marketplace') }}">Marketplace</a></li>
                <li class="{{request()->routeIs('showroom') ? 'active ' : ''}}" id="nav-showroom">
                    <a href="{{ route('showroom') }}">Showroom</a>
                    <div class="sub-menu menu-drop" id="sub-nav-showroom">
                        <ul class="sub-nav">
                            <li><a href="{{ route('showroom') }}">Flovatars</a></li>
                            <li><a href="{{ route('showroom.flobot') }}">Flobots</a></li>
                            @if(config('flovatar.dustEnabled'))
                            <li><a href="{{ route('showroom.dustcollectible') }}">Psyche Likee</a></li>
                            @endif
                        </ul>
                    </div>
                </li>

                <li><a href="https://toucans.ecdao.org/p/FlovatarDAO/" target="_blank">DAO</a></li>

                <li class="{{request()->routeIs('chat.index') ? 'active ' : ''}}"><a href="{{ route('chat.index') }}">Chat</a></li>


                <li class="{{request()->routeIs('explore') ? 'active ' : ''}}"  id="nav-explore">
                    <a href="{{ route('explore') }}">Explore</a>
                    <div class="sub-menu menu-drop" id="sub-nav-explore">
                        <ul class="sub-nav">
                            <li><a href="{{ route('story') }}">Story</a></li>
                            <li><a href="https://fudnews.com" target="_blank">FUD News</a></li>
                            <li><a href="https://flov.dev" target="_blank">Flov.dev</a></li>
                            <li><a href="{{ route('explore') }}">More</a></li>
                        </ul>
                    </div>
                </li>

                <li x-show="!loggedIn() && !userCheckLoading" class="jshidden hide-mobile">
                    <button @click.prevent.stop="fcl.authenticate()" class="btn"><span class="inner">Connect Wallet</span></button>
                </li>
            </ul>
            <div class="user-menu" x-show="loggedIn() || userCheckLoading">
                <button class="menu-opener"><i class="icon-user"></i></button>
                <div class="menu-drop">
                    <dl class="user-info">
                        <dt x-show="showProfileName && name()">Name</dt>
                        <dd x-show="showProfileName && name()" ><a x-bind:href="'https://find.xyz/'+name()" target="_blank" x-text="name()"></a></dd>
                        <dt>Wallet ID</dt>
                        <dd @click.prevent.stop="copyAddress()" x-text="address()"></dd>
                        <dt x-show="!isDapperWallet()">Balance</dt>
                        <dd x-text="balance()+' FLOW'" x-show="!isDapperWallet()"></dd>
                        <dt x-show="dustEnabled">&nbsp;</dt>
                        <dd x-show="dustEnabled" x-text="dustBalanceFormatted()+' ÐUST'"></dd>
                        <dt x-show="isDapperWallet()">Dapper</dt>
                        <dd x-show="isDapperWallet()"><a href="https://accounts.meetdapper.com/home" target="_blank">Open Profile</a></dd>
                    </dl>
                    <ul class="sub-nav">
                        <li class="{{request()->routeIs('collection') ? 'active ' : ''}}"><a x-bind:href="name() ? '/collection/'+name() : '/collection/'+address()">My Collection</a></li>
                        <li class="{{request()->routeIs('transactions') ? 'active ' : ''}}"><a x-bind:href="'/transactions/'+address()">My Transactions</a></li>
                        <li x-show="!isDapperWallet()"><a href="https://app.increment.fi/swap?in=A.1654653399040a61.FlowToken&out=A.921ea449dffec68a.FlovatarDustToken" target="_blank">Buy ÐUST</a></li>
                        @if(config('flovatar.isTest'))
                            <li><a href="https://testnet-faucet.onflow.org/fund-account" target="_blank" data-modal >Top up wallet</a></li>
                        @endif
                        <li><a href="#" @click.prevent.default="logoutAccount()" class="link-logout">Logout <i class="icon-arrow-circle"></i></a></li>
                    </ul>
                </div>

            </div>
        </div>
    </div>
</nav>
