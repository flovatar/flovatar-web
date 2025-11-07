import Alpine from "alpinejs";

Alpine.data('collectibleData', (address, collectibleId, templates, collectibleSaleOffer) => ({
    templates: templates,
    address: address,
    collectibleSaleOffer: collectibleSaleOffer,
    isLoading: true,
    geocoder: null,
    collectible: null,
    collectibleId: collectibleId,
    sale: null,
    currentPrice : null,
    currentPriceChange : null,
    changePriceOpen: false,
    showSale: false,
    showGift: false,
    currentGiftAddress: '',
    currentCreatorName: null,
    currentOwnerName: null,
    collectibleName: '',
    collectibleLat: 0,
    collectibleLon: 0,
    searchAddress: '',
    foundAddress: '',
    collectibleStory: '',
    init(){
        if(this.collectibleSaleOffer) {
            this.currentPrice = parseFloat(this.collectibleSaleOffer.price);
            this.currentPriceChange = this.currentPrice;
        }
        if(google){
            this.geocoder = new google.maps.Geocoder();
        }
    },
    loadCollectible(collectible){
        console.log('load collectible');
        this.collectible = collectible;
        this.isLoading = false;
        if(this.collectible && this.creatorAddress()){
            window.getAddressProfile(this.creatorAddress());
            window.getAddressOwnerProfile(this.address);
            this.searchAddress = this.position();
            if(this.position()){
                this.geocodeAddress();
            }
        }
    },
    creatorAddress(){
        if(this.currentCreatorName){
            return this.currentCreatorName+'.find';
        }
        return this.collectible ? this.collectible.creatorAddress : '';
    },
    ownerAddress(){
        if(this.currentOwnerName){
            return this.currentOwnerName;
        }
        return this.address;
    },
    creatorAddressName(){
        if(this.currentCreatorName){
            return this.currentCreatorName+'.find';
        }
        return this.collectible ? this.collectible.creatorAddress : '';
    },
    ownerAddressName(){
        if(this.currentOwnerName){
            return this.currentOwnerName+'.find';
        }
        return this.address;
    },
    geocodeAddress(){
        let self = this;
        if(this.geocoder){
            this.geocoder.geocode({ address: this.searchAddress }, (results, status) => {
                if (status === "OK" && results.length > 0) {
                    // Display response in the console
                    //console.log(results);
                    self.foundAddress = results[0].formatted_address;
                    let pos = results[0].geometry.location;
                    self.collectibleLat = pos.lat();
                    self.collectibleLon = pos.lng();
                } else {
                    self.foundAddress = '';
                    self.collectibleLat = 0;
                    self.collectibleLon = 0;
                    //alert("Geocode error: " + status);
                }
            });
        }
    },
    isDapperSale(){
        return (this.collectibleSaleOffer);
    },
    showSaleBtn(){
        let sameWallet = (this.isDapperListing() && this.isDapperWallet()) || (!this.isDapperListing() && !this.isDapperWallet());
        return this.isSale() && !this.isOwned() && !this.showSale && sameWallet;
    },
    isDapperListing(){
        return (this.collectibleSaleOffer && this.collectibleSaleOffer.is_dapper);
    },
    sendGift(){
        console.log('send collectible gift clicked!');
        if(this.collectible && this.isOwned() && this.currentGiftAddress) {
            console.log('send collectible gift');
            window.collectibleSendGift(this.collectible.id, this.currentGiftAddress);
        }
    },
    isSale() {
        return this.isDapperSale() != null;
    },
    isCollectible() {
        return !this.isSale();
    },
    isOwned(){
        if(this.user && this.address){
            return this.user.addr == this.address;
        }
        return null;
    },
    meta(){
        if(this.collectible){
            return this.collectible;
        } else if(this.sale){
            return this.sale.metadata;
        }
        return null;
    },
    svg(){
        return this.collectible ? this.collectible.svg : '';
    },
    name(){
        return this.collectible ? this.collectible.name : '';
    },
    description(){
        return this.collectible ? this.collectible.description : '';
    },
    mint(){
        return this.collectible ? this.collectible.mint : '';
    },
    series(){
        return this.collectible ? this.collectible.series : '';
    },
    rarity(){
        return this.getRarityClass();
    },
    story(){
        return this.collectible && this.collectible.bio.story ? this.collectible.bio.story : '';
    },
    position(){
        return this.collectible && this.collectible.bio.position ? this.collectible.bio.position : '';
    },
    setName(){
        console.log('set name');
        if(this.isOwned() && this.isCollectible() && this.loggedIn()){
            //console.log('set name execute');
            window.collectibleSetName(this.collectible.id, this.collectibleName);
        }
    },
    setPosition(){
        console.log('set position');
        if(this.isOwned() && this.isCollectible() && this.loggedIn()){
            //console.log('set name execute');
            window.collectibleSetPosition(this.collectible.id, this.collectibleLat, this.collectibleLon);
        }
    },
    addStory(){
        console.log('add story');
        if(this.isOwned() && this.isCollectible() && this.loggedIn()){
            //console.log('set name execute');
            window.collectibleAddStory(this.collectible.id, this.collectibleStory);
        }
    },
    rarityText(){
        return this.collectible ? window.getCollectibleRarityScore(this.collectible, this.templates) : 0;
    },
    getRarityCountText(type){
        let count = 0;
        if(this.collectible && this.collectible.layers){
            _.each(this.collectible.layers, item => {
                let template = _.find(self.templates, temp => { return temp.flow_id == item; });
                if(template){
                    if(template.rarity == type){
                        count++;
                    }
                }
            });
        }
        return count;
    },
    getRarityClass(){
        if(this.collectible){
            return window.getCollectibleRarityText(this.collectible, this.templates);
        }
        return 'common';
    },

    combination(){
        return this.collectible ? this.collectible.combination : '';
    },
    combinationUrl(){
        return '/builder/dust#combination='+this.combination();
    },
    loggedIn(){
        return (this.user && this.profile && this.profile.balance);
    },
    createSale(){
        if(this.collectible && this.isOwned() && this.currentPrice) {
            console.log('create component sale');
            window.createCollectibleSale(this.collectible.id, this.currentPrice);
        }
    },
    cancelSale(){
        if(this.isSale() && this.isOwned()) {
            console.log('cancel sale');
            window.cancelCollectibleSale(this.isDapperSale() ? this.collectible.id : this.sale.id, this.isDapperSale() ? this.collectibleSaleOffer.listing_id : this.sale.id);
        }
    },
    buySale(){
        if(this.isSale() && !this.isOwned()) {
            console.log('buy sale');
            let saleId = this.isDapperSale() ? this.collectibleSaleOffer.listing_id : this.sale.id;
            let price = this.isDapperSale() ? this.collectibleSaleOffer.price : this.sale.price;
            let tokenId = this.isDapperSale() ? this.collectibleSaleOffer.flow_id : this.sale.id;
            window.buyCollectibleSale(this.address, tokenId, price, saleId);
        }
    },
    loadCreatorName(profile){
        if(profile && profile.name){
            this.currentCreatorName = profile.name;
        }
    },
    loadOwnerName(profile){
        if(profile && profile.name){
            this.currentOwnerName = profile.name;
        }
    },
    saveSvg() {
        let svg = window.wrapSvg(this.svg());
        svg = svg.replace('width="100%"', 'width="3000"').replace('height="100%"', 'height="3000"');
        const base64 = window.btoa(unescape(encodeURIComponent(svg)));
        const src = "data:image/svg+xml;base64," + base64;

        const evt = new MouseEvent("click", {
            view: window,
            bubbles: false,
            cancelable: true
        });
        const a = document.createElement("a");
        a.setAttribute("download", `psyche-likee-${this.mint()}-${ (new Date().getTime()).toString(10) }.svg`);
        a.setAttribute("href", src);
        a.setAttribute("target", '_blank');
        a.dispatchEvent(evt);
        a.remove();
    }
}));

window.initCollectible = function(dispatch, address, collectibleId){
    window.dispatch = dispatch;
    window.getDustCollectible(address, collectibleId);
}
