import Alpine from "alpinejs";
import party from "party-js";

Alpine.data('builderData', (templates) => ({
    templates: templates,
    components: [],
    name: '',
    showMintBtn: false,
    useOwned: false,
    lastCheckResult: null,
    activeCategory: 'body',
    useBasicOnly: true,
    showModalFlovatar: false,
    templateIds: {},
    componentIds: {},
    showAvailableResults: false,
    showFlobits: false,
    showFlovatarCreated: false,
    displayPreviewCss: 'block',
    showMinBalance: false,
    showOnlyAvailable: false,
    childModeEnabled: false,
    mode3dEnabled: false,
    subgroupFilter: 'all',
    checkoutCart: {},
    showCheckoutCompleted: false,
    init(){
        let self = this;
        this.loadHashParams();

        this.$watch('templateIds.body', () => { self.loadCombination(); });
        this.$watch('templateIds.hair', () => { self.loadCombination(); });
        this.$watch('templateIds.eyes', () => { self.loadCombination(); });
        this.$watch('templateIds.nose', () => { self.loadCombination(); });
        this.$watch('templateIds.mouth', () => { self.loadCombination(); });
        this.$watch('templateIds.facialHair', () => { self.loadCombination(); });
        this.$watch('templateIds.clothing', () => { self.loadCombination(); });
        this.$watch('templateIds.eyeglasses', () => { self.loadCombination(); });
        this.$watch('templateIds.hat', () => { self.loadCombination(); });
        this.$watch('templateIds.accessory', () => { self.loadCombination(); });
        this.$watch('templateIds.background', () => { self.loadCombination(); });


        this.$watch('mode3dEnabled', () => { self.sync3dViewer(); });

        this.loadCombination();

    },
    checkBoosterNeeded(rarity){
        return (this.rarityCount(rarity) > this.filterRarityComp(rarity).length);
    },
    getTemplateUrl(flow_id){
        return (this.mode3dEnabled && this.activeCategory != 'background') ? '/images/3d/'+flow_id+'.png' : '/api/image/template/'+flow_id;
    },
    searchComponentsToBuy(){
        let templates = [];

        if(this.getSparkCount() <= 0){
            templates.push({
                id: this.getTemplateIdByCatRarity('spark', 'common'),
                quantity: 1
            });
        }
        if(this.checkBoosterNeeded('rare')){
            templates.push({
                id: this.getTemplateIdByCatRarity('boost', 'rare'),
                quantity: this.rarityCount('rare') - this.filterRarityComp('rare').length
            });
        }
        if(this.checkBoosterNeeded('epic')){
            templates.push({
                id: this.getTemplateIdByCatRarity('boost', 'epic'),
                quantity: this.rarityCount('epic') - this.filterRarityComp('epic').length
            });
        }
        if(this.checkBoosterNeeded('legendary')){
            templates.push({
                id: this.getTemplateIdByCatRarity('boost', 'legendary'),
                quantity: this.rarityCount('legendary') - this.filterRarityComp('legendary').length
            });
        }
        if(this.templateIds.hat && !this.getOptionalFlobit('hat')){
            templates.push({
                id: this.templateIds.hat,
                quantity: 1
            });
        }
        if(this.templateIds.eyeglasses && !this.getOptionalFlobit('eyeglasses')){
            templates.push({
                id: this.templateIds.eyeglasses,
                quantity: 1
            });
        }
        if(this.templateIds.accessory && !this.getOptionalFlobit('accessory')){
            templates.push({
                id: this.templateIds.accessory,
                quantity: 1
            });
        }
        if(this.templateIds.background && !this.getOptionalFlobit('background')){
            templates.push({
                id: this.templateIds.background,
                quantity: 1
            });
        }

        let self = this;
        axios.post('/builder/search', {templates: templates}).then(function (response) {
            console.log(response.data);
            self.checkoutCart = response.data;
        })
            .catch(function (error) {
                console.log(error);
            });
    },
    checkoutTotal(){
        let total = 0.0;
        _.each(this.checkoutCart, function(item){
            total += item.price;
        });

        return total;
    },
    startBuilderCheckout(){
        console.log(this.checkoutCart);
        let addresses = _.map(this.checkoutCart, function(item){
            return item.flow_address;
        });
        let sales = _.map(this.checkoutCart, function(item){
            return ''+item.flow_id;
        });
        let prices = _.map(this.checkoutCart, function(item){
            return floatArg(item.price);
        });
        window.builderCheckout(addresses, sales, prices);
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
    getCategoryClass(category){
        let output = this.getTempRarityClass(category);
        output += (this.activeCategory == category) ? ' active' : '';
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
        if(this.isCategoryFlobit(this.activeCategory)){
            return this.isCategoryOptional(this.activeCategory) && !this.componentIds[this.activeCategory] && this.templateIds[this.activeCategory];
        }
        let catClass = this.getTempRarityClass(this.activeCategory);
        return ((catClass == 'rare' || catClass == 'epic' || catClass == 'legendary') && this.filterRarityComp(catClass).length <= 0) ? true : false;
    },
    infoBoxClass(){
        let output = this.getTempRarityClass(this.activeCategory);
        output += this.showUnavailable() ? ' border-red' : '';
        return output;
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
        let combination = this.getCombination();
        if(combination && window.location.hash != '#'+combination){
            window.history.pushState({}, null, '#'+combination);
        }
        if(this.mode3dEnabled){
            this.sync3dViewer();
        }
    },
    selectActiveCategory(category){
        this.activeCategory = category;

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
    isCategoryOptional(category){
        return (category == 'facialHair' || category == 'accessory' || category == 'hat' || category == 'eyeglasses' || category == 'background');
    },
    checkAvailabilityClick(){
        console.log('check availability');
        console.log(this.templateIds);
        window.checkAvailability(this.templateIds.body, this.templateIds.hair, this.templateIds.facialHair, this.templateIds.eyes, this.templateIds.nose, this.templateIds.mouth, this.templateIds.clothing);
        this.searchComponentsToBuy();
    },
    getOptionalFlobit(category) {
        return (this.templateIds[category] && this.componentIds[category]) ? this.componentIds[category] : null;
    },
    createFlovatarClick(){
        if(this.balance() < 0.05 && this.currentWallet != 'dapper'){
            this.showMinBalance = true;
        } else {
            let spark = this.getSpark();
            let rareIds = this.getBoostId('rare');
            let epicIds = this.getBoostId('epic');
            let legendaryIds = this.getBoostId('legendary');
            if(spark && this.hasAllComponents()){
                let accessoryTemp = this.getOptionalFlobit('accessory');
                let hatTemp = this.getOptionalFlobit('hat');
                let eyeglassesTemp = this.getOptionalFlobit('eyeglasses');
                let backgroundTemp = this.getOptionalFlobit('background');
                window.createFlovatar(spark.id, this.templateIds.body, this.templateIds.hair, this.templateIds.facialHair, this.templateIds.eyes, this.templateIds.nose, this.templateIds.mouth, this.templateIds.clothing, accessoryTemp, hatTemp, eyeglassesTemp, backgroundTemp, rareIds, epicIds, legendaryIds);
            } else {
                console.log('unable to find the spark');
                console.log(spark);
            }
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
    filterTemp(category){
        let templates = [];
        let self = this;

        templates = _.filter(this.templates, function(temp){
            return temp.category == category && (!self.childModeEnabled ||  !window.childUnsafeIds.includes(temp.flow_id));
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
    randomComp(category){
        let filterList = this.filterComp(category);

        if(!filterList){
            return null;
        }
        let sample = _.sample(filterList);
        return sample ? sample.id : null;
    },
    randomTemp(category){
        let filterList = this.filterTemp(category);

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
        if(this.templateIds['background']) {
            let temp = _.find(this.templates, {'flow_id': this.templateIds['background']});
            svg += temp ? temp.svg : '';
        }

        _.each(this.categories, (category) => {
            if(this.templateIds[category] && category != 'background') {
                let temp = _.find(this.templates, {'flow_id': parseInt(this.templateIds[category],10)});
                svg += temp ? temp.svg : '';
            }
        });
        return window.wrapSvg(svg);
    },
    getTempSvg(category){
        if(this.templateIds[category]) {
            let temp = _.find(this.templates, { 'flow_id': parseInt(this.templateIds[category],10)});
            return temp ? window.wrapSvg(temp.svg) : '';
        }
        return window.wrapSvg('');
    },
    getTempUrl(category){
        return (this.templateIds[category]) ? '/api/image/template/'+this.templateIds[category] : '';
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
            let maxMint = (temp && this.isCategoryOptional(category) && category !='facialHair') ? ' (max. '+temp.max_mintable+')' : '';
            return temp ? temp.rarity+'<small>'+maxMint+'</small>' : 'N/A';
        }
        return 'N/A';
    },
    getTempBurned(category){
        if(!this.isCategoryFlobit(category) && this.templateIds[category]) {
            let temp = _.find(this.templates, { 'flow_id': parseInt(this.templateIds[category],10)});
            let extraS = (temp && temp.burned > 1) ? 's' : '';
            return temp ? temp.burned+' <small>Flovatar'+extraS+'</small>' : 'N/A';
        }
        return 'N/A';
    },
    handleCheckResults(data){
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
    handleFlovatarCreated(){
        //alert('Woohoo! You created a brand new unique Flovatar. Check it out in your collection');
        this.showConfetti();
        this.showFlovatarCreated = true;
    },
    sync3dViewer(){
        if(!this.mode3dEnabled){
            return false;
        }

        let self = this;
        let tempArray = [];
        _.each(this.categories, (category) => {
            if(this.templateIds[category] && category != 'background') {
                tempArray.push(this.templateIds[category]);
            }
        });

        document.getElementById("visualiser").contentWindow.postMessage(
            {
                message: "setParts",
                parts: tempArray,
                background: self.templateIds.background,
                style: 'shiny',
            },
            "*" // Actual URL where this needs to be sent
        );
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

            if(this.isCategoryOptional(category) && this.useBasicOnly){
                this.templateIds[category] = null;
                this.componentIds[category] = null;
            } else {
                let comp = _.find(this.components, {category: category});
                if(comp && this.isCategoryOptional(category)){
                    this.templateIds[category] = comp.templateId;
                    this.componentIds[category] = comp.id;
                } else {
                    let rand = Math.random();
                    this.templateIds[category] = (this.isCategoryOptional(category) && rand < 0.5) ? null : this.randomTemp(category);
                    let comp2 = _.find(this.components, {templateId: this.templateIds[category]});
                    this.componentIds[category] = ((this.isCategoryOptional(category) && rand < 0.5) || !comp2) ? null : comp2.id;
                }
            }
        });
    },
    randomizeCombination(){
        //console.log('randomize combination');
        this.showMintBtn = false;
        _.each(this.categories, (category) => {
            if(this.isCategoryOptional(category) && this.useBasicOnly){
                this.templateIds[category] = null;
                this.componentIds[category] = null;
            } else {
                let rand = Math.random();
                this.templateIds[category] = (this.isCategoryOptional(category) && rand < 0.5) ? null : this.randomTemp(category);

                let comp = _.find(this.components, {templateId: this.templateIds[category]});
                this.componentIds[category] = ((this.isCategoryOptional(category) && rand < 0.5) || !comp) ? null : comp.id;
            }
        });
    },
    rarityCount(rarity){
        let rareCount = 0;
        let self = this;

        _.each(this.basicCategories, (category) => {
            let template = _.find(this.templates, function(item){ return item.flow_id == parseInt(self.templateIds[category],10);});
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
        let check = true;
        let self = this;

        if(!this.getSpark()){
            check = false;
        }

        _.each(this.rarityBoost, function(rarity){
            if(self.filterRarityComp(rarity).length < self.rarityCount(rarity)){
                check = false;
            }
        });

        /*
        _.each(this.optionalCategories, (category) => {
            if(this.isCategoryOptional(category) && self.templateIds[category] && !self.componentIds[category]) {
                check = false;
            }
        });
        */


        return check;
    },
    cssClassCategory(category){
        let css = '';
        if(category == this.activeCategory){
            css += ' ring-2 ring-offset-2 ring-indigo-500';
        }
        if(this.filterComp(category).length > 0 && (this.isCategoryOptional(category) || this.componentIds[category])){
            if(this.isCategoryOptional(category)){
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
        if(templateId == this.templateIds[this.activeCategory]){
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
        this.componentIds[this.activeCategory] = comp ? comp.id : null;
        this.templateIds[this.activeCategory] = templateId;
        this.showMintBtn = false;
    },
    getComponentClass(template){
        let cssClass = (template.flow_id === this.templateIds[this.activeCategory]) ? 'active ' : '';
        let bgClass = template.rarity+' ';
        let opacityClass = '';
        if(this.isCategoryFlobit(this.activeCategory) && this.profile){
            let comp = _.find(this.components, (item) => { return parseInt(item.templateId,10) == template.flow_id;});
            if(!comp){
                opacityClass = 'unavailable ';
            }
        }
        return cssClass + bgClass + opacityClass;
    },
    getComponentParentClass(template){
        if(this.profile && this.showOnlyAvailable){
            if(this.isCategoryFlobit(this.activeCategory)){
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
        let svg = '';
        let self = this;
        _.each(this.categories, function(category){
            if(self.templateIds[category]) {
                let temp = _.find(self.templates, { 'flow_id': parseInt(self.templateIds[category],10)});
                svg += temp ? temp.svg : '';
            }
        });
        if(navigator.clipboard) {
            navigator.clipboard.writeText(window.wrapSvg(svg));
        }
    },
    saveSvg() {
        let svg = '';
        let self = this;
        _.each(this.categories, function(category){
            if(self.templateIds[category]) {
                let temp = _.find(self.templates, { 'flow_id': parseInt(self.templateIds[category],10)});
                svg += temp ? temp.svg : '';
            }
        });
        svg = window.wrapSvg(svg);
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
        let svg = '';
        let self = this;
        if(self.templateIds['background']) {
            let temp = _.find(self.templates, { 'flow_id': parseInt(self.templateIds['background'],10)});
            svg += temp ? temp.svg : '';
        }
        _.each(this.categories, function(category){
            if(self.templateIds[category] && category != 'background') {
                let temp = _.find(self.templates, { 'flow_id': parseInt(self.templateIds[category],10)});
                svg += temp ? temp.svg : '';
            }
        });
        svg += window.watermarkSvg;
        svg = window.wrapSvg(svg);
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

window.initBuilder = function(dispatch){
    window.dispatch = dispatch;
}
