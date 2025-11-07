import Alpine from "alpinejs";

Alpine.data('accessoryData', (address, templates, accessorySaleOffer) => ({
    templates: templates,
    address: address,
    accessorySaleOffer: accessorySaleOffer,
    isLoading: true,
    component: null,
    sale: null,
    currentPrice : null,
    currentPriceChange : null,
    changePriceOpen: false,
    showSale: false,
    showGift: false,
    currentGiftAddress: '',
    init(){
        if(this.accessorySaleOffer) {
            this.currentPrice = parseFloat(this.accessorySaleOffer.price);
            this.currentPriceChange = this.currentPrice;
        }
    },
    isDapperSale(){
        return (this.accessorySaleOffer);
    },
    showSaleBtn(){
        let sameWallet = (this.isDapperSale() && this.isDapperWallet()) || (!this.isDapperSale() && !this.isDapperWallet())
        return this.isSale() && !this.isOwned() && !this.showSale && sameWallet;
    },
    getDapperListing(){
        return (this.flovatarSaleOffer && this.flovatarSaleOffer.is_dapper);
    },
    sendGift(){
        console.log('send component gift clicked!');
        if(this.component && this.isOwned() && this.currentGiftAddress) {
            console.log('send component gift');
            window.componentSendGift(this.component.id, this.currentGiftAddress);
        }
    },
    isSale() {
        return (this.isDapperSale() && this.component) ||  (this.sale && !this.component);
    },
    isComponent() {
        return !this.isSale();
    },
    isOwned(){
        if(this.user && this.address){
            return this.user.addr == this.address;
        }
        return null;
    },
    isFlobit(){
        return (this.category() != 'spark' && this.category() != 'boost' && this.category() != 'land' && this.category() != 'fub' && this.category() != 'flobotkit');
    },
    getMarketplaceUrl(){
        if(this.category() == 'boost' || this.category() == 'land' || this.category() == 'flobotkit' || this.category() == 'fub'){
            return '/marketplace#templatecat=' + this.category() + '&rarity=' + this.rarity();
        }  else {
            return '/marketplace#templatecat=flobits&templateid=' + this.templateId()
        }
    },
    meta(){
        if(this.component){
            return this.component;
        } else if(this.sale){
            return this.sale.metadata;
        }
        return null;
    },
    template(){
        if(!this.meta()){
            return null;
        }
        return  _.find(this.templates, { 'flow_id': parseInt(this.meta().templateId, 10)});
    },
    svg(){
        if(!this.meta()){
            return '';
        }
        let template = _.find(this.templates, { 'flow_id': parseInt(this.meta().templateId, 10)});
        return template ? wrapSvg(template.svg) : '';
    },
    name(){
        if(!this.meta()){
            return '';
        }
        return this.category() == 'boost' ? this.meta().name + ' Booster' : this.meta().name;
    },
    description(){
        return this.meta() ? this.meta().description : '';
    },
    flobitId(){
        return this.meta() ? this.meta().id : '';
    },
    templateId(){
        return this.meta() ? this.meta().templateId : '';
    },
    mint(){
        let template = this.template();
        return (this.meta() && template) ? this.meta().mint + ' / ' + template.max_mintable : '';
    },
    category(){
        return this.meta() ? this.meta().category : '';
    },
    color(){
        return this.meta() ? this.meta().color : '';
    },
    series(){
        return this.template() ? this.template().series : '';
    },
    burned(){
        return this.template() ? this.template().flovatar_components_count : '';
    },
    maxMintable(){
        return this.template() ? this.template().max_mintable : '';
    },
    rarity(){
        return this.template() ? this.template().rarity : '';
    },
    loggedIn(){
        return (this.user && this.profile && this.profile.balance);
    },
    createSale(){
        if(this.component && this.isOwned() && this.currentPrice) {
            console.log('create component sale');
            window.createComponentSale(this.component.id, this.currentPrice);
        }
    },
    changePrice(){
        if(this.sale && this.isOwned() && this.currentPriceChange) {
            console.log('change price');
            window.updateComponentSale(this.sale.id, this.currentPriceChange);
        }
    },
    cancelSale(){
        if(this.isSale() && this.isOwned()) {
            console.log('cancel sale');
            window.cancelComponentSale(this.isDapperSale() ? this.component.id : this.sale.id, this.isDapperSale() ? this.accessorySaleOffer.listing_id : this.sale.id);
        }
    },
    buySale(){
        if(this.isSale() && !this.isOwned()) {
            console.log('buy sale');
            let saleId = this.isDapperSale() ? this.accessorySaleOffer.listing_id : this.sale.id;
            let price = this.isDapperSale() ? this.accessorySaleOffer.price : this.sale.price;
            let tokenId = this.isDapperSale() ? this.accessorySaleOffer.flow_id : this.sale.id;
            window.buyComponentSale(this.address, tokenId, price, saleId);
        }
    }
}));

window.initAccessory = function(dispatch, address, accessoryId){
    window.dispatch = dispatch;
    window.getDustAccessory(address, accessoryId);
    window.getDustAccessorySale(address, accessoryId);
}
