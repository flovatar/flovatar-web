<div class="col">
    <a x-bind:href="getUrl(sale)" class="card" x-bind:class="getRarityColor(sale)">
        <div class="img-hold" x-bind:class="isFlobit(sale) ? 'builder-component-thumb' : ''">
            <img x-bind:src="getSvgUrl(sale)" />
            <div x-html="getFlovatarRarityImages(sale)"  class="rarity-score-boosters"></div>
        </div>
        <div class="wrap">
            <dl>
                <dt x-text="hasMint(sale) ? 'Mint:' : 'Total Minted:'"></dt>
                <dd x-text="getMint(sale)"></dd>
                <dt>RARITY LEVEL</dt>
                <dd x-text="getRarityText(sale)"></dd>
                <dt x-text="isFlovatar(sale) ? 'RARITY SCORE' : 'CATEGORY'"></dt>
                <dd x-text="isFlovatar(sale) ? getRarityScoreNumber(sale) : getCategory(sale)"></dd>
                <dt>Sale Price</dt>
                <dd x-text="formatPrice(sale.price)+' FLOW'"></dd>
            </dl>
        </div>
    </a>
</div>
