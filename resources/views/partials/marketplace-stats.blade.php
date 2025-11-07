<div class="marketplace-stats-row">
    <div class="title-row">
        <h2>Stats</h2>

        @if(request()->routeIs('marketplace'))
            <a href="/transactions" class="btn-outline has-arrow">Previous sales</a>
        @else
            <a href="/marketplace" class="btn-outline has-arrow">Marketplace</a>
        @endif
    </div>
    <div class="marketplace-stats">
        <ul class="marketplace-stats_list">
            <li>
                <span class="dt"><small>Total</small>Sales</span>
                <span class="dd"><span x-text="formatPriceNoDecimal(Math.round(stats.total))"></span> <small>Flow</small></span>
            </li>
            <li>
                <span class="dt"><small>Number of</small>Sales</span>
                <span class="dd"><span x-text="formatPriceNoDecimal(Math.round(stats.count))"></span></span>
            </li>
            <li>
                <span class="dt"><small>Average</small>price</span>
                <span class="dd"><span x-text="formatPrice(stats.avg)"></span>  <small>Flow</small></span>
            </li>
            <li>
                <span class="dt"><small>Highest</small>sale</span>
                <span class="dd"><span x-text="formatPriceNoDecimal(stats.max)"></span>  <small>Flow</small></span>
            </li>
            <li>
                <span class="dt"><small>Sparks</small>Burned</span>
                <span class="dd sparks"><span x-text="formatPriceNoDecimal(getBurnedData('spark'))"></span> <small>/ 9,999</small></span>
            </li>
            <li>
                <span class="dt"><small>Rare boosters </small>burned</span>
                <span class="dd rare"><span x-text="formatPriceNoDecimal(getBurnedData('rare'))"></span> <small>/ 18,747</small></span>
            </li>
            <li>
                <span class="dt"><small>Epic boosters </small>burned</span>
                <span class="dd epic"><span x-text="formatPriceNoDecimal(getBurnedData('epic'))"></span> <small>/ 3,500</small></span>
            </li>
            <li>
                <span class="dt"><small>Legendary boosters </small>burned</span>
                <span class="dd legendary"><span x-text="formatPriceNoDecimal(getBurnedData('legendary'))"></span> <small>/ 350</small></span>
            </li>
        </ul>
    </div>
</div>
