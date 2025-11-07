import Alpine from "alpinejs";

Alpine.data('collectionData', (address, addressName, templates, dustEnabled, dustPerDayScore) => ({
    templates: templates,
    address: address,
    addressName: addressName,
    dustEnabled: dustEnabled,
    dustPerDayScore: dustPerDayScore,
    flovatars: [],
    flovatarIds: [],
    components: [],
    flovatarSales: [],
    flobots: [],
    flobotSales: [],
    collectibles: [],
    packs: [],
    claimables: {},
    componentsTemp: [],
    componentSalesTemp: [],
    showPackAnimation: false,
    showAirdropPackAnimation: false,
    showPackOpening: false,
    showAirdropPackOpening: false,
    fadePackOpening: false,
    fadeAirdropPackOpening: false,
    selectedPackType: null,
    groupSpark: true,
    groupBoosterRare: true,
    groupBoosterEpic: true,
    groupBoosterLegendary: true,
    groupFlobits: {},
    groupTerraKit: {},
    sparkCount: 0,
    rareCount: 0,
    epicCount: 0,
    legendaryCount: 0,
    sparkSaleCount: 0,
    rareSaleCount: 0,
    epicSaleCount: 0,
    legendarySaleCount: 0,
    flobitsCount: {},
    flobitsSaleCount: {},
    upgradeFlobitsStarted: false,
    showPackModal: false,
    fadePackCompleted: false,
    hidePackOpenBtn: false,
    showAirdropPackModal: false,
    fadeAirdropPackCompleted: false,
    hideAirdropPackOpenBtn: false,
    selectedPackToOpen: null,
    componentsPackTemp: [],
    packNewComponents: [],
    collectionFlovatarFailed: false,
    loadingFlovatarFromIdsStarted: false,
    showTerraKit: false,
    showDustPack: false,
    dustPackWrapperClass: '',
    activeTab: 'flovatar',
    burnList: Alpine.$persist([]),
    burnRarity: Alpine.$persist(null),
    showMixedRarityModal: false,
    showAirdropModal: false,
    showUpgradeFlobitModal: false,
    showUpgradeFlobitModalComplete: false,
    nextPage: 1,
    minMint: null,
    maxMint: null,
    sortBy: 'datenew',
    searchRarity: 'all',
    searchCat: 'all',
    searchCat2: 'all',
    searchTemplate: 'all',
    showFilterPanel: false,
    sendDustAmount: 0.0,
    sendDustAddress: '',
    sportpacks: [],
    init(){
        let self = this;
        this.$watch('showPackModal', () => {
            if(!self.showPackModal){
                self.showPackAnimation = false;
                self.showPackOpening = false;
                self.fadePackOpening = false;
                self.selectedPackType = null;
                self.selectedPackToOpen = null;
                self.componentsPackTemp = [];
                self.packNewComponents = [];
                self.fadePackCompleted = false;
                self.hidePackOpenBtn = false;
            }
        });
        this.$watch('showAirdropModal', () => {
            if(!self.showPackModal){
                self.showPackAnimation = false;
                self.showPackOpening = false;
                self.fadePackOpening = false;
                self.selectedPackType = null;
                self.selectedPackToOpen = null;
                self.componentsPackTemp = [];
                self.packNewComponents = [];
                self.fadePackCompleted = false;
                self.hidePackOpenBtn = false;
            }
        });
        this.$watch('showUpgradeFlobitModal', () => {
            self.upgradeFlobitsStarted = false;
        });



        this.$watch('activeTab', () => {
            this.$nextTick(() =>{
                let self = this;
                if(window.collectionFrameSlider){
                    window.collectionFrameSlider.reload();
                }
                this.resetFilterParams();
                setTimeout(function(){
                    if(window.collectionFrameSlider){
                        window.collectionFrameSlider.reload();
                    }
                    self.resetFilterParams();
                },500);
            });
        });
        this.$nextTick(() =>{
            if(window.collectionFrameSlider){
                window.collectionFrameSlider.reload();
            }
            this.resetFilterParams();
        });
    },

    hasParentAccount(){
        return this.claimables && this.claimables.ownerAddress;
    },
    parentAddress(){
        return this.claimables && this.claimables.ownerAddress ? this.claimables.ownerAddress : '';
    },
    loadSportpacks(packs){
        this.sportpacks = packs;
    },
    resetFilterParams(){
        this.sortBy = this.activeTab == 'flobit' ? 'namelow' : 'datenew';
        this.searchRarity = 'all';
        this.searchCat = 'all';
        this.searchCat2 = 'all';
        this.searchTemplate = 'all';
        this.showFilterPanel = false;
        this.minMint = null;
        this.maxMint = null;
    },
    flobitUpgraded(){
        this.burnList = [];
        this.burnRarity = null;
        this.showUpgradeFlobitModalComplete = true;

        this.refreshClaimables();
    },
    refreshClaimables(){
        if(this.address) {
            window.getClaimables(this.address);
        } else if(this.addressName){
            window.getClaimablesByName(this.addressName);
        }
    },
    totalResults(){
        if(this.activeTab == 'flovatar'){
            return this.filteredFlovatars().length;
        } else if(this.activeTab == 'flobot'){
            return this.filteredFlobots().length;
        } else if(this.activeTab == 'flobit'){
            return this.filteredFlobitsUngrouped().length;
        } else if(this.activeTab == 'collectible'){
            return this.filteredCollectibles().length;
        }
    },
    upgradeFlobits(){
        let idArr = _.map(this.burnList, item => {
            return item.id;
        });

        this.upgradeFlobitsStarted = true;

        window.upgradeFlobits(idArr);
    },
    filteredFlovatars(){
        if(!this.flovatarsCombined()){
            return [];
        }
        let self = this;
        let filtered = _.filter(this.flovatarsCombined(), item => {
            let check = true;
            if(self.searchRarity != 'all' && self.searchRarity != self.getRarityText(item)){
                check = false;
            }
            if(self.searchTemplate != 'all'){
                let templateIds = self.getFlovatarTemplateIds(item);
                if(templateIds[self.searchCat] != self.searchTemplate){
                    check = false;
                }
            }
            if(self.minMint && item.id < self.minMint){
                check = false;
            }
            if(self.maxMint && item.id > self.maxMint){
                check = false;
            }
            return check;
        });

        return filtered.sort(function(l,r){
            return self.sortResultFunction(self, l, r);
        });
    },
    filteredFlobots(){
        if(!this.flobotsCombined()){
            return [];
        }
        let self = this;

        let filtered = _.filter(this.flobotsCombined(), item => {
            let check = true;
            if(self.searchRarity != 'all' && self.searchRarity != item.metadata.rarity){
                check = false;
            }
            if(self.searchTemplate != 'all'){
                let templateIds = item.metadata.components;
                if(templateIds[self.searchCat] != self.searchTemplate){
                    check = false;
                }
            }
            if(self.minMint && item.id < self.minMint){
                check = false;
            }
            if(self.maxMint && item.id > self.maxMint){
                check = false;
            }
            return check;
        });

        return filtered.sort(function(l,r){
            return self.sortResultFunction(self, l, r);
        });
    },
    filteredSparkBooster(){
        let self = this;
        return _.filter(this.componentsSparkBooster(), item => {
            return !self.groupComponent(item);
        });
    },
    filteredTerrakit(){
        let self = this;
        return _.filter(this.componentsTerraKit(), item => {
            return !self.groupComponent(item);
        });
    },
    filteredFlobits(){
        let allFlobits = this.componentsFlobit() ? this.componentsFlobit() : [];
        let self = this;

        allFlobits = allFlobits.concat(this.componentsStackedFlobit());

        let filtered = _.filter(allFlobits, item => {
            let check = true;
            let meta = (item.price) ? item.metadata : item;
            if(self.searchRarity != 'all' && self.searchRarity != meta.rarity){
                check = false;
            }
            if(self.searchCat != 'all' && self.searchCat != meta.category){
                check = false;
            }
            if(self.searchTemplate != 'all' && self.searchTemplate != meta.templateId){
                check = false;
            }
            if(!item.count){
                if(self.minMint && item.mint < self.minMint){
                    check = false;
                }
                if(self.maxMint && item.mint > self.maxMint){
                    check = false;
                }
                if(self.groupComponent(item)){
                    check = false;
                }
            } else {
                if(!self.groupComponent(item)){
                    check = false;
                }
            }
            if(!(item.id || item.name)){
                check = false;
            }
            return check;
        });

        return filtered.sort(function(l,r){
            return self.sortResultFunction(self, l, r);
        });
    },
    filteredFlobitsUngrouped(){
        let allFlobits = this.componentsFlobit() ? this.componentsFlobit() : [];
        let self = this;

        let filtered = _.filter(allFlobits, item => {
            let check = true;
            if(self.searchRarity != 'all' && self.searchRarity != item.rarity){
                check = false;
            }
            if(self.searchCat != 'all' && self.searchCat != item.category){
                check = false;
            }
            if(self.searchTemplate != 'all' && self.searchTemplate != item.templateId){
                check = false;
            }
            if(self.minMint && item.mint < self.minMint){
                check = false;
            }
            if(self.maxMint && item.mint > self.maxMint){
                check = false;
            }
            return check;
        });

        return filtered.sort(function(l,r){
            return self.sortResultFunction(self, l, r);
        });
    },
    filteredCollectibles(){
        return this.collectibles;
    },
    sortResultFunction(self, l,r){
        let lId = parseInt(l.id, 10);
        let rId = parseInt(r.id, 10);
        if(self.sortBy == 'datenew'){
            return (lId > rId) ? -1 : ((lId < rId) ? 1 : 0);
        } else if(self.sortBy == 'dateold'){
            return (lId < rId) ? -1 : ((lId > rId) ? 1 : 0);
        } else if(self.sortBy == 'namelow' || self.sortBy == 'namehigh'){
            let lName = l.name ? l.name : self.getComponentName(l);
            let rName = r.name ? r.name : self.getComponentName(r);

            if(self.sortBy == 'namelow'){
                return (lName < rName) ? -1 : ((lName > rName) ? 1 : 0);
            } else {
                return (lName > rName) ? -1 : ((lName < rName) ? 1 : 0);
            }
        } else if(self.sortBy == 'raritylow' || self.sortBy == 'rarityhigh'){
            let rarityL = 0;
            let rarityR = 0;
            if(self.activeTab == 'flobot' || self.activeTab == 'flobit'){
                let rarityArr = {
                    'common': 0,
                    'rare': 1,
                    'epic': 2,
                    'legendary': 3
                }
                let rarityTempL = self.activeTab == 'flobot' ? l.metadata.rarity : l.rarity;
                let rarityTempR = self.activeTab == 'flobot' ? r.metadata.rarity : r.rarity;
                rarityL = rarityArr[rarityTempL];
                rarityR = rarityArr[rarityTempR];
            } else {
                rarityL = parseFloat(window.getRarityScore(l));
                rarityR = parseFloat(window.getRarityScore(r));
            }
            let returnVal = 0;
            if(self.sortBy == 'raritylow'){
                returnVal = (rarityL < rarityR) ? -1 : ((rarityL > rarityR) ? 1 : 0);
            } else {
                returnVal = (rarityL > rarityR) ? -1 : ((rarityL < rarityR) ? 1 : 0);
            }
            if(returnVal == 0){
                let lName = l.name ? l.name : self.getComponentName(l);
                let rName = r.name ? r.name : self.getComponentName(r);
                returnVal = (lName < rName) ? -1 : ((lName > rName) ? 1 : 0);
            }
            return returnVal;
        }
    },
    getFlovatarTemplateIds(flovatar){
        let meta = flovatar.metadata;
        if(meta && meta.combination){
            let arr = window.signatureToArray(meta.combination);
            arr.hat = meta.hatId;
            arr.eyeglasses = meta.eyeglassesId;
            arr.accessory = meta.accessoryId;
            arr.background = meta.backgroundId;
            return arr;
        } else {
            return [];
        }
    },
    showGroupedFlobit(templateId){
        let result = _.find(this.filteredFlobits(), item => {
            return item.templateId == templateId;
        });

        return result && this.groupFlobits[templateId] ? true : false;
    },
    getSearchParams(){
        let params = {
            rarity: this.searchRarity,
            sortby: this.sortBy,
            mintmin: this.minMint,
            mintmax: this.maxMint,
            page: this.nextPage,
            category: this.searchCat,
            template: this.searchTemplate
        };
        let hashParams = this.getHashParams(params);

        if(Object.keys(hashParams).length > 0 || window.location.hash){
            window.writeHashParams(hashParams);
        }
        return params;
    },
    getHashParams(params){
        let hashParams = {};
        if(params.rarity != 'all'){
            hashParams.rarity = params.rarity;
        }
        if(params.sortby != 'datenew'){
            hashParams.sortby = params.sortby;
        }
        if(params.mintmin != null){
            hashParams.mintmin = params.mintmin;
        }
        if(params.mintmax != null){
            hashParams.mintmax = params.mintmax;
        }
        if(params.category != 'all' && params.template != 'all'){
            hashParams.category = params.category;
            hashParams.template = params.template;
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
    getMintRange() {
        let output = '';
        if(this.minMint && this.maxMint){
            output = this.minMint + ' - ' + this.maxMint;
        } else if(this.minMint){
            output = this.minMint + ' - ∞';
        } else if(this.maxMint){
            output = '0 - ' + this.maxMint;
        } else {
            return '';
        }

        return output;
    },
    filteredTemplates(){
        if(this.searchCat != 'all'){
            return _.filter(this.templates, (template) => {
                return template.category == this.searchCat;
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
    flovatarsCombined(){
        return this.flovatars.concat(this.flovatarSales);
    },
    flobotsCombined(){
        return this.flobots.concat(this.flobotSales);
    },
    emptyBurnListItems(){
        let arr = [];
        for(let i = 0; i < 10 - this.burnList.length; i++){
            arr.push(i);
        }
        return arr;
    },
    burnListComponents(){
        return [];
    },
    flovatarCountTotal(){
        return this.flovatars.length + this.flovatarSales.length;
    },
    flobotCountTotal(){
        return this.flobots.length + this.flobotSales.length;
    },
    collectibleCountTotal(){
        return this.collectibles.length;
    },
    terrakitCountTotal(){
        return this.componentsTerraKit().length;
    },
    flobitCountTotal(){
        return this.componentsFlobit().length;
    },
    sparkCountTotal(){
        return this.componentsSparkBooster().length;
    },
    packCountTotal(){
        return this.packs.length;
    },
    componentsSparkBooster(){
        return _.filter(this.components, function(component){
            let item = (component.metadata) ? component.metadata : component;
            return item.category == 'spark' || item.category == 'boost';
        });
    },
    groupComponent(component){
        let item = (component.metadata) ? component.metadata : component;

        if(item.category == 'spark' || item.category == 'boost'){
            return this.groupCategory(item.category, item.rarity);
        } else if(item.category == 'land' || item.category == 'flobotkit' || item.category == 'fub'){
            return this.groupTerraKit[item.templateId] && this.groupTerraKit[item.templateId] === true && this.flobitsCount[item.templateId] && this.flobitsCount[item.templateId] > 1;
        } else {
            return this.groupFlobits[item.templateId] && this.groupFlobits[item.templateId] === true && this.flobitsCount[item.templateId] && this.flobitsCount[item.templateId] > 1;
        }
    },
    groupCategory(category, rarity){
        if(category == 'spark'){
            return this.groupSpark && this.sparkCount > 1;
        } else if(category == 'boost' && rarity == 'rare'){
            return this.groupBoosterRare && this.rareCount > 1;
        } else if(category == 'boost' && rarity == 'epic'){
            return this.groupBoosterEpic && this.epicCount > 1;
        } else if(category == 'boost' && rarity == 'legendary'){
            return this.groupBoosterLegendary && this.legendaryCount > 1;
        } else {
            return false;
        }
    },
    showBodyOutline(component){
        let item = (component.metadata) ? component.metadata : component;
        return item.category !='spark' && item.category != 'boost'  && item.category != 'land' && item.category != 'fub' && item.category != 'flobotkit';
    },
    loadClaimables(data){
        this.claimables = data;
    },
    loadCollections(data){
        //console.log(data);
        this.flovatars = data.flovatars;
        this.flovatarSales = data.flovatarSales;
        this.packs = data.packs;
        this.componentsTemp = data.components;
        this.componentSalesTemp = data.componentSales;
        if(this.address == ''){
            this.address = data.address;
        }
        this.loadComponents();
    },
    flovatarDust(){
        let commDust = this.claimables.flovatarCommunityDust ? this.claimables.flovatarCommunityDust : 0.0
        let flovDust = this.claimables.flovatarDust ? this.claimables.flovatarDust : 0.0;
        return commDust + flovDust;
    },
    sendDust(){
        window.sendDustToAddress(this.sendDustAmount, this.sendDustAddress);
    },
    claimAllDustWithIds(){
        if(this.claimables && this.claimables.flovatarsWithDust && this.claimables.flovatarsWithDust.length > 0) {
            //console.log('claiming dust for 40 flovatars');
            //console.log(this.claimables.flovatarsWithDust.slice(0, 40));
            window.claimAllDustByIds(this.claimables.flovatarsWithDust.slice(0, 30));
        } else {
            window.claimAllDust();
        }
    },
    claimAllChildDustWithIds(){
        if(this.claimables && this.claimables.childFlovatarsWithDust && this.claimables.childFlovatarsWithDust.length > 0 && this.claimables.childFlovatarsWithDustAddress && this.claimables.childFlovatarsWithDustAddress.length > 0) {
            //console.log('claiming dust for 40 flovatars');
            //console.log(this.claimables.flovatarsWithDust.slice(0, 40));
            window.claimAllChildDustByIds(this.claimables.childFlovatarsWithDust.slice(0, 30), this.claimables.childFlovatarsWithDustAddress.slice(0, 30));
        }
    },
    hasChildAccounts(){
        return this.claimables && this.claimables.childAddresses && this.claimables.childAddresses.length > 0;
    },
    childAddresses(){
        return this.claimables && this.claimables.childAddresses ? this.claimables.childAddresses : [];
    },
    currentAddressCollection(){
        return this.user ? this.user.addr : null;
    },
    airdropDust(){
        let totalDust = 0.0;
        if(this.claimables.flovatarDust){
            totalDust += this.claimables.flovatarDust;
        }
        if(this.claimables.walletDust){
            totalDust += this.claimables.walletDust;
        }
        return totalDust;
    },
    totalDust(){
        return this.flovatarDust() + this.airdropDust();
    },
    totalDustFormatted(){
        return window.formatPrice(this.totalDust());
    },
    flovatarDustPerDay(flovatar){
        return parseFloat((3 + parseFloat(window.getRarityScore(flovatar))) * this.dustPerDayScore);
    },
    dailyDust(){
        let total = 0.0;
        let self = this;
        _.each(this.flovatarsCombined(), flovatar => {
            total += self.flovatarDustPerDay(flovatar);
        });
        return total;
    },
    dailyDustFormatted(){
        return window.formatPrice(this.dailyDust());
    },
    airdropFlobits(){
        let totalFlobits = 0;
        if(this.claimables.flovatarComponents){
            totalFlobits += this.claimables.flovatarComponents.length;
        }
        if(this.claimables.walletComponents){
            totalFlobits += this.claimables.walletComponents.length;
        }
        return totalFlobits;
    },
    loadCollectionsPack(data){
        this.packs = data.packs;
        if(this.address == ''){
            this.address = data.address;
        }
    },
    loadCollectionsFlovatar(data){
        this.flovatars = data.flovatars;
        if(this.address == ''){
            this.address = data.address;
        }
    },
    loadCollectionsFlovatarSale(data){
        this.flovatarSales = data.flovatarSales;
        if(this.address == ''){
            this.address = data.address;
        }
    },
    loadCollectionsFlobot(data){
        this.flobots = data.flobots;
        if(this.address == ''){
            this.address = data.address;
        }
    },
    loadCollectionsFlobotSale(data){
        this.flobotSales = data.flobotSales;
        if(this.address == ''){
            this.address = data.address;
        }
    },
    loadCollectionsCollectible(data){
        this.collectibles = data.collectibles;
        //console.log(this.collectibles);
        if(this.address == ''){
            this.address = data.address;
        }
    },
    loadCollectionsComponent(data){
        this.componentsTemp = data.components;
        if(this.address == ''){
            this.address = data.address;
        }

        if(this.showPackOpening){

            let self = this;
            self.fadePackOpening = true;
            this.packNewComponents = _.filter(this.componentsTemp, (item) => {
                let check = _.find(self.componentsPackTemp, (item2) =>{
                    return item2.id == item.id;
                });
                return !check;
            });

            if(this.showTerraKit){
                document.getElementById("front_panel_pulse").classList.add('hide')

                document.getElementById("pack_15_37_anim").beginElement()
                document.getElementById("pack_15_36_anim").beginElement()
                document.getElementById("pack_15_35_anim").beginElement()
                document.getElementById("pack_15_34_anim").beginElement()
                document.getElementById("pack_15_33_anim").beginElement()
                document.getElementById("pack_15_32_anim").beginElement()
                document.getElementById("pack_15_31_anim").beginElement()
                document.getElementById("pack_15_30_anim").beginElement()
                document.getElementById("pack_15_29_anim").beginElement()
                document.getElementById("pack_15_28_anim").beginElement()
                document.getElementById("pack_15_27_anim").beginElement()
                document.getElementById("pack_15_26_anim").beginElement()
                document.getElementById("pack_15_25_anim").beginElement()
                document.getElementById("pack_15_24_anim").beginElement()
                document.getElementById("pack_15_23_anim").beginElement()
                document.getElementById("pack_15_22_anim").beginElement()
                document.getElementById("pack_15_21_anim").beginElement()
                document.getElementById("pack_15_20_anim").beginElement()
                document.getElementById("pack_15_19_anim").beginElement()
                document.getElementById("pack_15_18_anim").beginElement()
                document.getElementById("pack_15_17_anim").beginElement()
                document.getElementById("pack_15_16_anim").beginElement()
                document.getElementById("pack_15_15_anim").beginElement()
                document.getElementById("pack_15_14_anim").beginElement()
                document.getElementById("pack_15_13_anim").beginElement()
                document.getElementById("pack_15_12_anim").beginElement()
                document.getElementById("pack_15_11_anim").beginElement()
                document.getElementById("pack_15_10_anim").beginElement()
                document.getElementById("pack_15_9_anim").beginElement()
                document.getElementById("pack_15_8_anim").beginElement()
                document.getElementById("pack_15_7_anim").beginElement()
                document.getElementById("pack_15_6_anim").beginElement()
                document.getElementById("pack_15_5_anim").beginElement()
                document.getElementById("pack_15_4_anim").beginElement()
                document.getElementById("pack_15_3_anim").beginElement()
                document.getElementById("pack_15_2_anim").beginElement()
                document.getElementById("pack_15_1_anim").beginElement()
                document.getElementById("pack_15_0_anim").beginElement()
                document.getElementById("pack_14_38_anim").beginElement()
                document.getElementById("pack_13_1_anim").beginElement()
                document.getElementById("pack_13_0_anim").beginElement()
                document.getElementById("bolt_left_109_anim_1").beginElement()
                document.getElementById("bolt_left_109_anim_2").beginElement()
                document.getElementById("bolt_left_109_anim_3").beginElement()
                document.getElementById("piston_full_109_anim").beginElement()
                document.getElementById("latch_anim_1").beginElement()
                document.getElementById("latch_anim_2").beginElement()
                document.getElementById("latch_anim_3").beginElement()
                document.getElementById("pack_7_0_anim").beginElement()
                document.getElementById("pack_5_0_anim").beginElement()
                document.getElementById("top_buttons_1").beginElement()
                document.getElementById("top_buttons_2").beginElement()
                document.getElementById("pack_2_22_anim").beginElement()
                document.getElementById("pack_2_21_anim").beginElement()
                document.getElementById("pack_2_20_anim").beginElement()
                document.getElementById("pack_2_19_anim").beginElement()
                document.getElementById("pack_2_18_anim").beginElement()
                document.getElementById("pack_2_17_anim").beginElement()
                document.getElementById("pack_2_16_anim").beginElement()
                document.getElementById("pack_2_15_anim").beginElement()
                document.getElementById("pack_2_14_anim").beginElement()
                document.getElementById("pack_2_13_anim").beginElement()
                document.getElementById("pack_2_12_anim").beginElement()
                document.getElementById("pack_2_11_anim").beginElement()
                document.getElementById("pack_2_10_anim").beginElement()
                document.getElementById("pack_2_9_anim").beginElement()
                document.getElementById("pack_2_8_anim").beginElement()
                document.getElementById("pack_2_7_anim").beginElement()
                document.getElementById("pack_2_6_anim").beginElement()
                document.getElementById("pack_2_5_anim").beginElement()
                document.getElementById("pack_2_4_anim").beginElement()
                document.getElementById("pack_2_3_anim").beginElement()
                document.getElementById("pack_2_2_anim").beginElement()
                document.getElementById("pack_2_1_anim").beginElement()
                document.getElementById("pack_2_0_anim").beginElement()
                document.getElementById("inside_glow_anim_1").beginElement()
                document.getElementById("lid_open_3_anim").beginElement()
                document.getElementById("lid_open_2_anim").beginElement()
                document.getElementById("lid_open_1_anim").beginElement()
                document.getElementById("glow_ring_1_anim_1").beginElement()
                document.getElementById("glow_ring_1_anim_2").beginElement()
                document.getElementById("glow_ring_1_anim_3").beginElement()
                document.getElementById("glow_ring_1_anim_4").beginElement()
                document.getElementById("glow_ring_2_anim_1").beginElement()
                document.getElementById("glow_ring_2_anim_2").beginElement()
                document.getElementById("glow_ring_2_anim_3").beginElement()
                document.getElementById("glow_ring_2_anim_4").beginElement()
                document.getElementById("flash_anim_1").beginElement()
                document.getElementById("flash_anim_2").beginElement()

            } else if(!this.showDustPack){

                document.getElementById("lid_open3_anim").beginElement()
                document.getElementById("lid_open2_anim").beginElement()
                document.getElementById("lid_open1_anim").beginElement()
                document.getElementById("lid_closed_anim").beginElement()
                document.getElementById("lid_handle_light_off_anim1").beginElement()
                document.getElementById("lid_handle_light_off_anim2").beginElement()
                document.getElementById("lid_handle_light_on_anim").beginElement()
                document.getElementById("lid_shade_anim").beginElement()
                document.getElementById("round_lights_on_anim0").beginElement()
                document.getElementById("round_lights_on_anim1").beginElement()
                document.getElementById("round_lights_on_anim2").beginElement()
                document.getElementById("round_lights_on_anim3").beginElement()
                document.getElementById("lozenge_lights_anim2").beginElement()
                document.getElementById("lozenge_lights_anim1").beginElement()
                document.getElementById("lozenge_lights_anim0").beginElement()

                document.getElementById("glow_leak_display_anim").beginElement()
                document.getElementById("glow_leak_trans1_anim").beginElement()
                document.getElementById("glow_leak_trans2_anim").beginElement()
                document.getElementById("glow_leak_trans3_anim").beginElement()

                document.getElementById("glow_trans1_anim1").beginElement()
                document.getElementById("glow_trans2_anim1").beginElement()
                document.getElementById("glow_trans3_anim1").beginElement()
                document.getElementById("glow_display_anim1").beginElement()

                document.getElementById("glow_trans1_anim2").beginElement()
                document.getElementById("glow_trans2_anim2").beginElement()
                document.getElementById("glow_trans3_anim2").beginElement()
                document.getElementById("glow_display_anim2").beginElement()

                document.getElementById("glow_display_anim3").beginElement()
            } else {
                this.dustPackWrapperClass = 'open';
            }


            setTimeout(function(){
                self.fadePackCompleted = true;
            },2000);
        }

        this.loadComponents();
    },
    loadCollectionsFlovatarIds(data){
        this.flovatarIds = data.flovatarIds;
        if(this.address == ''){
            this.address = data.address;
        }
        if(this.collectionFlovatarFailed && !this.loadingFlovatarFromIdsStarted){
            this.loadFlovatarFromIds();
        }
    },
    loadCollectionsComponentSale(data){
        this.componentSalesTemp = data.componentSales;
        if(this.address == ''){
            this.address = data.address;
        }
        this.loadComponents();
    },
    loadFlovatarFromIds(){
        this.loadingFlovatarFromIdsStarted = true;
        console.log('Load Flovatars one by one in Collection');
        let self = this;
        let timeout = 0;
        _.each(this.flovatarIds, (item) => {
            setTimeout(function(){
                window.getFlovatar(self.address, item);
            }, timeout);
            timeout += 150;
        });
    },
    loadSingleFlovatar(data){
        //console.log('single flovatar loaded');
        //console.log(data);
        this.flovatars.push(data.flovatar);
    },
    loadComponents(){
        let self = this;
        self.sparkCount = 0;
        self.rareCount = 0;
        self.epicCount = 0;
        self.legendaryCount = 0;
        self.sparkSaleCount = 0;
        self.rareSaleCount = 0;
        self.epicSaleCount = 0;
        self.legendarySaleCount = 0;
        self.flobitsCount = {};
        self.flobitsSaleCount = {};

        let collection = [];
        _.each(self.componentSalesTemp, function(item){
            collection.push(item);
            if(item.metadata.category == 'spark'){
                self.sparkCount++;
                self.sparkSaleCount++;
            } else if(item.metadata.category == 'boost' && item.metadata.rarity == 'rare'){
                self.rareCount++;
                self.rareSaleCount++;
            } else if(item.metadata.category == 'boost' && item.metadata.rarity == 'epic'){
                self.epicCount++;
                self.epicSaleCount++;
            } else if(item.metadata.category == 'boost' && item.metadata.rarity == 'legendary'){
                self.legendaryCount++;
                self.legendarySaleCount++;
            } else {
                self.flobitsCount[item.metadata.templateId] = self.flobitsCount[item.metadata.templateId] ? self.flobitsCount[item.metadata.templateId] + 1 : 1;
                self.flobitsSaleCount[item.metadata.templateId] = self.flobitsSaleCount[item.metadata.templateId] ? self.flobitsSaleCount[item.metadata.templateId] + 1 : 1;
                if(self.flobitsCount[item.metadata.templateId] && self.flobitsCount[item.metadata.templateId] > 1){
                    if(item.metadata.category == 'land' || item.metadata.category == 'flobotkit' || item.metadata.category == 'fub'){
                        self.groupTerraKit[item.metadata.templateId] = true;
                    } else {
                        self.groupFlobits[item.metadata.templateId] = true;
                    }
                }
            }
        });
        _.each(self.componentsTemp, function(item){
            collection.push(item);
            if(item.category == 'spark'){
                self.sparkCount++;
            } else if(item.category == 'boost' && item.rarity == 'rare'){
                self.rareCount++;
            } else if(item.category == 'boost' && item.rarity == 'epic'){
                self.epicCount++;
            } else if(item.category == 'boost' && item.rarity == 'legendary'){
                self.legendaryCount++;
            } else {
                self.flobitsCount[item.templateId] = self.flobitsCount[item.templateId] ? self.flobitsCount[item.templateId] + 1 : 1;
                if(self.flobitsCount[item.templateId] && self.flobitsCount[item.templateId] > 1){
                    if(item.category == 'land' || item.category == 'flobotkit' || item.category == 'fub'){
                        self.groupTerraKit[item.templateId] = true;
                    } else {
                        self.groupFlobits[item.templateId] = true;
                    }
                }
            }
        });

        this.components = collection.sort(function (l,r){
            let lItem = l.metadata ? l.metadata : l;
            let rItem = r.metadata ? r.metadata : r;

            if(lItem.category == rItem.category){
                let lTemp = self.getTemplateById(lItem.templateId);
                let rTemp = self.getTemplateById(rItem.templateId);

                if(lTemp && rTemp) {
                    if (lTemp.name > rTemp.name) {
                        return 1;
                    } else if (lTemp.name < rTemp.name) {
                        return -1;
                    } else {
                        if(lItem.mint > rItem.mint){
                            return 1
                        } else if(lItem.mint < rItem.mint){
                            return -1
                        } else {
                            return 0;
                        }
                    }
                } else {
                    if(lItem.templateId > rItem.templateId){
                        return 1;
                    } else if(lItem.templateId < rItem.templateId){
                        return -1;
                    } else {
                        return 0;
                    }
                }
            } else {
                if(lItem.category > rItem.category){
                    return 1;
                } else {
                    return -1;
                }
            }
        });

    },

    componentsFlobit(){
        return _.filter(this.components, function(component){
            let metadata = (component.metadata && component.metadata.category) ? component.metadata : component;
            return metadata.category != 'spark' && metadata.category != 'boost' && metadata.category != 'land' && metadata.category != 'fub' && metadata.category != 'flobotkit';
        });
    },
    componentsTerraKit(){
        return _.filter(this.components, function(component){
            let metadata = (component.metadata && component.metadata.category) ? component.metadata : component;
            return metadata.category == 'land' || metadata.category == 'fub' || metadata.category == 'flobotkit';
        });
    },
    componentsStackedFlobit(){
        let collection = [];
        let self = this;
        _.each(this.groupFlobits, function(val, key){
            let temp = self.getTemplateById(key);
            collection.push({
                templateId: key,
                count: self.flobitsCount[key],
                salesCount: self.flobitsSaleCount[key] ? self.flobitsSaleCount[key] : 0,
                category: temp ? temp.category : 'N/A',
                name: temp ? temp.name : 'N/A',
                rarity: temp ? temp.rarity : 'N/A'
            });
        });
        return collection;
    },
    componentsStackedTerraKit(){
        let collection = [];
        let self = this;
        _.each(this.groupTerraKit, function(val, key){
            let temp = self.getTemplateById(key);
            collection.push({
                templateId: key,
                count: self.flobitsCount[key],
                salesCount: self.flobitsSaleCount[key] ? self.flobitsSaleCount[key] : 0,
                category: temp ? temp.category : 'N/A',
                name: temp ? temp.name : 'N/A',
                rarity: temp ? temp.rarity : 'N/A'
            });
        });
        return collection;
    },
    showOpenPack(){
        return this.isCollectionOwner();
    },
    isCollectionOwner(){
        return (this.user && this.address == this.user.addr);
    },
    selectPackToOpen(pack){
        if(this.showOpenPack()){
            this.selectedPackType = (pack > 6800) ? ((pack>9799) ? ((pack>10799) ? ((pack>15799 || pack>15689) ? 'dustpack' : 'terrakit') : 'flobits') : 'deluxe') : 'standard';
            this.componentsPackTemp = this.components;
            if(pack>9799){
                this.showTerraKit = (pack>10799 && (pack<15689)) ? true : false;
                this.showDustPack = (pack>15799 || pack>15689) ? true : false;
                console.log(pack);
                console.log(this.showTerraKit);
                console.log(this.showDustPack);
                this.showPackModal = true;
                this.selectedPackToOpen = pack;
            } else {
                window.openPack(pack);
            }
        }
    },
    startPackOpening(){
        this.showPackAnimation = true;

        if(this.showTerraKit){
            document.getElementById("pulse1").beginElement()
            document.getElementById("pulse2").beginElement()
            document.getElementById("pulse3").beginElement()
            document.getElementById("pulse4").beginElement()
            document.getElementById("pulse5").beginElement()
            document.getElementById("pulse6").beginElement()
            document.getElementById("pulse7").beginElement()
            document.getElementById("pulse8").beginElement()
            document.getElementById("pulse9").beginElement()
            document.getElementById("pulse10").beginElement()
        } else if(!this.showDustPack){
            document.getElementById("message_move").beginElement()
            document.getElementById("message_scale").beginElement()

            document.getElementById("pulse0").beginElement()
            document.getElementById("pulse1").beginElement()
            document.getElementById("pulse2").beginElement()
            document.getElementById("pulse3").beginElement()
            document.getElementById("pulse4").beginElement()
            document.getElementById("pulse5").beginElement()
            document.getElementById("pulse6").beginElement()
            document.getElementById("pulse7").beginElement()
            document.getElementById("pulse8").beginElement()
            document.getElementById("pulse9").beginElement()
            document.getElementById("pulse10").beginElement()
            document.getElementById("pulse11").beginElement()
            document.getElementById("pulse12").beginElement()
            document.getElementById("pulse13").beginElement()
            document.getElementById("pulse14").beginElement()
            document.getElementById("pulsing_lights_anim").beginElement()
        } else {
            this.dustPackWrapperClass = 'loading';
        }
    },
    startAirdropPackOpening(){
        this.showPackAnimation = true;
    },
    claimAirdropFlobits(){
        this.componentsPackTemp = this.components;
        window.claimFlobits();
        this.hidePackOpenBtn = true;
    },
    handlePackOpening(){
        this.showPackOpening = true;
    },
    handleInboxClaimed(){
        console.log('inbox claimed successfully!');
        this.showPackOpening = true;
        this.refreshClaimables();
    },
    hidePackOpening(){
        let self = this;
        self.fadePackOpening = true;
        setTimeout(function(){
            self.showPackAnimation = false;
            self.showPackOpening = false;
            self.fadePackOpening = false;
            self.selectedPackType = null;
            self.selectedPackToOpen = null;
        },1000);
    },
    getRarityText(flovatar){
        if(flovatar.metadata){
            return window.getFlovatarRarityText(flovatar);
        } else {
            return '';
        }
    },

    getRarityColor(flovatar){
        return this.getRarityClassByName(this.getRarityText(flovatar));
    },
    getRarityColorComponent(component){
        return component.metadata ? component.metadata.rarity : component.rarity;
    },
    getMaxMintable(templateId){
        let template = _.find(this.templates, (el) => { return el.flow_id == parseInt(templateId,10); });
        return template ? template.max_mintable : '';
    },
    getComponentMint(component){
        let compMint = component.metadata ? component.metadata.mint : component.mint;
        let maxMint = this.getMaxMintable(component.metadata ? component.metadata.templateId : component.templateId);
        return '# ' + compMint + ' / '+maxMint;
    },
    isComponentFlobit(component){
        let meta = this.getComponentMeta(component);
        return !(meta.category == 'spark' || meta.category == 'boost' || meta.category == 'land' || meta.category == 'fub' || meta.category == 'flobotkit');
    },
    isComponentUpgradableFlobit(component){
        let meta = this.getComponentMeta(component);
        return this.burnList && this.burnList.length < 10 && (!this.burnRarity || this.burnRarity == meta.rarity) && !this.isFlobitInUpgradeList(component) && this.isComponentFlobit(component);
    },
    isFlobitInUpgradeList(component){
        let match = _.find(this.burnList, item => {
            return item.id == component.id;
        });
        return match ? true : false;
    },
    addFlobitToUpgrade(component){
        let meta = this.getComponentMeta(component);
        if(!this.isFlobitInUpgradeList(component) && this.burnList.length < 10){
            this.burnList.push(component);
            if(!this.burnRarity){
                this.burnRarity = meta.rarity;
            }
        }
    },
    removeFlobitToUpgrade(component){
        let meta = this.getComponentMeta(component);
        this.burnList = _.filter(this.burnList, item => {
            return item.id != component.id;
        });
        if(this.burnList.length == 0){
            this.burnRarity = null;
        }
    },
    getComponentMeta(component){
        return component.metadata ? component.metadata : component;
    },
    getComponentCategory(component){
        let meta = this.getComponentMeta(component);
        if(this.isComponentFlobit(component)){
            return 'Category'
        }
        return component.metadata ? component.metadata.category : component.category;
    },
    getComponentName(component){
        let meta = this.getComponentMeta(component);
        if(meta.category == 'boost'){
            return 'Booster'
        }
        return component.metadata ? component.metadata.name : component.name;
    },
    getComponentOutlineClass(component){
        return (this.getComponentName(component) == 'Booster' || this.getComponentName(component) == 'Spark'  || this.getComponentName(component) == 'FUB' || this.getComponentName(component) == 'Asteroid' || this.getComponentName(component) == 'Flobot Kit') ? '' : 'builder-component-thumb'
    },
    getTemplateById(id){
        return _.find(this.templates, function(item){ return item.flow_id == parseInt(id,10)});
    }

}));


window.initCollection = function(dispatch, address, addressName, dustEnabled){
    window.dispatch = dispatch;
    if(address) {
        //window.getCollections(address);
        window.getCollectionsFlovatarIds(address);
        window.getCollectionsPack(address);
        window.getCollectionsFlovatar(address);
        window.getCollectionsFlovatarSale(address);
        window.getCollectionsComponent(address);
        window.getCollectionsComponentSale(address);
        window.getCollectionsFlobot(address);
        window.getCollectionsCollectible(address);
        if(dustEnabled) {
            window.getClaimables(address);
        }
    } else if(addressName){
        //window.getCollectionsByName(addressName);
        window.getCollectionsByNameFlovatarIds(addressName);
        window.getCollectionsByNamePack(addressName);
        window.getCollectionsByNameFlovatar(addressName);
        window.getCollectionsByNameFlobot(addressName);
        window.getCollectionsByNameFlovatarSale(addressName);
        window.getCollectionsByNameComponent(addressName);
        window.getCollectionsByNameComponentSale(addressName);
        window.getCollectionsByNameCollectible(addressName);
        if(dustEnabled) {
            window.getClaimablesByName(addressName);
        }
    }
}
