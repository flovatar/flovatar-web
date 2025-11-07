import Alpine from "alpinejs";
import party from "party-js";

Alpine.data('asteroidBuilderData', (templates) => ({
    templates: templates,
    components: [],
    name: '',
    showMintBtn: false,
    useOwned: false,
    lastCheckResult: null,
    activeLayer: 2,
    activeRarity: 'common',
    useBasicOnly: true,
    templateIds: {},
    componentIds: {},
    showAvailableResults: false,
    showCollectibleCreated: false,
    displayPreviewCss: 'block',
    showMinBalance: false,
    showOnlyAvailable: false,
    childModeEnabled: false,
    subgroupFilter: 'all',
    showCheckoutCompleted: false,
    showZoomModal: false,
    flovatars: [],
    collectibles: [],
    flobots: [],
    raritySlotCount:{
        common: 5,
        epic: 30,
        legendary: 60,
    },
    slots: {},
    layers: [
        {id: 2, name: 'base', isAccessory: false},
        {id: 3, name: 'structures', isAccessory: false},
        {id: 4, name: 'fub', isAccessory: false},
        {id: 5, name: 'accessory', isAccessory: false},
        {id: 6, name: 'flovatar', isAccessory: true},
        {id: 7, name: 'psyche likee', isAccessory: true},
        {id: 8, name: 'flobot', isAccessory: true}
    ],
    init(){
        let self = this;
        //this.loadHashParams();

        if(this.layers.length > 0){
            self.activeLayer = this.layers[0].id;
        }

        this.$watch('templateIds', () => { self.loadCombination(); });
        //this.$watch('templateIds[1]', () => { self.loadCombination(); });

        //this.loadCombination();
        this.randomizeCombination();
    },
    seriesLayers(){
        return this.layers;
    },
    traitsLayers(){
        return _.filter(this.seriesLayers(), {isAccessory: false});
    },
    traitsTemplatesIds(){
        let self = this;
        let traitsTemplates = {};
        _.each(this.traitsLayers(), layer => {
            if(self.templateIds[layer.id]){
                traitsTemplates[layer.id] = self.templateIds[layer.id];
            }
        });
        return traitsTemplates;
    },
    layersTemplatesIds(){
        let self = this;
        let traitsTemplates = {};
        _.each(this.seriesLayers(), layer => {
            if(self.templateIds[layer.id]){
                traitsTemplates[layer.id] = self.templateIds[layer.id];
            } else {
                traitsTemplates[layer.id] = null;
            }
        });
        return traitsTemplates;
    },
    flobitLayers(){
        return _.filter(this.seriesLayers(), {isAccessory: true});
    },
    getActiveLayer(){
        let self = this;
        return this.activeLayer ? _.find(this.seriesLayers(), {id: self.activeLayer}) : null;
    },
    getTempCurrentPriceById(templateId){

    },
    getCurrentRarity(){
        let rarity = 'common';
        if(this.rarityCount('rare') > 0){
            rarity = 'rare';
        }
        if(this.rarityCount('epic') > 0){
            rarity = 'epic';
        }
        if(this.rarityCount('legendary') > 0){
            rarity = 'legendary';
        }
        return rarity;
    },
    getCurrentRarityClass(){
        return this.getCurrentRarity();
    },
    getTotalPrice(){
        let total = 0.0;
        _.each(this.traitsTemplatesIds(), item => {
            let temp = _.find(this.templates, { 'flow_id': item});
            if(temp){
                total += temp.current_price + 0.01;
            }
        });

        total += 0.01;

        return total;
    },
    getTotalPriceFormatted(){
        return window.formatPriceSmall(this.getTotalPrice());
    },
    hasEnoughDust(){
        //console.log('check dust balance');
        //console.log(this.dustBalance());
        //console.log(this.getTotalPrice());
        return this.dustBalance() >= this.getTotalPrice();
    },
    checkBoosterNeeded(rarity){
        return (this.rarityCount(rarity) > this.filterRarityComp(rarity).length);
    },
    getTemplateUrl(flow_id){
        return '/api/image/asteroid/template/'+flow_id;
    },

    processBuilderCheckoutCompleted(){
        window.getComponents(window.user.addr);
        this.showCheckoutCompleted = true;
    },
    getTemplateIdByCatRarity(category, rarity){
        let template = _.find(this.templates, function(temp){ return temp.category == category && temp.rarity == rarity; });
        return template ? template.flow_id : null;
    },
    builderScore(){
        return window.getRarityScoreFromCounts(this.rarityCount('legendary'), this.rarityCount('epic'), this.rarityCount('rare'));
    },
    builderScoreClass(){
        if(this.rarityCount('legendary') > 0){
            return 'legendary';
        } else if(this.rarityCount('epic') > 0){
            return 'epic';
        } else if(this.rarityCount('rare') > 0){
            return 'rare';
        }
        return 'common';
    },
    getLayerClass(layer){
        let output = this.getTempRarityClass(layer.id);
        output += (this.activeLayer == layer.id) ? ' active' : '';
        return output;
    },
    getTempRarityClass(category){
        if(this.templateIds[category]) {
            let temp = _.find(this.templates, { 'flow_id': parseInt(this.templateIds[category],10)});
            return temp ? temp.rarity : '';
        }
        return '';
    },
    showUnavailable(){
        if(this.isCategoryFlobit(this.activeLayer)){
            return this.isLayerOptional(this.activeLayer) && !this.componentIds[this.activeLayer] && this.templateIds[this.activeLayer];
        }
        let catClass = this.getTempRarityClass(this.activeLayer);
        return ((catClass == 'rare' || catClass == 'epic' || catClass == 'legendary') && this.filterRarityComp(catClass).length <= 0) ? true : false;
    },
    infoBoxClass(){
        let output = this.getTempRarityClass(this.activeLayer);
        output += this.showUnavailable() ? ' border-red' : '';
        return output;
    },
    collectionsFlovatarLoaded(eventData){
          this.flovatars = _.concat(eventData.flovatars, eventData.flovatarSales);
          console.log(eventData);
        console.log(this.flovatars);
    },
    loadCollectionsCollectible(eventData){
        this.collectibles = eventData.collectibles;
        console.log(eventData);
    },
    loadCollectionsFlobot(eventData){
        this.flobots = eventData.flobots;
        console.log(eventData);
    },
    loadHashParams(){
        let self = this;
        if(window.location.hash && window.location.hash != '#') {
            let hash = window.location.hash;
            hash = hash.replace('#', '');
            let params = _.split(hash, '&');
            _.each(params, function(param) {
                let paramArr = _.split(param, '=');
                if (paramArr.length == 2) {
                    if(paramArr[0] == 'combination'){
                        self.templateIds = window.signatureToTemplateArray(paramArr[1]);
                    }
                    if(paramArr[0] == 'eyeglasses'){
                        self.templateIds.eyeglasses = parseInt(paramArr[1],10);
                    }
                    if(paramArr[0] == 'hat'){
                        self.templateIds.hat = parseInt(paramArr[1],10);
                    }
                    if(paramArr[0] == 'accessory'){
                        self.templateIds.accessory = parseInt(paramArr[1],10);
                    }
                    if(paramArr[0] == 'background'){
                        self.templateIds.background = parseInt(paramArr[1],10);
                    }
                }
            });
        } else {
            this.initCombination();
        }
    },
    getCombination(){
        if(Object.keys(this.templateIds).length == 0){
            return null;
        } else {
            let facialHairId = this.templateIds['facialHair'] ? this.templateIds['facialHair'] : 'x';
            let eyeglassesParam = (this.templateIds['eyeglasses']) ? '&eyeglasses='+this.templateIds['eyeglasses'] : '';
            let hatParam = (this.templateIds['hat']) ? '&hat='+this.templateIds['hat'] : '';
            let accessoryParam = (this.templateIds['accessory']) ? '&accessory='+this.templateIds['accessory'] : '';
            let backgroundParam = (this.templateIds['background']) ? '&background='+this.templateIds['background'] : '';
            return 'combination=B'+this.templateIds['body']+'H'+this.templateIds['hair']+'F'+facialHairId+'E'+this.templateIds['eyes']+'N'+this.templateIds['nose']+'M'+this.templateIds['mouth']+'C'+this.templateIds['clothing']+eyeglassesParam+hatParam+accessoryParam+backgroundParam;
        }
    },
    loadCombination(){
        return true;
        /*
        let combination = this.getCombination();
        if(combination && window.location.hash != '#'+combination){
            window.history.pushState({}, null, '#'+combination);
        }
        if(this.mode3dEnabled){
            this.sync3dViewer();
        }
         */
    },
    selectActiveLayer(layer){
        this.activeLayer = layer.id;

        /*
        this.displayPreviewCss = 'flex';
        let self = this;
        setTimeout(function(){
            self.displayPreviewCss = 'block';
        },10)
        */

    },
    showCategory(category){
        return this.showFlobits || !this.isCategoryFlobit(category);
    },
    isCategoryBoost(category){
        return (category == 'boost');
    },
    isCategoryFlobit(category){
        return  (category == 'accessory' || category == 'hat' || category == 'eyeglasses' || category == 'background');
    },
    isLayerOptional(category){
        let layer = this.getActiveLayer();
        return layer ? layer.isAccessory : false;
    },
    getOptionalFlobit(category) {
        return (this.templateIds[category] && this.componentIds[category]) ? this.componentIds[category] : null;
    },
    createCollectibleClick(){
        if(this.traitsTemplatesIds()){
            window.createAsteroid(this.layersTemplatesIds());
        }
    },
    getBoostId(rarity){
        let rareIds = [];
        let rareComp = this.filterRarityComp(rarity);
        let rarityCount = this.rarityCount(rarity);
        if(rareComp.length >= rarityCount){
            for(let i=0; i < rarityCount; i++){
                rareIds[i] = ''+rareComp[i].id;
            }
        }
        return rareIds;
    },
    getSpark(){
        let sparkComp =  _.filter(this.components, function(comp){ return comp.category == 'spark'; });
        return (sparkComp && sparkComp.length > 0) ? sparkComp[0] : null;
    },
    getSparkCount(){
        let sparkComp =  _.filter(this.components, function(comp){ return comp.category == 'spark'; });
        return (sparkComp) ? sparkComp.length : 0;
    },
    getTemplateId(id){
        let temp = this.getTemplate(id);
        return temp ? temp.flow_id : null;
    },
    getTemplate(id){
        let comp = _.find(this.components, function(item) { return item.id == id; });
        return (comp) ? _.find(this.templates, { 'flow_id': parseInt(comp.templateId, 10)}) : null;
    },
    filterComp(category){
        let self = this;
        return _.filter(this.components, function(comp){ return comp.category == category && self.templateIds[category] && comp.templateId == self.templateIds[category]; });
    },
    filterRarityComp(rarity){
        let tempRarity = _.find(this.templates, function(temp){ return temp.category == 'boost' && temp.rarity == rarity; });
        return (tempRarity) ? _.filter(this.components, function(comp){ return comp.category == 'boost' && comp.templateId == tempRarity.flow_id; }) : [];
    },
    filterTemp(layer){
        let templates = [];
        let self = this;

        templates = _.filter(this.templates, function(temp){
            return temp.layer == layer;
        });


        return templates.sort(function (l,r){
            let lRarity = self.getRarityByName(l.rarity);
            let rRarity = self.getRarityByName(r.rarity);
            if(l.rarity == r.rarity){
                if(l.name > r.name){
                    return 1;
                } else if(l.name < r.name){
                    return -1;
                } else {
                    return 0;
                }
            } else {
                if(lRarity.order > rRarity.order){
                    return 1;
                } else {
                    return -1;
                }
            }
        });
    },
    filterTempRarity(layer){
        let templates = [];
        let self = this;

        templates = _.filter(this.templates, function(temp){
            return temp.layer == layer && temp.rarity == self.activeRarity;
        });

        return templates;
    },
    randomComp(category){
        let filterList = this.filterComp(category);

        if(!filterList){
            return null;
        }
        let sample = _.sample(filterList);
        return sample ? sample.id : null;
    },
    randomTemp(category){
        let filterList = this.filterTempRarity(category);
        if(!filterList){
            return null;
        }
        let sample = _.sample(filterList);
        return sample ? sample.flow_id : null;
    },
    getCompSvg(category){
        if(this.componentIds[category]) {
            let curCat = this.componentIds[category];
            let comp = _.find(this.components, function(item) { return item.id == curCat; });
            if (comp) {
                let temp = _.find(this.templates, { 'flow_id': parseInt(comp.templateId, 10)});
                return temp ? window.wrapSvg(temp.svg) : '';
            }
        }
        return '';
    },
    getFullSvg(){
        let svg = ''
        //console.log(this.seriesLayers());
        _.each(this.seriesLayers(), (layer) => {
            //console.log(layer);
            if(this.templateIds[layer.id]) {
                let temp = _.find(this.templates, {'flow_id': parseInt(this.templateIds[layer.id],10)});
                svg += temp ? temp.svg : '';
            }
        });


        let slotCount = this.raritySlotCount[this.activeRarity];
        for(let i= 1; i < slotCount; i++){
            if(this.slots[i]){
                svg = svg.replace('<g id="slot-'+i+'"></g>', '<g id="slot-'+i+'">'+this.slots[i]+'</g>');
            }
        }
        let svgOpen = window.svgOpenAsteroid;
        let svgClose = window.svgClose;

        return svgOpen + svg + svgClose;
    },
    getLayerName(layerId){
        //l(this.seriesLayers());
        let layer = _.find(this.seriesLayers(), item => {
            return item.id == layerId;
        });
        return layer ? layer.name : 'Layer '+layerId;
    },
    getTempSvg(category){
        if(this.templateIds[category]) {
            let temp = _.find(this.templates, { 'flow_id': parseInt(this.templateIds[category],10)});
            return temp ? window.wrapSvg(temp.svg) : '';
        }
        return window.wrapSvg('');
    },
    getTempId(category){
        let temp = _.find(this.templates, { 'flow_id': parseInt(this.templateIds[category],10)});
        return (temp) ? temp.flow_id : null;
    },
    getTempUrl(category){
        let temp = _.find(this.templates, { 'flow_id': parseInt(this.templateIds[category],10)});
        return (temp) ? '/api/image/asteroid/template/'+temp.flow_id : '';
    },
    getTempName(category){
        if(this.templateIds[category]) {
            let temp = _.find(this.templates, { 'flow_id': parseInt(this.templateIds[category],10)});
            return temp ? temp.name : 'N/A';
        }
        return 'N/A';
    },
    getTempRarity(category){
        if(this.templateIds[category]) {
            let temp = _.find(this.templates, { 'flow_id': parseInt(this.templateIds[category], 10)});
            let maxMint = (temp && this.isLayerOptional(category)) ? ' (max. '+temp.max_mintable+')' : '';
            return temp ? temp.rarity+'<small>'+maxMint+'</small>' : 'N/A';
        }
        return 'N/A';
    },
    getTempBurned(category){
        if(!this.isCategoryFlobit(category) && this.templateIds[category]) {
            let temp = _.find(this.templates, { 'flow_id': parseInt(this.templateIds[category],10)});
            return temp ? temp.minted : 0;
        }
        return 'N/A';
    },
    getTempBasePrice(category){
        if(!this.isCategoryFlobit(category) && this.templateIds[category]) {
            let temp = _.find(this.templates, { 'flow_id': parseInt(this.templateIds[category],10)});
            return temp ? temp.base_price : 0;
        }
        return 'N/A';
    },
    getTempCategoryCurrentPrice(category){
        if(!this.isCategoryFlobit(category) && this.templateIds[category]) {
            let temp = _.find(this.templates, { 'flow_id': parseInt(this.templateIds[category],10)});
            return temp ? temp.current_price : 0;
        }
        return 'N/A';
    },
    getTempCurrentPrice(templateId){
        let temp = _.find(this.templates, { 'flow_id': parseInt(templateId,10)});
        return temp ? temp.current_price : 0;
    },
    getTempBurnedById(templateId){
        let temp = _.find(this.templates, { 'flow_id': parseInt(templateId,10)});
        return temp ? temp.minted : 0;
    },
    getTempMaxMintable(category){
        if(!this.isCategoryFlobit(category) && this.templateIds[category]) {
            let temp = _.find(this.templates, { 'flow_id': parseInt(this.templateIds[category],10)});
            return temp ? temp.max_mintable : 0;
        }
        return 'N/A';
    },
    handleCheckResults(data){
        l('check result');
        l(data);
        this.lastCheckResult = data;
        this.showMintBtn = data.result;
        this.showAvailableResults = true;
    },
    shotConfetti(){
        party.confetti(document.getElementById('flovatar-builder-box'), {
            count: party.variation.range(70, 100),
            size: party.variation.range(0.6, 1.4),
            spread: party.variation.range(10, 60),
        });
    },
    showConfetti(){
        let self = this;
        self.shotConfetti();
        setTimeout(function(){ self.shotConfetti(); }, 500);
        setTimeout(function(){ self.shotConfetti(); }, 1000);
        setTimeout(function(){ self.shotConfetti(); }, 2000);
        setTimeout(function(){ self.shotConfetti(); }, 3000);
    },
    handleDustCollectibleCreated(){
        //alert('Woohoo! You created a brand new unique Flovatar. Check it out in your collection');
        this.showConfetti();
        this.showCollectibleCreated = true;
    },
    loadComponents(components){
        this.components = components;

        if(this.templateIds.hat){
            let comp = _.find(this.components, {templateId: this.templateIds.hat});
            this.componentIds.hat = comp ? comp.id : null;
        }
        if(this.templateIds.eyeglasses){
            let comp = _.find(this.components, {templateId: this.templateIds.eyeglasses});
            this.componentIds.eyeglasses = comp ? comp.id : null;
        }
        if(this.templateIds.accessory){
            let comp = _.find(this.components, {templateId: this.templateIds.accessory});
            this.componentIds.accessory = comp ? comp.id : null;
        }
        if(this.templateIds.background){
            let comp = _.find(this.components, {templateId: this.templateIds.background});
            this.componentIds.background = comp ? comp.id : null;
        }
    },
    initCombination(){
        _.each(this.categories, (category) => {

            if(this.isLayerOptional(category) && this.useBasicOnly){
                this.templateIds[category] = null;
                this.componentIds[category] = null;
            } else {
                let comp = _.find(this.components, {category: category});
                if(comp && this.isLayerOptional(category)){
                    this.templateIds[category] = comp.templateId;
                    this.componentIds[category] = comp.id;
                } else {
                    let rand = Math.random();
                    this.templateIds[category] = (this.isLayerOptional(category) && rand < 0.5) ? null : this.randomTemp(category);
                    let comp2 = _.find(this.components, {templateId: this.templateIds[category]});
                    this.componentIds[category] = ((this.isLayerOptional(category) && rand < 0.5) || !comp2) ? null : comp2.id;
                }
            }
        });
    },
    randomizeCombination(){
        //console.log('randomize combination');
        this.showMintBtn = false;
        _.each(this.seriesLayers(), (category) => {
            if(this.isLayerOptional(category.id) && this.useBasicOnly){
                this.templateIds[category.id] = null;
                this.componentIds[category.id] = null;
            } else {
                let rand = Math.random();
                this.templateIds[category.id] = (this.isLayerOptional(category.id) && rand < 0.5) ? null : this.randomTemp(category.id);

                let comp = _.find(this.components, {templateId: this.templateIds[category]});
                this.componentIds[category.id] = ((this.isLayerOptional(category.id) && rand < 0.5) || !comp) ? null : comp.id;
            }
        });
    },
    rarityCount(rarity){
        let rareCount = 0;
        let self = this;

        _.each(this.traitsLayers(), (category) => {
            let template = _.find(this.templates, function(item){ return item.flow_id == parseInt(self.templateIds[category.id],10);});
            if(template && template.rarity == rarity){
                rareCount++;
            }
        });

        return rareCount;
    },
    rarityFullCount(rarity){
        return this.rarityCount(rarity)+'/'+this.filterRarityComp(rarity).length;
    },
    rarityCountColor(rarity){
        return (this.rarityCount(rarity) <= this.filterRarityComp(rarity).length) ? '' : 'empty';
    },
    sparkCountColor(){
        return (this.getSparkCount() > 0) ? 'text-green-500' : 'text-red-400';
    },
    hasAllComponents(){
        return true;
    },
    cssClassCategory(category){
        let css = '';
        if(category == this.activeLayer){
            css += ' ring-2 ring-offset-2 ring-indigo-500';
        }
        if(this.filterComp(category).length > 0 && (this.isLayerOptional(category) || this.componentIds[category])){
            if(this.isLayerOptional(category)){
                if(this.componentIds[category]) {
                    // All good to do
                    css += ' bg-indigo-400 border-transparent text-white hover:bg-indigo-500';
                }
            } else {

                css += ' bg-indigo-600 border-transparent text-white hover:bg-indigo-700';
            }
        } else {
            css += ' bg-white border-gray-200 text-gray-900 hover:bg-gray-50';
        }
        return css;
    },
    cssClassTemplate(templateId){
        let css = '';
        if(templateId == this.templateIds[this.activeLayer]){
            css += ' ring-2 ring-offset-2 ring-indigo-500';
        }
        let comp = _.find(this.components, {templateId: templateId});
        if(comp){
            css += ' bg-indigo-600 border-transparent text-white hover:bg-indigo-700';
        } else {
            css += ' bg-white border-gray-200 text-gray-900 hover:bg-gray-50';
        }
        return css;
    },
    selectComponent(templateId){
        let comp = _.find(this.components, (item) => { return parseInt(item.templateId,10) == parseInt(templateId,10);});
        let template = _.find(this.templates, (item) => { return parseInt(item.flow_id,10) == parseInt(templateId,10);});
        this.componentIds[this.activeLayer] = comp ? comp.id : null;
        this.templateIds[this.activeLayer] = templateId;
        this.showMintBtn = false;
        this.activeRarity = template.rarity;
        this.checkLayersRarity();
    },
    setSlot(id, nftType){
        l('set Slot');
        let slotCount = this.raritySlotCount[this.activeRarity];
        if(slotCount){
            let randomSlot = _.random(1,slotCount);
            let imgUrl = (nftType=='flovatar') ? 'https://flovatar.com/api/image/nobg/'+id : 'https://flovatar.com/api/image/'+nftType+'/'+id;
            l(randomSlot);
            this.slots[randomSlot] = '<image x="0" y="0" width="300" height="300" href="'+imgUrl+'" />';
            l(this.slots[randomSlot]);
        }
    },
    checkLayersRarity(){
        let self = this;
        _.each(this.traitsLayers(), function(layer){
            let rarity = self.getTempRarityClass(layer.id);
            if(rarity != self.activeRarity){
                let randomTemplate = self.randomTemp(layer.id);
                self.templateIds[layer.id] = randomTemplate;
                l('set random template '+randomTemplate);
            }
        });
    },
    getComponentClass(template){
        let cssClass = (template.flow_id === this.templateIds[this.activeLayer]) ? 'active ' : '';
        let bgClass = template.rarity+' ';
        let opacityClass = '';
        if(this.isCategoryFlobit(this.activeLayer) && this.profile){
            let comp = _.find(this.components, (item) => { return parseInt(item.templateId,10) == template.flow_id;});
            if(!comp){
                opacityClass = 'unavailable ';
            }
        }
        return cssClass + bgClass + opacityClass;
    },
    getComponentParentClass(template){
        if(this.profile && this.showOnlyAvailable){
            if(this.isCategoryFlobit(this.activeLayer)){
                let comp = _.find(this.components, (item) => { return parseInt(item.templateId,10) == template.flow_id;});
                if(!comp){
                    return 'hide-unavailable';
                }
            } else {
                if(template.rarity !== 'common' && this.filterRarityComp(template.rarity).length <= 0){
                    return 'hide-unavailable';
                }
            }
        }
        return '';
    },
    copySvg(){
        let svg = this.getFullSvg();
        if(navigator.clipboard) {
            navigator.clipboard.writeText(svg);
        }
    },
    saveSvg() {
        let svg = this.getFullSvg();
        let self = this;
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
        let svg = this.getFullSvg();
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

window.initAsteroidBuilder = function(dispatch){
    window.dispatch = dispatch;
}
