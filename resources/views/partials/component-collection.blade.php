
<div class="col">
    <template x-if="component.count && groupComponent(component)">
        <a href="#" @click.prevent.stop="groupFlobits[component.templateId] = false" class="card stack" x-bind:class="component.rarity">
            <div class="img-hold builder-component-thumb">
                <img x-bind:src="'/api/image/template/'+component.templateId" alt="flobit" />
            </div>
            <div class="wrap">
                <dl>
                    <dt x-text="component.category"></dt>
                    <dd x-text="component.name"></dd>
                    <dt>Rarity</dt>
                    <dd x-text="component.rarity"></dd>
                    <dt>Count</dt>
                    <dd x-text="component.count"></dd>
                    <dt>On sale</dt>
                    <dd x-text="component.salesCount"></dd>
                </dl>
            </div>
            <span x-show="component.salesCount>0" class="icon-cart"></span>
        </a>
    </template>
    <template x-if="!(component.count && groupComponent(component))">
            <a x-bind:href="'/components/'+component.id+'/'+address" class="card" x-bind:class="getRarityColorComponent(component)">
                <div class="img-hold component" x-bind:class="getComponentOutlineClass(component)">
                    <img x-bind:src="component.metadata ? '/api/image/template/'+component.metadata.templateId : '/api/image/template/'+component.templateId" />

                    <div class="side-items">
                        <button @click.prevent.stop="addFlobitToUpgrade(component)" class="item" x-show="dustEnabled && isComponentUpgradableFlobit(component)"><i class="icon-upgrade"></i></button>
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
    </template>
</div>



