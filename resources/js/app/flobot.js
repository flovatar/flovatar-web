import Alpine from "alpinejs";

Alpine.data('flobotData', (address, templates, flobotSales, flobotSaleOffer) => ({
    templates: templates,
    address: address,
    flobotSales: flobotSales,
    flobotSaleOffer: flobotSaleOffer,
    isLoading: true,
    flobot: null,
    sale: null,
    components: null,
    currentPrice : null,
    currentPriceChange : null,
    changePriceOpen: false,
    optionalIds: {},
    flobotName: '',
    showSale: false,
    showGift: false,
    currentGiftAddress: '',
    currentCreatorName: null,
    currentOwnerName: null,
    init(){
        let self = this;

        if(this.flobotSaleOffer) {
            this.currentPrice = parseFloat(this.flobotSaleOffer.price);
            this.currentPriceChange = this.currentPrice;
        }
    },
    isDapperSale(){
        return (this.flobotSaleOffer && this.flobotSaleOffer.is_dapper);
    },
    showSaleBtn(){
        let sameWallet =  (this.isDapperSale() && this.isDapperWallet()) || (!this.isDapperSale() && !this.isDapperWallet())
        return this.isSale() && !this.isOwned() && !this.showSale && sameWallet;
    },
    getDapperListing(){
        return (this.flobotSaleOffer && this.flobotSaleOffer.is_dapper);
    },
    combinationUrl(){
        let extraParams = '';
        if(this.accessoryId()){
            extraParams += '&accessory='+this.accessoryId();
        }
        if(this.hatId()){
            extraParams += '&hat='+this.hatId();
        }
        if(this.eyeglassesId()){
            extraParams += '&eyeglasses='+this.eyeglassesId();
        }
        if(this.backgroundId()){
            extraParams += '&background='+this.backgroundId();
        }
        return '/builder/flobot#combination='+this.combination()+extraParams;
    },
    loadFlobot(flobot){
        this.flobot = flobot;
        this.isLoading = false;
        if(this.flobot && this.creatorAddress()){
            window.getAddressProfile(this.creatorAddress());
            window.getAddressOwnerProfile(this.address);
        }
    },
    loadSale(sale){
        this.sale = sale;
        this.isLoading = false;
        if(this.sale) {
            this.currentPrice = parseFloat(this.sale.price);
            this.currentPriceChange = this.currentPrice;
            if(this.creatorAddress()){
                window.getAddressProfile(this.creatorAddress());
                window.getAddressOwnerProfile(this.address);
            }
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
    sendGift(){
        if(this.flobot && this.isOwned() && this.currentGiftAddress) {
            window.flobotSendGift(this.flobot.id, this.currentGiftAddress);
        }
    },
    isSale() {
        return (this.flobotSaleOffer && this.flobot);
    },
    isFlobot() {
        return !this.isSale();
    },
    isOwned(){
        if(this.user && this.address){
            return this.user.addr == this.address;
        }
        return null;
    },
    meta(){
        if(this.flobot){
            return this.flobot;
        } else if(this.sale){
            return this.sale;
        }
        return null;
    },
    dustPerDay(){
        let score = (3 + parseFloat(this.rarityText())) * 0.9144716544;
        return new Intl.NumberFormat('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2, fractionDigits: 2}).format(parseFloat(score));
    },
    hasFlobit(category){
        if(category == 'accessory'){
            return this.accessoryId();
        } else if(category == 'hat'){
            return this.hatId();
        } else if(category == 'eyeglasses'){
            return this.eyeglassesId();
        } else if(category == 'background'){
            return this.backgroundId();
        }
        return false;
    },
    accessoryId(){
        return this.meta() ? this.meta().accessoryId : null;
    },
    hatId(){
        return this.meta() ? this.meta().hatId : null;
    },
    eyeglassesId(){
        return this.meta() ? this.meta().eyeglassesId : null;
    },
    backgroundId(){
        return this.meta() ? this.meta().backgroundId : null;
    },
    curBackgroundId(){
        let meta = this.meta();
        let curBackgroundId = (this.optionalIds['background'] && this.getTemplateId(this.optionalIds['background'])) ? this.getTemplateId(this.optionalIds['background']) : (meta && meta.backgroundId ? meta.backgroundId : '');
        return curBackgroundId;
    },
    name(){
        if(this.isFlobot()){
            return this.meta().name;
        }
        return this.meta() ? this.meta().metadata.name : '';
    },
    mint(){
        return this.meta() ? this.meta().metadata.mint : '';
    },
    combination(){
        return this.meta() ? this.meta().metadata.combination : '';
    },
    creatorAddress(){
        if(this.currentCreatorName){
            return this.currentCreatorName;
        }
        return this.meta() ? this.meta().metadata.creatorAddress : '';
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
        return this.meta() ? this.meta().metadata.creatorAddress : '';
    },
    ownerAddressName(){
        if(this.currentOwnerName){
            return this.currentOwnerName+'.find';
        }
        return this.address;
    },
    rarityText(){
        return this.meta() ? window.getRarityScore(this.meta()) : 0;
    },
    getRarityCountText(type){
        if(this.meta() && this.meta().metadata){
            return this.meta().metadata.rarity;
        }
        return '';
    },
    getRarityClass(){
        if(this.meta() && this.meta().metadata){
            return this.meta().metadata.rarity;
        }
        return 'common';
    },
    loggedIn(){
        return (this.user && this.profile && this.profile.balance);
    },
    createSale(){
        if(this.flobot && this.isOwned() && this.currentPrice) {
            console.log('create flobot sale');
            window.createFlobotSale(this.flobot.id, this.currentPrice);
        }
    },
    changePrice(){
        if(this.sale && this.isOwned() && this.currentPriceChange) {
            console.log('change price');
            window.updateFlobotSale(this.sale.id, this.currentPriceChange);
        }
    },
    cancelSale(){
        if(this.isSale() && this.isOwned()) {
            console.log('cancel sale');
            window.cancelFlobotSale(this.flobot.id, this.flobotSaleOffer.listing_id);
        }
    },
    buySale(){
        if(this.isSale() && !this.isOwned()) {
            console.log('buy sale');
            window.buyFlobotSale(this.address, this.flobotSaleOffer.flow_id, this.flobotSaleOffer.price, this.flobotSaleOffer.listing_id);
        }
    },
    setBackground(){
        console.log('set background');
        if(this.optionalIds['background'] && this.isOwned() && this.isFlobot() && this.loggedIn()){
            //console.log('set background execute');
            window.flobotAddBackground(this.flobot.id, this.optionalIds['background']);
        }
    },
    setOptional(category){
        console.log('set optional');
        if(category == 'background' && this.optionalIds[category] && this.isOwned() && this.isFlobot() && this.loggedIn()){
            //console.log('set optional execute');
            window.flobotAddBackground(this.flobot.id, this.optionalIds[category]);
        }
    },
    removeOptional(category){
        console.log('set optional');
        if(category == 'background' && this.hasFlobit(category) && this.isOwned() && this.isFlobot() && this.loggedIn()){
            //console.log('set optional execute');
            window.flobotRemoveBackground(this.flobot.id);
        }
    },
    setName(){
        console.log('set name');
        if(this.optionalIds[category] && this.isOwned() && this.isFlobot() && this.loggedIn()){
            //console.log('set name execute');
            window.flobotSetName(this.flobot.id, this.flobotName);
        }
    },
    refreshAvatar(){
        if(this.flobot) {
            window.getFlobot(address, this.flobot.id);
            //window.getFlobotSale(address, this.flobot.id);
        }
    },
    filterComp(category){
        return _.filter(this.components, function(comp){ return comp.category == category; }).sort(function (l,r){
            if(l.name > r.name){
                return 1;
            } else if(l.name < r.name){
                return -1;
            } else {
                return 0;
            }
        });
    },
    getTemplate(id){
        let comp = _.find(this.components, function(item) { return parseInt(item.id, 10) == parseInt(id, 10); });
        if (comp) {
            return _.find(this.templates, { 'flow_id': parseInt(comp.templateId, 10)});
        }
        return null;
    },
    getTemplateId(id){
        let comp = _.find(this.components, function(item) { return parseInt(item.id, 10) == parseInt(id, 10); });
        return comp ? comp.templateId : null;
    },
    setDiscordProfile(){
        /*
        client.user.setAvatar('./avatar.png')
            .then(user => console.log(`New avatar set!`))
            .catch(console.error);
            */
    },
    getFlobotTemplateIds(){
        var meta = this.meta();
        if(meta && this.combination()){
            let arr = window.signatureToArray(this.combination());
            arr.hat = meta.hatId;
            arr.eyeglasses = meta.eyeglassesId;
            arr.accessory = meta.accessoryId;
            arr.background = meta.backgroundId;
            return arr;
        } else {
            return null;
        }
    },
    getFlobotTemplates(){
        let templateIds = this.getFlobotTemplateIds();
        if(templateIds) {
            let self = this;
            let templates = _.map(templateIds, function (item) {
                return self.getTemplate(item);
            });
            return templates;
        } else {
            return {};
        }
    },
    getFlobotBasicTemplates(){
        if(this.combination()) {
            let templateIds = window.signatureToFlobotTemplateArray(this.combination());
            if (templateIds) {
                let self = this;
                let templates = _.map(templateIds, function (item) {
                    let template = _.find(self.templates, function(templ) { return templ.flow_id == parseInt(item,10); });
                    return template;
                });
                return templates;
            } else {
                return {};
            }
        } else {
            return {};
        }
    },
    getFlobotOptionalTemplates(){
        var meta = this.meta();
        if(meta) {
            let templateIds = {};
            templateIds.hat = meta.hatId;
            templateIds.eyeglasses = meta.eyeglassesId;
            templateIds.accessory = meta.accessoryId;
            templateIds.background = meta.backgroundId;
            if (templateIds) {
                let self = this;
                let templates = _.map(templateIds, function (item) {
                    let template = _.find(self.templates, { 'flow_id': parseInt(item,10)});
                    return template;
                });
                return templates;
            } else {
                return {};
            }
        } else {
            return {};
        }
    },
    getFlobotBasicTemplate(category){
        let templates = this.getFlobotBasicTemplates();
        return templates ? _.find(templates, function(item){ return item ? item.category == category : false; }) : null;
    },
    getFlobotOptionalTemplate(category){
        let templates = this.getFlobotOptionalTemplates();
        return templates ? _.find(templates, function(item){ return item ? item.category == category : false; }) : null;
    },
    templateBasicSvg(category){
        let template = this.getFlobotBasicTemplate(category);
        return (template) ? window.wrapSvg(template.svg) : '';
    },
    templateBasicName(category){
        let template = this.getFlobotBasicTemplate(category);
        return (template) ? template.name : category;
    },
    templateBasicRarity(category){
        let template = this.getFlobotBasicTemplate(category);
        return (template) ? template.rarity : 'N/A';
    },
    templateUsedBy(category){
        let template = this.getFlobotBasicTemplate(category);
        let extraS = (template && template.burned > 1) ? 's' : '';
        return (template) ? template.burned+' Flobot'+extraS : 'N/A';
    },
    templateOptionalSvg(category){
        let template = this.getFlobotOptionalTemplate(category);
        return (template) ? window.wrapSvg(template.svg) : '';
    },
    templateOptionalName(category){
        let template = this.getFlobotOptionalTemplate(category);
        return (template) ? template.name : category;
    },
    templateOptionalRarity(category){
        let template = this.getFlobotOptionalTemplate(category);
        return (template) ? template.rarity : 'N/A';
    },
    getComponentBasicClass(category){
        let template = this.getFlobotBasicTemplate(category);
        return (template) ? template.rarity : 'common';
    },
    getComponentOptionalClass(category){
        let template = this.getFlobotOptionalTemplate(category);
        return (template) ? template.rarity : 'common';
    }
}));

window.initFlobot = function(dispatch, address, flobotId){
    window.dispatch = dispatch;
    window.getFlobot(address, flobotId);
    //window.getFlobotSale(address, flobotId);
}
