<div class="col">
    <a x-bind:href="'/flovatars/'+flovatar.id+'/'+address" class="card" x-bind:class="getRarityText(flovatar)">
        <div class="img-hold">
            <div>
                <img x-bind:src="'/api/image/'+flovatar.id">
                <div x-html="getFlovatarRarityImages(flovatar)" class="rarity-score-boosters"></div>
                <div class="side-items">
                    <span class="item" x-show="flovatar.price"><i class="icon-cart"></i></span>
                    <div class="item" x-show="false"><img src="/images/icon-airdrop.svg"></div>
                </div>
            </div>
        </div>
        <div class="wrap">
            <dl>
                <dt x-text="flovatar.name ? 'Name' : 'Mint'"></dt>
                <dd x-text="flovatar.name ? flovatar.name : '#'+flovatar.id"></dd>
                <dt>Rarity</dt>
                <dd x-text="getRarityText(flovatar)"></dd>
                <dt>Rarity Score</dt>
                <dd x-text="getRarityScore(flovatar)"></dd>
                <dt>Sale Price</dt>
                <dd x-html="flovatar.price ? formatPrice(flovatar.price)+' <span>Flow</span>' : 'Not for sale'" x-bind:class="flovatar.price ? '' : 'text-red'"></dd>
            </dl>
        </div>
    </a>
</div>
