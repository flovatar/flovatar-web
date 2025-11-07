<div class="filter-panel-wrap">
    <div class="filter-panel">
        <div class="filter-btn">
            <a href="#" @click.prevent.stop="showFilterPanel = !showFilterPanel" class="btn btn-sm btn-gray"> <span class="inner"><i class="icon-funel"></i> Filter</span></a>
            <span x-show="getFiltersCount()" class="filter-no" x-text="getFiltersCount()"></span>
        </div>
        <div class="filter-tag-wrap" x-bind:class="!getFiltersCount() ? 'hide-filters' : ''">
            <ul class="tag-list">
                <li x-show="minMint || maxMint">
                    <div class="filter-tag">
                        <div class="inner-tag">
                            <span>Mint #</span>
                            <strong x-text="getMintRange()"></strong>
                            <a href="#" @click.prevent.stop="minMint = null; maxMint = null;" class="icon-cross02"></a>
                        </div>
                        <i class="triangle"></i>
                    </div>
                </li>

            </ul>
        </div>
        <span class="divider"></span>
        <div class="select-wrap select-sort">
            <span class="label">Sort by</span>
            <select name="sortby" x-model="sortBy" value="">
                <option value="datenew">Date - <small>Most recent</small></option>
                <option value="dateold">Date - <small>Oldest</small></option>
            </select>
        </div>
        <span class="item-no"><span x-text="totalResults"></span> <small>items</small></span>
    </div>
    <div class="filter-panel filter-drop" x-show="showFilterPanel">

        <div class="select-wrap sm">
            <span class="label">Min Mint</span>
            <div class="input-wrap">
                <input type="text" @input.debounce.500ms="searchResults" x-model="minMint">
            </div>
        </div>
        <div class="select-wrap sm">
            <span class="label">Max Mint</span>
            <div class="input-wrap">
                <input type="text" @input.debounce.500ms="searchResults" x-model="maxMint">
            </div>
        </div>
        <button @click.prevent.stop="showFilterPanel = false" class="btn btn-icon btn-gray btn-cross">
            <div class="inner">
                <i class="icon-cross02"></i>
            </div>
        </button>
    </div>
</div>
