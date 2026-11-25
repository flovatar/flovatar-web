import Alpine from "alpinejs";

Alpine.data('salesData', (templates, address, stats) => ({
    sales: null,
    templates: templates,
    address: address,
    stats: stats,
    flovatars: [],
    components: [],
    packs: [],
    nextPage: 1,
    totalResults: null,
    minPrice: null,
    maxPrice: null,
    sortBy: 'datenew',
    searchCat: 'all',
    searchRarity: 'all',
    searchCat2: 'all',
    searchTemplate: 'all',
    showFilterPanel: false,
    init(){
        let self = this;
        this.loadSearchParams();

        this.$watch('sortBy', () => { self.searchResults(); });
        this.$watch('searchCat', () => {
            self.searchCat2 = 'all';
            self.searchTemplate = 'all';
            if(self.searchCat == 'spark'){
                self.searchRarity = 'all';
            } else if(self.searchCat == 'boost' && self.searchRarity == 'common'){
                self.searchRarity = 'all';
            }
            self.searchResults();
        });
        this.$watch('searchRarity', () => { self.searchResults(); });
        this.$watch('searchCat2', () => { self.searchTemplate = 'all'; self.searchResults(); });
        this.$watch('searchTemplate', () => {
            if(this.searchTemplate != 'all'){
                this.searchRarity = 'all';
            }
            self.searchResults();
        });


        this.searchResults();

    },
    resetFilters(){
        this.minPrice = null;
        this.maxPrice = null;
        this.sortBy = 'datenew';
        this.searchCat = 'all';
        this.searchRarity = 'all';
        this.searchCat2 = 'all';
        this.searchTemplate = 'all';
    },
    loadSearchParams(){
        let self = this;
        if(window.location.hash) {
            let hash = window.location.hash;
            hash = hash.replace('#', '');
            let params = _.split(hash, '&');
            _.each(params, function(param){
                let paramArr = _.split(param, '=');
                if(paramArr.length == 2){
                    if(paramArr[0] == 'templatecat'){
                        self.searchCat = paramArr[1];
                    } else if(paramArr[0] == 'flobitcat'){
                        self.searchCat2 = paramArr[1];
                    } else if(paramArr[0] == 'templateid'){
                        self.searchTemplate = paramArr[1];
                    } else if(paramArr[0] == 'rarity'){
                        self.searchRarity = paramArr[1];
                    } else if(paramArr[0] == 'sortby'){
                        self.sortBy = paramArr[1];
                    } else if(paramArr[0] == 'pricemin'){
                        self.minPrice = (paramArr[1] == 'null') ? null : parseInt(paramArr[1],10);
                    } else if(paramArr[0] == 'pricemax'){
                        self.maxPrice = (paramArr[1] == 'null') ? null : parseInt(paramArr[1],10);
                    } else if(paramArr[0] == 'page'){
                        self.nextPage = paramArr[1];
                    }
                }
            });
        }
    },
    getSearchParams(){
        let params = {
            templatecat: this.searchCat,
            flobitcat: this.searchCat2,
            templateid: this.searchTemplate,
            rarity: this.searchRarity,
            sortby: this.sortBy,
            pricemin: this.minPrice,
            pricemax: this.maxPrice,
            page: this.nextPage,
            address: this.address
        };
        let hashParams = this.getHashParams(params);
        if(Object.keys(hashParams).length > 0 || window.location.hash){
            window.writeHashParams(hashParams);
        }
        return params;
    },
    getHashParams(params){
        let hashParams = {};
        if(params.templatecat != 'all'){
            hashParams.templatecat = params.templatecat;
        }
        if(params.flobitcat != 'all'){
            hashParams.flobitcat = params.flobitcat;
        }
        if(params.templateid != 'all'){
            hashParams.templateid = params.templateid;
        }
        if(params.rarity != 'all'){
            hashParams.rarity = params.rarity;
        }
        if(params.sortby != 'datenew'){
            hashParams.sortby = params.sortby;
        }
        if(params.pricemin != null){
            hashParams.pricemin = params.pricemin;
        }
        if(params.pricemax != null){
            hashParams.pricemax = params.pricemax;
        }
        if(params.page > 1){
            hashParams.page = params.page;
        }
        return hashParams;
    },
    getFiltersCount() {
        let params = this.getSearchParams();
        params.sortby = 'datenew';
        params.page = 1;
        let hashParams = this.getHashParams(params);
        return Object.keys(hashParams).length;
    },
    filteredTemplates(){
        if(this.searchCat == 'flobits' && this.searchCat2 != 'all'){
            return _.filter(this.templates, (template) => {
                return template.category == this.searchCat2;
            }).sort(function(l,r){
                return (l.name > r.name) ? 1 : ((l.name < r.name) ? -1 : 0);
            });
        }
        return {};
    },
    searchResults(){
        let self = this;
        this.nextPage = 1;
        if(this.minPrice && this.maxPrice && this.minPrice > this.maxPrice){
            this.minPrice = this.maxPrice;
        }
        window.searchApi('/transactions/search', this.getSearchParams()).then(function (response) {
            self.handleSearchResults(response.data);
        })
            .catch(function (error) {
                console.log(error);
            });
    },
    handleSearchResults(sales){
        this.sales = sales.data;
        this.nextPage = (sales.current_page < sales.last_page) ? sales.current_page + 1 : null;
        this.totalResults = sales.total;
    },
    loadNextPage(){
        if(this.nextPage) {
            let self = this;
            window.searchApi('/transactions/search', this.getSearchParams()).then(function (response) {
                self.handleNextResults(response.data);
            })
                .catch(function (error) {
                    console.log(error);
                });
        }
    },
    handleNextResults(sales){
        let newSales = _.concat(this.sales, sales.data);
        this.sales = newSales;
        this.nextPage = (sales.current_page < sales.last_page) ? sales.current_page + 1 : null;
    },
    isFlovatar(sale){
        return (sale.item_type == 'App\\Models\\Flovatar');
    },
    // Flobot / DUST collectible sales carry their own row (no Flobit template)
    isOtherItem(sale){
        return sale.item_type == 'App\\Models\\Flobot' || sale.item_type == 'App\\Models\\DustCollectible';
    },
    isFlobit(sale){
        if(this.isFlovatar(sale) || this.isOtherItem(sale)){
            return false;
        } else {
            let item = this.getItem(sale);
            return (item && item.category != 'spark' && item.category != 'boost') ? true : false;
        }
    },
    getItem(sale){
        if(!sale.item){
            return null;
        }
        return (this.isFlovatar(sale) || this.isOtherItem(sale)) ? sale.item : _.find(this.templates, {  'id': sale.item.flovatar_template_id });
    },
    getSvgUrl(sale){
        // headless API rows: R2 thumbnail when it looks the same as the SVG (opaque, not animated); else the SVG
        if(sale.list_thumb){
            return sale.list_thumb;
        }
        let item = this.getItem(sale);
        if(!item){
            return '';
        }
        if(sale.item_type == 'App\\Models\\Flobot'){
            return '/api/image/flobot/'+item.flow_id;
        }
        if(sale.item_type == 'App\\Models\\DustCollectible'){
            return '/api/image/collectible/'+item.flow_id;
        }
        return this.isFlovatar(sale) ? '/api/image/'+item.flow_id : '/api/image/template/'+item.flow_id;
    },
    getSvg(sale){
        let item = this.getItem(sale);
        return item ? wrapSvg(item.svg) : '';
    },
    getUrl(sale){
        if(!sale.item){
            return '#';
        }
        if(sale.item_type == 'App\\Models\\Flobot'){
            return window.itemUrl('flobots', sale.item.flow_id, sale.item.flow_address);
        }
        if(sale.item_type == 'App\\Models\\DustCollectible'){
            return window.itemUrl('collectibles', sale.item.flow_id, sale.item.flow_address);
        }
        return this.isFlovatar(sale) ? window.itemUrl('flovatars', sale.item.flow_id, sale.item.flow_address) : '/components/'+sale.item.flow_id+'/'+sale.item.flow_address;
    },
    getName(sale){
        let item = this.getItem(sale);
        if(!item){
            return '';
        }
        if((this.isFlovatar(sale) || this.isOtherItem(sale)) && !item.name){
            return '#'+item.flow_id;
        }
        return item && item.name ? item.name : '';
    },
    getType(sale){
        if(this.isFlovatar(sale)){
            return 'Flovatar';
        } else if(sale.item_type == 'App\\Models\\Flobot'){
            return 'Flobot';
        } else if(sale.item_type == 'App\\Models\\DustCollectible'){
            return 'DUST Collectible';
        } else {
            let item = this.getItem(sale);
            if(!item){
                return 'Flobit';
            } else if(item.category == 'spark'){
                return 'Spark';
            } else if(item.category == 'boost') {
                return 'Rarity Booster';
            } else {
                return 'Flobit';
            }
        }
    },
    getColor(sale){
        return (sale.flow_buyer_address == this.address) ? '' : 'sold';
    },
    getBurnedData(cat){
        if(cat == 'spark'){
            let template = _.find(this.templates, (el) => { return el.category == 'spark'; });
            return (template) ? template.burned : 'N/A';
        } else {
            let template = _.find(this.templates, (el) => { return el.category == 'boost' && el.rarity == cat; });
            return (template) ? template.burned : 'N/A';
        }
    },
    getTemplateName(templateId){
        if(templateId == 'all' || !templateId){
            return '';
        }
        let template = _.find(this.templates, (template) => {
            return template.flow_id == parseInt(templateId,10);
        });
        return template ? template.name : '';
    },
    getPriceRange() {
        let output = '';
        if(this.minPrice && this.maxPrice){
            output = this.minPrice + ' - ' + this.maxPrice;
        } else if(this.minPrice){
            output = this.minPrice + ' - ∞';
        } else if(this.maxPrice){
            output = '0 - ' + this.maxPrice;
        } else {
            return '';
        }

        return output + ' FLOW';
    }
}));

window.initSales = function(dispatch){
    window.dispatch = dispatch;
}
