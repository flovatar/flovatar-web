import Alpine from "alpinejs";
import party from "party-js";

Alpine.data('flobotBuilderData', (templates) => ({
    templates: templates,
    components: [],
    name: '',
    showMintBtn: false,
    useOwned: false,
    lastCheckResult: null,
    activeCategory: 'body',
    useBasicOnly: true,
    showModalFlobot: false,
    templateIds: {},
    componentIds: {},
    showAvailableResults: false,
    showFlobits: false,
    showFlobotCreated: false,
    displayPreviewCss: 'block',
    showMinBalance: false,
    showOnlyAvailable: false,
    childModeEnabled: false,
    mode3dEnabled: false,
    subgroupFilter: 'all',
    checkoutCart: {},
    showCheckoutCompleted: false,
    categories: ['legs', 'body', 'arms', 'head', 'face'],
    basicCategories: ['legs', 'body', 'arms', 'head', 'face'],
    optionalCategories: [],
    init(){
        let self = this;
        this.loadHashParams();

        this.$watch('templateIds.body', () => { self.loadCombination(); });
        this.$watch('templateIds.face', () => { self.loadCombination(); });
        this.$watch('templateIds.legs', () => { self.loadCombination(); });
        this.$watch('templateIds.arms', () => { self.loadCombination(); });
        this.$watch('templateIds.head', () => { self.loadCombination(); });


        this.$watch('mode3dEnabled', () => { self.sync3dViewer(); });

        this.loadCombination();

    },
    checkBoosterNeeded(rarity){
        return (this.rarityCount(rarity) > this.filterRarityComp(rarity).length);
    },
    getTemplateUrl(flow_id){
        return (this.mode3dEnabled && this.activeCategory != 'background') ? '/images/3d/'+flow_id+'.png' : '/api/image/flobot/template/'+flow_id;
    },
    searchComponentsToBuy(){
        let templates = [];

        let flobotkit = this.getFlobotKit();

        if(!flobotkit){
            let rarity = this.getCurrentRarity();

            if(rarity == 'common'){
                templates.push({
                    id: this.getTemplateIdByCatRarity('flobotkit', 'common'),
                    quantity: 1
                });
            } else if(rarity == 'epic'){
                templates.push({
                    id: this.getTemplateIdByCatRarity('flobotkit', 'epic'),
                    quantity: 1
                });
            } else if(rarity == 'legendary'){
                templates.push({
                    id: this.getTemplateIdByCatRarity('flobotkit', 'legendary'),
                    quantity: 1
                });
            }

            let self = this;
            axios.post('/flobot/builder/search', {templates: templates}).then(function (response) {
                console.log(response.data);
                self.checkoutCart = response.data;
            })
            .catch(function (error) {
                console.log(error);
            });
        }

    },
    checkoutTotal(){
        let total = 0.0;
        _.each(this.checkoutCart, function(item){
            total += item.price;
        });

        return total;
    },
    startBuilderCheckout(){
        console.log('checkout');
        //console.log(this.checkoutCart);
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
                        self.templateIds = window.signatureToFlobotTemplateArray(paramArr[1]);
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
            return 'combination=B'+this.templateIds['body']+'H'+this.templateIds['head']+'A'+this.templateIds['arms']+'L'+this.templateIds['legs']+'F'+this.templateIds['face'];
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
        //console.log(this.templateIds);
        window.checkFlobotAvailability(this.templateIds.body, this.templateIds.head, this.templateIds.arms, this.templateIds.legs, this.templateIds.face);
        //this.searchComponentsToBuy();
    },
    getOptionalFlobit(category) {
        return (this.templateIds[category] && this.componentIds[category]) ? this.componentIds[category] : null;
    },
    createFlobotClick(){
        if(this.balance() < 0.05 && this.currentWallet != 'dapper' && window.extSession && window.extSession.active && !this.storageTopupTried){
            // Passkey account: Flovatar sends the storage FLOW, then we continue.
            this.storageTopupTried = true;
            window.dispatch('loading-start');
            window.extEnsureStorage().then((r) => {
                window.dispatch('loading-completed');
                if (r && this.profile) { this.profile.balance = String(r.balance); }
                this.createFlobotClick();
            }).catch((e) => {
                window.dispatch('loading-completed');
                this.storageTopupTried = false;
                const msg = (e && e.response && e.response.data && e.response.data.error) || 'Could not add storage FLOW. Please try again.';
                window.dispatch('transaction-error', {message: msg});
            });
            return;
        }
        if(this.balance() < 0.05 && this.currentWallet != 'dapper'){
            this.showMinBalance = true;
        } else {
            let flobotkit = this.getFlobotKit();
            if(flobotkit && this.hasAllComponents()){
                let backgroundTemp = this.getOptionalFlobit('background');
                window.createFlobot(flobotkit, this.templateIds.body, this.templateIds.head, this.templateIds.arms, this.templateIds.legs, this.templateIds.face, backgroundTemp);
            } else {
                console.log('unable to find the spark');
                console.log(spark);
            }
        }
    },
    getFlobotKitCount(){
        let count = 0;
        let flobotkit = this.getFlobotKit();
        if(flobotkit){
            count = flobotkit.length;
            let rarity = count > 1 ? this.getLowerRarity() : this.getCurrentRarity();
            return count + ' ' + rarity;
        }
        return '0';
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
    getCurrentRarity(){
        let rarity = 'common';
        if(this.rarityCount('epic') > 0){
            rarity = 'epic';
        }
        if(this.rarityCount('legendary') > 0){
            rarity = 'legendary';
        }
        return rarity;
    },
    getSpark(){
        let sparkComp =  _.filter(this.components, function(comp){ return comp.category == 'spark'; });
        return (sparkComp && sparkComp.length > 0) ? sparkComp[0] : null;
    },
    getLowerRarity(){
        let rarity = this.getCurrentRarity();
        return (rarity == 'epic') ? 'common' : (rarity == 'legendary') ? 'epic' : null;
    },
    getFlobotKit(){
        let tempArr = [];
        let rarity = this.getCurrentRarity();
        let flobotkitComp =  _.filter(this.components, function(comp){ return rarity == comp.rarity && comp.category == 'flobotkit'; });
        if(flobotkitComp && flobotkitComp.length > 0){
            tempArr.push(flobotkitComp[0].id);
            return tempArr;
        }
        if(rarity == 'common'){
            return null;
        }
        let lowerRarity = this.getLowerRarity();
        if(lowerRarity){
            flobotkitComp =  _.filter(this.components, function(comp){ return lowerRarity == comp.rarity && comp.category == 'flobotkit'; });
            if(flobotkitComp && flobotkitComp.length >= 5){
                for(let i=0; i<5; i++){
                    tempArr.push(flobotkitComp[i].id);
                }
                return tempArr;
            }
        }
        return null;
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
        let tempRarity = _.find(this.templates, function(temp){ return temp.category == 'flobotkit' && temp.rarity == rarity; });
        return (tempRarity) ? _.filter(this.components, function(comp){ return comp.category == 'flobotkit' && comp.templateId == tempRarity.flow_id; }) : [];
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
                return temp ? window.wrapFlobotSvg(temp.svg) : '';
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
        return window.wrapFlobotSvg(svg);
    },
    getTempSvg(category){
        if(this.templateIds[category]) {
            let temp = _.find(this.templates, { 'flow_id': parseInt(this.templateIds[category],10)});
            return temp ? window.wrapFlobotSvg(temp.svg) : '';
        }
        return window.wrapFlobotSvg('');
    },
    getTempUrl(category){
        return (this.templateIds[category]) ? '/api/image/flobot/template/'+this.templateIds[category] : '';
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
            return temp ? temp.burned+' <small>Flobot'+extraS+'</small>' : 'N/A';
        }
        return 'N/A';
    },
    handleCheckResults(data){
        //console.log(data);
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
    handleFlobotCreated(){
        //alert('Woohoo! You created a brand new unique Flobot. Check it out in your collection');
        this.showConfetti();
        this.showFlobotCreated = true;
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
            if(template && template.rarity == rarity && rareCount == 0){
                rareCount++;
            }
        });

        return rareCount;
    },
    rarityFullCount(rarity){
        if(rarity == 'common'){
            return '1/'+this.filterRarityComp(rarity).length
        }
        return this.rarityCount(rarity)+'/'+this.filterRarityComp(rarity).length;
    },
    rarityCountColor(rarity){
        return (this.rarityCount(rarity) <= this.filterRarityComp(rarity).length) ? '' : 'empty';
    },
    sparkCountColor(){
        return (this.getSparkCount() > 0) ? 'text-green-500' : 'text-red-400';
    },
    hasAllComponents(){
        return this.getFlobotKit() != null;
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
            navigator.clipboard.writeText(window.wrapFlobotSvg(svg));
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
        svg = window.wrapFlobotSvg(svg);
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
        svg = window.wrapFlobotSvg(svg);
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

window.initFlobotBuilder = function(dispatch){
    window.dispatch = dispatch;
}
