import Alpine from 'alpinejs';

/**
 * Flobit TYPE page (/flobits/{templateFlowId}). The page carries a snapshot (static build / server render);
 * on load it refreshes listings/floor/sales from GET /api/v2/templates/{id} (edge-cached 60 s).
 */
Alpine.data('flobitTypeData', (snapshot) => ({
    data: snapshot,
    init(){
        const id = this.data && this.data.template ? this.data.template.id : null;
        if(!id){
            return;
        }
        let self = this;
        window.axios.get('/api/v2/templates/' + id).then(function(response){
            if(response.data && response.data.template){
                self.data = response.data;
            }
        }).catch(function(error){
            console.log('flobit type refresh failed');
        });
    },
    formatPrice(price){
        return window.formatPrice(price);
    },
    floor(currency){
        const l = _.find(this.data.listings, {currency: currency});
        return l ? l.floor : null;
    },
    minted(){
        const max = this.data.template.max_mintable;
        return this.data.minted + (max ? ' / ' + max : '');
    },
    listedText(){
        return _.sumBy(this.data.listings, 'count');
    },
    floorText(){
        const parts = _.map(this.data.listings, (l) => window.formatPrice(l.floor) + ' ' + l.currency);
        return parts.length ? parts.join(' / ') : '-';
    },
    salesText(){
        const s = this.data.sales;
        return s.count ? window.formatPrice(s.avg) + ' / ' + window.formatPrice(s.max) : '-';
    },
}));
