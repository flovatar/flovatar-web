import Alpine from "alpinejs";
import {geoEditor} from "../geo";

Alpine.data('flovatarData', (address, flovatarId, templates, flovatarSales, flovatarSaleOffer, dustPerDayScore) => ({
    templates: templates,
    address: address,
    flovatarId: flovatarId,
    flovatarSales: flovatarSales,
    flovatarSaleOffer: flovatarSaleOffer,
    dustPerDayScore: dustPerDayScore,
    ...geoEditor('flovatarLat', 'flovatarLon'), // GEO Location editor (OpenStreetMap), resources/js/geo.js
    isLoading: true,
    flovatar: null,
    sale: null,
    components: null,
    currentPrice : null,
    currentPriceChange : null,
    changePriceOpen: false,
    optionalIds: {},
    flovatarName: '',
    flovatarLat: 0,
    flovatarLon: 0,
    flovatarEarnedDust: 0,
    flovatarAirdroppedDust: 0,
    flovatarAirdropCount: 0,
    searchAddress: '',
    foundAddress: '',
    flovatarStory: '',
    showSale: false,
    showGift: false,
    currentGiftAddress: '',
    currentCreatorName: null,
    currentOwnerName: null,
    mode3dEnabled: false,
    assetSelect: 'svg',
    showZoomModal: false,
    init(){
        let self = this;
        this.$watch('mode3dEnabled', () => { self.sync3dViewer(); });
        this.$watch('searchAddress', () => { self.geocodeAddress(); });

        if(this.flovatarSaleOffer) {
            this.currentPrice = parseFloat(this.flovatarSaleOffer.price);
            this.currentPriceChange = this.currentPrice;
        }

    },
    isDapperSale(){
        return (this.flovatarSaleOffer);
    },
    showSaleBtn(){
        let sameWallet = (this.isDapperSale() && this.isDapperWallet()) || (!this.isDapperSale() && !this.isDapperWallet())
        return this.isSale() && !this.isOwned() && !this.showSale && sameWallet;
    },
    getDapperListing(){
        return (this.flovatarSaleOffer && this.flovatarSaleOffer.is_dapper);
    },
    claimDust(){
        window.claimFlovatarDust(this.flovatarId);
    },
    claimAirdrop(){
        window.claimFlovatarAirdrop(this.flovatarId);
    },
    loadFlovatarClaimables(claimables){
        this.flovatarEarnedDust = claimables.flovatarCommunityDust;
        this.flovatarAirdroppedDust = claimables.flovatarDust;
        this.flovatarAirdropCount = claimables.flovatarComponents.length;
    },
    sync3dViewer(){
        if(!this.mode3dEnabled){
            return false;
        }

        let self = this;
        let tempArray = [];
        let flovTemp = this.getFlovatarTemplateIds();
        _.each(flovTemp, (category) => {
            tempArray.push(category);
        });

        document.getElementById("visualiser").contentWindow.postMessage(
            {
                message: "setParts",
                parts: tempArray,
                background: flovTemp.background,
                style: 'shiny',
            },
            "*" // Actual URL where this needs to be sent
        );
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
        return '/builder#combination='+this.combination()+extraParams;
    },
    loadFlovatar(flovatar){
        this.flovatar = flovatar;
        this.isLoading = false;
        if(this.flovatar && this.creatorAddress()){
            window.getAddressProfile(this.creatorAddress());
            window.getAddressOwnerProfile(this.address);
            this.searchAddress = this.position();
            if(this.position()){
                this.geocodeAddress();
            }
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
        if(this.flovatar && this.isOwned() && this.currentGiftAddress) {
            window.flovatarSendGift(this.flovatar.id, this.currentGiftAddress);
        }
    },
    isSale() {
        return (this.isDapperSale() && this.flovatar) || (this.sale && !this.flovatar);
    },
    isFlovatar() {
        return !this.isSale();
    },
    isOwned(){
        if(this.user && this.address){
            return this.user.addr == this.address;
        }
        return null;
    },
    meta(){
        if(this.flovatar){
            return this.flovatar;
        } else if(this.sale){
            return this.sale;
        }
        return null;
    },
    dustPerDay(){
        let score = (3 + parseFloat(this.rarityText())) * this.dustPerDayScore;
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
    svg(){
        let meta = this.meta();
        if(!meta){
            return '';
        }

        let accessory = '';
        let hat ='';
        let eyeglasses = '';
        let background ='';

        let curAccessoryId = (this.optionalIds['accessory'] && this.getTemplateId(this.optionalIds['accessory'])) ? this.getTemplateId(this.optionalIds['accessory']) : (meta.accessoryId ? meta.accessoryId : '');
        accessory = _.find(window.templates, function(item){return item.flow_id == parseInt(curAccessoryId, 10);});
        accessory = accessory ? accessory.svg : '';

        let curHatId = (this.optionalIds['hat'] && this.getTemplateId(this.optionalIds['hat'])) ? this.getTemplateId(this.optionalIds['hat']) : (meta.hatId ? meta.hatId : '');
        hat = curHatId ? _.find(window.templates, function(item){return item.flow_id == parseInt(curHatId, 10);}) : '';
        hat = hat ? hat.svg : '';

        let curEyeglassesId = (this.optionalIds['eyeglasses'] && this.getTemplateId(this.optionalIds['eyeglasses'])) ? this.getTemplateId(this.optionalIds['eyeglasses']) : (meta.eyeglassesId ? meta.eyeglassesId : '');
        eyeglasses = curEyeglassesId ? _.find(window.templates, function(item){return item.flow_id == parseInt(curEyeglassesId, 10);}) : '';
        eyeglasses = eyeglasses ? eyeglasses.svg : '';

        let curBackgroundId = (this.optionalIds['background'] && this.getTemplateId(this.optionalIds['background'])) ? this.getTemplateId(this.optionalIds['background']) : (meta.backgroundId ? meta.backgroundId : '');
        background = curBackgroundId ? _.find(window.templates, function(item){return item.flow_id == parseInt(curBackgroundId, 10);}) : '';
        background = background ? background.svg : '';

        return window.wrapSvg(background+meta.metadata.svg+accessory+eyeglasses+hat)
    },
    name(){
        if(this.isFlovatar()){
            return this.meta() ? this.meta().name : '';
        }
        return this.meta() ? this.meta().metadata.name : '';
    },
    // bio lives on FlovatarData; FLOW-marketplace sale data (listed Flovatar) has none -> '' instead of a TypeError
    bioValue(key){
        const m = this.meta();
        if(!m){
            return '';
        }
        const bio = this.isFlovatar() ? m.bio : ((m.metadata && m.metadata.bio) || m.bio);
        return bio && bio[key] ? bio[key] : '';
    },
    story(){
        return this.bioValue('story');
    },
    unlocked3d(){
        return this.bioValue('3d');
    },
    position(){
        return this.bioValue('position');
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
            if(type == 'rare'){
                return this.meta().metadata.rareCount;
            } else if(type == 'epic'){
                return this.meta().metadata.epicCount;
            } else if(type == 'legendary'){
                return this.meta().metadata.legendaryCount;
            }
        }
        return '';
    },
    getRarityClass(){
        if(this.getRarityCountText('legendary') > 0){
            return 'legendary';
        } else if(this.getRarityCountText('epic') > 0){
            return 'epic';
        } else if(this.getRarityCountText('rare') > 0){
            return 'rare';
        }
        return 'common';
    },
    loggedIn(){
        return (this.user && this.profile && this.profile.balance);
    },
    createSale(){
        if(this.flovatar && this.isOwned() && this.currentPrice) {
            console.log('create flovatar sale');
            window.createFlovatarSale(this.flovatar.id, this.currentPrice);
        }
    },
    changePrice(){
        if(this.sale && this.isOwned() && this.currentPriceChange) {
            console.log('change price');
            window.updateFlovatarSale(this.sale.id, this.currentPriceChange);
        }
    },
    cancelSale(){
        if(this.isSale() && this.isOwned()) {
            console.log('cancel sale');
            window.cancelFlovatarSale(this.isDapperSale() ? this.flovatar.id : this.sale.id, this.isDapperSale() ? this.flovatarSaleOffer.listing_id : this.sale.id);
        }
    },
    buySale(){
        if(this.isSale() && !this.isOwned()) {
            console.log('buy sale');
            let saleId = this.isDapperSale() ? this.flovatarSaleOffer.listing_id : this.sale.id;
            let tokenId = this.isDapperSale() ? this.flovatarSaleOffer.flow_id : this.sale.id;
            let price = this.isDapperSale() ? this.flovatarSaleOffer.price : this.sale.price;
            window.buyFlovatarSale(this.address, tokenId, price, saleId);
        }
    },
    setAccessory(){
        console.log('set accessory');
        if(this.optionalIds['accessory'] && this.isOwned() && this.isFlovatar() && this.loggedIn()){
            //console.log('set accessory execute');
            window.flovatarAddAccessory(this.flovatar.id, this.optionalIds['accessory']);
        }
    },
    setHat(){
        console.log('set hat');
        if(this.optionalIds['hat'] && this.isOwned() && this.isFlovatar() && this.loggedIn()){
            //console.log('set hat execute');
            window.flovatarAddHat(this.flovatar.id, this.optionalIds['hat']);
        }
    },
    setEyeglasses(){
        console.log('set eyeglasses');
        if(this.optionalIds['eyeglasses'] && this.isOwned() && this.isFlovatar() && this.loggedIn()){
            //console.log('set eyeglasses execute');
            window.flovatarAddEyeglasses(this.flovatar.id, this.optionalIds['eyeglasses']);
        }
    },
    setBackground(){
        console.log('set background');
        if(this.optionalIds['background'] && this.isOwned() && this.isFlovatar() && this.loggedIn()){
            //console.log('set background execute');
            window.flovatarAddBackground(this.flovatar.id, this.optionalIds['background']);
        }
    },
    setOptional(category){
        console.log('set optional');
        if(this.optionalIds[category] && this.isOwned() && this.isFlovatar() && this.loggedIn()){
            //console.log('set optional execute');
            window.flovatarAddOptional(this.flovatar.id, this.optionalIds[category], category);
        }
    },
    removeOptional(category){
        console.log('set optional');
        if(this.hasFlobit(category) && this.isOwned() && this.isFlovatar() && this.loggedIn()){
            //console.log('set optional execute');
            window.flovatarRemoveOptional(this.flovatar.id, category);
        }
    },
    setName(){
        console.log('set name');
        if(this.isOwned() && this.isFlovatar() && this.loggedIn()){
            //console.log('set name execute');
            window.flovatarSetName(this.flovatar.id, this.flovatarName);
        }
    },
    positionFound(){
        return this.flovatarLat != 0 && this.flovatarLon != 0
    },
    setPosition(){
        console.log('set position');
        if(this.isOwned() && this.isFlovatar() && this.loggedIn()){
            //console.log('set name execute');
            window.flovatarSetPosition(this.flovatar.id, this.flovatarLat, this.flovatarLon);
        }
    },
    addStory(){
        console.log('add story');
        if(this.isOwned() && this.isFlovatar() && this.loggedIn()){
            //console.log('set name execute');
            window.flovatarAddStory(this.flovatar.id, this.flovatarStory);
        }
    },
    unlock3d(){
        console.log('unlock 3d');
        if(this.isOwned() && this.isFlovatar() && this.loggedIn()){
            //console.log('set name execute');
            window.flovatarUnlock3d(this.flovatar.id);
        }
    },
    refreshAvatar(){
        if(this.flovatar) {
            window.getFlovatar(address, this.flovatar.id);
            window.getFlovatarSale(address, this.flovatar.id);
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
        let temp = this.getTemplate(id);
        return temp ? temp.flow_id : null;
    },
    setDiscordProfile(){
        /*
        client.user.setAvatar('./avatar.png')
            .then(user => console.log(`New avatar set!`))
            .catch(console.error);
            */
    },
    getFlovatarTemplateIds(){
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
    getFlovatarTemplates(){
        let templateIds = this.getFlovatarTemplateIds();
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
    getFlovatarBasicTemplates(){
        if(this.combination()) {
            let templateIds = window.signatureToTemplateArray(this.combination());
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
    getFlovatarOptionalTemplates(){
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
    getFlovatarBasicTemplate(category){
        let templates = this.getFlovatarBasicTemplates();
        return templates ? _.find(templates, function(item){ return item ? item.category == category : false; }) : null;
    },
    getFlovatarOptionalTemplate(category){
        let templates = this.getFlovatarOptionalTemplates();
        return templates ? _.find(templates, function(item){ return item ? item.category == category : false; }) : null;
    },
    templateBasicSvg(category){
        let template = this.getFlovatarBasicTemplate(category);
        return (template) ? window.wrapSvg(template.svg) : '';
    },
    templateBasicName(category){
        let template = this.getFlovatarBasicTemplate(category);
        return (template) ? template.name : category;
    },
    templateBasicRarity(category){
        let template = this.getFlovatarBasicTemplate(category);
        return (template) ? template.rarity : 'N/A';
    },
    templateUsedBy(category){
        let template = this.getFlovatarBasicTemplate(category);
        let extraS = (template && template.burned > 1) ? 's' : '';
        return (template) ? template.burned+' Flovatar'+extraS : 'N/A';
    },
    templateOptionalSvg(category){
        let template = this.getFlovatarOptionalTemplate(category);
        return (template) ? window.wrapSvg(template.svg) : '';
    },
    templateOptionalName(category){
        let template = this.getFlovatarOptionalTemplate(category);
        return (template) ? template.name : category;
    },
    templateOptionalRarity(category){
        let template = this.getFlovatarOptionalTemplate(category);
        return (template) ? template.rarity : 'N/A';
    },
    getComponentBasicClass(category){
        let template = this.getFlovatarBasicTemplate(category);
        return (template) ? template.rarity : 'common';
    },
    getComponentOptionalClass(category){
        let template = this.getFlovatarOptionalTemplate(category);
        return (template) ? template.rarity : 'common';
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
        a.setAttribute("download", `flovatar-${ (new Date().getTime()).toString(10) }.svg`);
        a.setAttribute("href", src);
        a.setAttribute("target", '_blank');
        a.dispatchEvent(evt);
        a.remove();
    },
    savePng() {
        let svg = window.wrapSvg(this.svg());
        const img = new Image(3000, 3000);
        const canvas = document.createElement("canvas");
        canvas.width = 3000;
        canvas.height = 3000;
        const ctx = canvas.getContext("2d");
        svg = svg.replace('width="100%"', 'width="3000"').replace('height="100%"', 'height="3000"');
        const base64 = window.btoa(unescape(encodeURIComponent(svg)));
        const src = "data:image/svg+xml;base64," + base64;

        img.onload = () => {
            ctx.drawImage(img, 0, 0, 3000, 3000);

            let imgURI = canvas
                .toDataURL("image/png")
                .replace("image/png", "image/octet-stream");

            const evt = new MouseEvent("click", {
                view: window,
                bubbles: false,
                cancelable: true
            });
            const a = document.createElement("a");
            a.setAttribute("download", `flovatar-${ (new Date().getTime()).toString(10) }.png`);
            a.setAttribute("href", imgURI);
            a.setAttribute("target", '_blank');
            a.dispatchEvent(evt);
            canvas.remove();
            a.remove();
            img.remove();
        }
        img.src = src;
    }
}));

window.initFlovatar = async function(dispatch, address, flovatarId){
    window.dispatch = dispatch;
    address = await window.resolveItemOwner('flovatar', flovatarId, address); // headless: owner confirmed on chain
    window.getFlovatar(address, flovatarId);
    window.getFlovatarSale(address, flovatarId);
    if(window.dustEnabled) {
        window.getFlovatarClaimables(address, flovatarId);
    }
}
