<div class="col">
    <a x-bind:href="window.itemUrl('flobots', flobot.id, address)" class="card" x-bind:class="flobot.metadata.rarity">
        <div class="img-hold">
            <div>
                <img x-bind:src="'/api/image/flobot/'+flobot.id">
            </div>
        </div>
        <div class="wrap">
            <dl>
                <dt>Mint</dt>
                <dd x-text="'#'+flobot.id"></dd>
                <dt>Rarity</dt>
                <dd x-text="flobot.metadata.rarity"></dd>
                <dt>Sale Price</dt>
                <dd x-html="flobot.price ? formatPrice(flobot.price)+' <span>Flow</span>' : 'Not for sale'" x-bind:class="flobot.price ? '' : 'text-red'"></dd>
            </dl>
        </div>
        <span x-show="flobot.price" class="icon-cart"></span>
    </a>
</div>
