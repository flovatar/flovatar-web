import Alpine from "alpinejs";

Alpine.data('marketplaceData', (templates, stats) => ({
    sales: null,
    stats: stats,
    templates: templates,
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
    showDapper: false,
    selectedMarketplace: 'blocto',
    init(){
        let self = this;
        if(this.currentWallet == 'dapper'){
            this.showDapper = true;
            this.selectedMarketplace = 'dapper';
        }
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
        this.$watch('showDapper', () => { self.searchResults(); });
        this.$watch('selectedMarketplace', () => { self.showDapper = (self.selectedMarketplace == 'dapper'); });
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
                    } else if(paramArr[0] == 'dapper'){
                        self.showDapper = paramArr[1];
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
            dapper: this.showDapper
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
        if(params.dapper === true){
            hashParams.dapper = params.dapper;
        }
        return hashParams;
    },
    getFiltersCount() {
        let params = this.getSearchParams();
        params.sortby = 'datenew';
        params.page = 1;
        let hashParams = this.getHashParams(params);
        return (this.showDapper && Object.keys(hashParams).length > 0) ? Object.keys(hashParams).length - 1 : Object.keys(hashParams).length;
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
    getTemplateName(templateId){
        if(templateId == 'all' || !templateId){
            return '';
        }
        let template = _.find(this.templates, (template) => {
            return template.flow_id == parseInt(templateId, 10);
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
    },
    searchResults(){
        let self = this;
        this.nextPage = 1;
        if(this.minPrice && this.maxPrice && this.minPrice > this.maxPrice){
            this.minPrice = this.maxPrice;
        }
        window.searchApi('/search', this.getSearchParams()).then(function (response) {
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
            window.searchApi('/search', this.getSearchParams()).then(function (response) {
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
    isDustCollectible(sale){
        return (sale.item_type == 'App\\Models\\DustCollectible');
    },
    isFlobot(sale){
        return (sale.item_type == 'App\\Models\\Flobot');
    },
    // Flovatars, Flobots and DUST collectibles carry their own row; Flobits (components) are described by their template
    isWholeItem(sale){
        return this.isFlovatar(sale) || this.isDustCollectible(sale) || this.isFlobot(sale);
    },
    isFlobit(sale){
        if(this.isWholeItem(sale)){
            return false;
        } else {
            let item = this.getItem(sale);
            if(!item){
                return false;
            }
            return (item.category != 'spark' && item.category != 'boost' && item.category != 'land' && item.category != 'fub' && item.category != 'flobotkit') ? true : false;
        }
    },
    getItem(sale){
        if(!sale.item){
            return null;
        }
        return this.isWholeItem(sale) ? sale.item : _.find(this.templates, {  'id': sale.item.flovatar_template_id });
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
    getSvg(sale){
        let item = this.getItem(sale);
        if(!this.isFlovatar(sale)) {
            return item ? wrapSvg(item.svg) : '';
        } else if(this.isDustCollectible(sale)) {
            return item.svg;
        } else {
            let meta = item.flow_metadata;

            if(!meta){
                return '';
            }
            let accessory = '';
            let hat ='';
            let eyeglasses = '';
            let background ='';

            if(meta.accessoryId){
                accessory = _.find(window.templates, function(item){return item.flow_id == parseInt(meta.accessoryId, 10);});
                accessory = accessory ? accessory.svg : '';
            }
            if(meta.hatId){
                hat = _.find(window.templates, function(item){return item.flow_id == parseInt(meta.hatId, 10);});
                hat = hat ? hat.svg : '';
            }
            if(meta.eyeglassesId){
                eyeglasses = _.find(window.templates, function(item){return item.flow_id == parseInt(meta.eyeglassesId, 10);});
                eyeglasses = eyeglasses ? eyeglasses.svg : '';
            }
            if(meta.backgroundId){
                background = _.find(window.templates, function(item){return item.flow_id == parseInt(meta.backgroundId, 10);});
                background = background ? background.svg : '';
            }

            return window.wrapSvg(item.svg+accessory+eyeglasses+hat)
        }

    },

    getSvgUrl(sale){
        // headless API rows: R2 thumbnail when it looks the same as the SVG (opaque, not animated); else the SVG
        if(sale.list_thumb){
            return sale.list_thumb;
        }
        let item = this.getItem(sale);
        if(this.isDustCollectible(sale)){
            return '/api/image/collectible/'+sale.flow_id;
        }
        if(this.isFlobot(sale)){
            return '/api/image/flobot/'+sale.flow_id;
        }
        if(this.isFlovatar(sale)){
            return '/api/image/'+sale.flow_id;
        }
        return item ? '/api/image/template/'+item.flow_id : '';
    },

    svg(){
        let meta = this.meta();
        if(!meta){
            return '';
        }



        return window.wrapSvg(meta.metadata.svg+accessory+eyeglasses+hat)
    },

    getUrl(sale){
        if(this.isFlovatar(sale)){
            return window.itemUrl('flovatars', sale.flow_id, sale.flow_address);
        }
        if(this.isDustCollectible(sale)){
            return window.itemUrl('collectibles', sale.flow_id, sale.flow_address);
        }
        if(this.isFlobot(sale)){
            return window.itemUrl('flobots', sale.flow_id, sale.flow_address);
        }
        return '/components/'+sale.flow_id+'/'+sale.flow_address;
    },
    getName(sale){
        let item = this.getItem(sale);
        return item ? item.name : '';
    },
    getType(sale){
        if(this.isDustCollectible(sale)){
            return 'Dust Collectible'
        }
        if(this.isFlobot(sale)){
            return 'Flobot';
        }
        return this.isFlovatar(sale) ? 'Flovatar' : 'Component';
    },
    getRarityById(id){
        let template = _.find(this.templates, (el) => { return el.id == parseInt(id, 10); });
        return template ? template.rarity : '';
    },
    getRarityClassById(id){
        let template = _.find(this.templates, (el) => { return el.id == parseInt(id,10); });
        return template ? template.rarity : '';
    },
    getRarityScoreNumber(sale){
        return (this.isFlovatar(sale)) ? window.getRarityScore(sale.item.flow_metadata) : 0;
    },
    getRarityText(sale){
        if(!sale.item){
            return '';
        }
        if(this.isFlobot(sale)){
            return sale.item.rarity || '';
        }
        if(!this.isFlovatar(sale) && sale.item.flovatar_template_id){
            return this.getRarityById(sale.item.flovatar_template_id);
        } else if(this.isFlovatar(sale) && sale.item.flow_metadata && sale.item.flow_metadata.metadata){
            return window.getFlovatarRarityText(sale.item.flow_metadata);
        } else if(this.isDustCollectible(sale)){
            return window.getCollectibleRarityScoreFromCounts(sale.item.legendary_count, sale.item.epic_count, sale.item.rare_count);
        } else {
            return '';
        }
    },
    hasMint(sale){
        if(this.isWholeItem(sale)){
            return true;
        } else {
            return !!sale.item && sale.item.mint != null;
        }
    },
    getMint(sale){
        let item = this.getItem(sale);
        if(this.isWholeItem(sale)){
            return '#' + sale.flow_id;
        } else {
            let max = item ? item.max_mintable : '';
            return this.hasMint(sale) ? '#' + sale.item.mint + ' / '+ max : max;
        }
    },
    getBurnText(sale){
        if(this.isWholeItem(sale) || !sale.flow_metadata || !sale.flow_metadata.metadata){
            return '';
        }
        let template = _.find(this.templates, (el) => { return el.id == sale.flow_metadata.metadata.templateId; });
        return template ? template.burned + '/' + template.max_mintable : '';
    },
    getCategory(sale){
        let item = this.getItem(sale);
        if(this.isDustCollectible(sale)){
            return 'collectible';
        }
        if(this.isFlobot(sale)){
            return 'flobot';
        }
        return this.isFlovatar(sale) ? 'flovatar' : (item ? item.category : '');
    },
    getRarityColor(sale){
        if(this.isWholeItem(sale)){
            return this.getRarityText(sale);
        }
        return sale.item ? this.getRarityClassById(sale.item.flovatar_template_id) : '';
    },
    getFlovatarRarityImages(sale){
        let item = this.getItem(sale);
        return (this.isFlovatar(sale) && item && item.flow_metadata && item.flow_metadata.metadata) ? window.getFlovatarRarityImagesFromMeta(item.flow_metadata.metadata) : '';
    }
}));

window.initMarketplace = function(dispatch){
    window.dispatch = dispatch;
}
