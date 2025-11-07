<div class="col">
    <button @click.prevent.stop="selectPackToOpen(pack)" class="card" x-bind:class="(pack>6800) ? ((pack>9799) ? ((pack>10799) ? 'rare' : 'epic') : 'legendary') : 'rare'">
        <div class="img-hold">
            <img x-bind:src="(pack>6800) ? ((pack>9799) ? ((pack>10799) ? ((pack>15799 || pack>15689) ? '/images/pack-dust.svg' :'/images/pack-terrakit.svg') : '/images/pack_flobits_loop_square.svg') : '/images/pack-deluxe.png') : '/images/pack-standard.png'" />
        </div>
        <div class="wrap">
            <dl>
                <dt>Mint</dt>
                <dd x-text="'#'+pack"></dd>
                <dt>Type</dt>
                <dd x-text="(pack>6800) ? ((pack>9799) ? ((pack>10799) ? ((pack>15799 || pack>15689) ? 'Dust Flobits' : 'TerraKit') : 'Flobits S2') : 'Deluxe') : 'Standard'"></dd>
            </dl>
        </div>
    </button>
</div>
