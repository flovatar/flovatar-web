{{-- Pages of collectionData.pageSize cards for big wallets (thousands of Flobits); hidden when everything fits on one page --}}
<div class="collection-pager" x-show="pageCount('{{ $tab }}') > 1" x-cloak style="display:flex;align-items:center;justify-content:center;gap:16px;margin:24px 0;">
    <button type="button" class="btn btn-sm btn-gray" @click.prevent="setPage('{{ $tab }}', currentPage('{{ $tab }}') - 1, $el)" x-bind:disabled="currentPage('{{ $tab }}') <= 1"><span class="inner">&lsaquo; Prev</span></button>
    <span class="item-no" x-text="'Page ' + currentPage('{{ $tab }}') + ' / ' + pageCount('{{ $tab }}') + ' (' + fullList('{{ $tab }}').length + ' items)'"></span>
    <button type="button" class="btn btn-sm btn-gray" @click.prevent="setPage('{{ $tab }}', currentPage('{{ $tab }}') + 1, $el)" x-bind:disabled="currentPage('{{ $tab }}') >= pageCount('{{ $tab }}')"><span class="inner">Next &rsaquo;</span></button>
</div>
