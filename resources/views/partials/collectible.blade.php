<div class="col">
    <a x-bind:href="'/collectibles/'+collectible.id+'/'+address" class="card" x-bind:class="getCollectibleRarityText(collectible, templatesDust)">
        <div class="img-hold">
            <div>
                <img x-bind:src="'/api/image/collectible/'+collectible.id">
            </div>
            <div class="side-items">
                <span class="item" x-show="collectible.price"><i class="icon-cart"></i></span>
                <div class="item" x-show="false"><img src="/images/icon-airdrop.svg"></div>
            </div>
        </div>
        <div class="wrap">
            <dl>
                <dt x-text="collectible.name ? 'Name' : 'Mint'"></dt>
                <dd x-text="collectible.name ? collectible.name : '#'+collectible.mint"></dd>
                <dt>Rarity</dt>
                <dd x-text="getCollectibleRarityText(collectible, templatesDust)"></dd>
                <dt>Rarity Score</dt>
                <dd x-text="getCollectibleRarityScore(collectible, templatesDust)"></dd>
                <dt>Sale Price</dt>
                <dd x-html="collectible.price ? formatPrice(collectible.price)+' <span>Flow</span>' : 'Not for sale'" x-bind:class="collectible.price ? '' : 'text-red'"></dd>
            </dl>
        </div>
    </a>
</div>
