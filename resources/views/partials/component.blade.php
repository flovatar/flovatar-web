<div class="col">
    <a x-bind:href="'/components/'+component.id+'/'+address" class="card" x-bind:class="getRarityColorComponent(component)">
        <div class="img-hold component" x-bind:class="getComponentOutlineClass(component)">
            <img x-bind:src="component.metadata ? '/api/image/template/'+component.metadata.templateId : '/api/image/template/'+component.templateId" />

            <div class="side-items">
                <button x-bind:href="'/components/'+component.id+'/'+address" class="item" x-show="component.price"><i class="icon-cart"></i></button>
            </div>
        </div>
        <div class="wrap">
            <dl>
                <dt x-text="getComponentCategory(component)"></dt>
                <dd x-text="getComponentName(component)"></dd>
                <dt>Rarity</dt>
                <dd x-text="component.metadata ? component.metadata.rarity : component.rarity"></dd>
                <dt>Mint</dt>
                <dd x-text="getComponentMint(component)"></dd>
                <dt>Sale Price</dt>
                <dd x-html="component.price ? formatPrice(component.price)+' <span>Flow</span>' : 'Not for sale'" x-bind:class="component.price ? '' : 'text-red'"></dd>
            </dl>
        </div>
    </a>
</div>



