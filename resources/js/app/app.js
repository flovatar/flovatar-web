import Alpine from "alpinejs";
import {DateTime} from "luxon";
import Parallax from "parallax-js";
import {config} from "@onflow/fcl";
import {toSvg} from "jdenticon";

Alpine.data('appData', () => ({
    user: null,
    profile: null,
    loading: false,
    waitingSeal: false,
    sealCompleted: false,
    open: false,
    showSubscription: true,
    sealingPack: false,
    packPurchased: false,
    jsLoaded: false,
    showInitPopup: false,
    showInitMetadataPopup: false,
    emailSubscription: '',
    showTransactionError: false,
    showTransactionErrorMessage: false,
    showMaintenance: false,
    showLoginResult: false,
    showReserveResult: false,
    transactionErrorMessage: '',
    txId: null,
    showProfileName: true,
    userCheckLoading: true,
    showWalletModal: false,
    showBlacklistModal: false,
    currentWallet: Alpine.$persist('flow'),
    isVipUser: false,
    remainingMinutesToSale: 0,
    fubPreviewMode: false,
    availableSkins: ['', 'rainbow-unicorn', 'horror-movie', 'retro-80s-pixelated', 'metalic'],
    activeSkin: 0,
    init() {
        //console.log('init');
        //console.log(this.currentWallet);
        window.currentWallet = this.currentWallet;
        let saleDateTime = DateTime.utc(2023, 9, 20, 21, 0);
        let curDateTime = DateTime.utc();
        this.remainingMinutesToSale = Math.ceil(saleDateTime.diff(curDateTime, 'minutes').minutes);
        //console.log('remaining time until sale');
        //console.log(this.remainingMinutesToSale);


        var sceneSalesHero = document.getElementById('sales-hero-box');
        if(sceneSalesHero){
            var parallaxInstance = new Parallax(sceneSalesHero);
        }

    },
    loadLoggedUser(user){
        this.user = user;
        if(user && user.addr){
            this.currentWallet = window.detectWallet(user);
            window.currentWallet = this.currentWallet;
        }

    },
    nextSkin(){
        this.activeSkin++;
        if(this.activeSkin >= this.availableSkins.length){
            this.activeSkin = 0;
        }
    },
    currentSkinClass(){
        let jsHidden = this.jsLoaded ? 'jsloaded ' : '';
        return jsHidden + (this.availableSkins[this.activeSkin] ? 'theme--'+this.availableSkins[this.activeSkin] : '');
    },
    chooseWallet(wallet){
        l('choose wallet');
        window.currentWallet = wallet;
        this.currentWallet = wallet;
        this.showWalletModal = false;
        //l(wallet);
        /*
        if(wallet == 'dapper'){
            config()
                //.put("accessNode.api", process.env.MIX_ACCESS_NODE_DAPPER)
                .put("discovery.wallet", process.env.MIX_WALLET_DISCOVERY_DAPPER)
                .put("discovery.wallet.method", 'POP/RPC')

        } else if(wallet == 'blocto'){
            config()
                //.put("accessNode.api", process.env.MIX_ACCESS_NODE_BLOCTO)
                .put("discovery.wallet", process.env.MIX_WALLET_DISCOVERY_BLOCTO)
                .put("discovery.wallet.method", 'IFRAME/RPC')
        } else if(wallet == 'lilico') {
            config()
                //.put("accessNode.api", process.env.MIX_ACCESS_NODE_LILICO)
                .put("discovery.wallet", process.env.MIX_WALLET_DISCOVERY_LILICO)
                .put("discovery.wallet.method", 'EXT/RPC')
        } else {
            config()
                //.put("accessNode.api", process.env.MIX_ACCESS_NODE)
                .put("discovery.wallet", process.env.MIX_WALLET_DISCOVERY)
                .put("discovery.wallet.method", 'IFRAME/RPC')
        }

         */
        fcl.authenticate();
    },
    isDapperWallet(){
        return this.currentWallet == 'dapper';
    },
    getToasterClass(){
        return this.loading || this.waitingSeal ? 'active' : '';
    },
    copyAddress(){
        if(navigator.clipboard && this.address()) {
            navigator.clipboard.writeText(this.address());
        }
    },
    logoutAccount(){
        window.currentWallet = null;
        this.currentWallet = null;
        window.logout();
    },
    handleTransactionErrorMessage(message){
        this.loading = false;
        this.waitingSeal = false;
        this.sealCompleted = false;
        this.showTransactionError = true;
        this.showTransactionErrorMessage = false;
        this.transactionErrorMessage = message;
    },
    handleAddressFound(data){
        console.log('Handle address found');
        console.log(data);
    },
    submitSubscription() {
        window.sendSubscription(this.emailSubscription);
    },
    startAccountInit(){
        this.showInitPopup = false;
        window.loadInitUser();
    },
    startAccountMetadataInit(){
        this.showInitMetadataPopup = false;
        window.loadInitMetadataViews();
    },
    cancelAccountMetadataInit(){
        this.showInitMetadataPopup = false;
        window.logout();
    },
    cancelAccountInit(){
        this.showInitPopup = false;
        window.logout();
    },
    categories: ['body', 'clothing', 'nose', 'mouth', 'facialHair', 'hair', 'eyes', 'accessory', 'eyeglasses', 'hat', 'background'],
    basicCategories: ['body', 'clothing', 'nose', 'mouth', 'facialHair', 'hair', 'eyes'],
    basicFlobotCategories: ['body', 'head', 'arms', 'legs', 'face'],
    optionalCategories: ['eyeglasses', 'hat', 'accessory', 'background'],
    rarityBoost: ['rare', 'epic', 'legendary'],
    categoriesLabel: {
        spark: 'Spark',
        boost: 'Boost',
        body: 'Body',
        hair: 'Hair',
        eyes: 'Eyes',
        nose: 'Nose',
        mouth: 'Mouth',
        clothing: 'Clothing',
        facialHair: 'Facial Hair',
        eyeglasses: 'Eyeglasses',
        hat: 'Hat',
        accessory: 'Accessory',
        background: 'Background'
    },
    rarities: [
        {
            id: 'legendary',
            name: 'Legendary',
            css: 'bg-pink-700',
            border: 'border-black-100',
            mint: 10,
            score: 90,
            order: 3
        },
        {
            id: 'epic',
            name: 'Ultra Rare',
            css: 'bg-yellow-100',
            border: 'border-black-100',
            mint: 100,
            score: 80,
            order: 2
        },
        {
            id: 'rare',
            name: 'Rare',
            css: 'bg-blue-1300',
            border: 'border-black-100',
            mint: 500,
            score: 60,
            order: 1
        },
        {
            id: 'common',
            name: 'Common',
            css: 'bg-green-50',
            border: 'border-black-100',
            mint: 99999999999,
            score: 40,
            order: 0
        }
    ],
    address(){
        return this.user ? this.user.addr : null;
    },
    name(){
        return (this.profile && this.profile.name) ? this.profile.name : null;
    },
    loggedIn(){
        return this.user && this.profile && this.profile.balance ? true : false;
    },
    balance(){
        return (this.profile && !this.isDapperWallet()) ? Intl.NumberFormat('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2, fractionDigits: 2}).format(parseFloat(this.profile.balance)) : 0;
    },
    dustBalance(){
        return (this.profile) ? parseFloat(this.profile.dustBalance) : 0;
    },
    dustBalanceFormatted(){
        return (this.profile) ? Intl.NumberFormat('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2, fractionDigits: 2}).format(this.dustBalance()) : 0;
    },
    missingDustPackBalanceFormatted(){
        return (this.profile) ? Intl.NumberFormat('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2, fractionDigits: 2}).format(3000 - this.dustBalance()) : 0;
    },
    userAvatar(){
        return this.loggedIn() ? toSvg(this.user.addr, 50) : toSvg('Anon', 50);
    },
    packRequestUrl(){
        return this.loggedIn() ? '/pack/request/'+this.user.addr : '#';
    },
    collectionUrl(){
        return this.loggedIn() ? '/collection/'+this.user.addr : '#';
    },
    lastRarity(){
        return this.rarities[this.rarities.length-1];
    },
    getRarity(val){
        let rarity = _.find(this.rarities, (el) => { return val <= el.mint;});

        return rarity ? rarity.name : this.lastRarity().name;
    },
    getRarityByName(val){
        return _.find(this.rarities, (el) => { return val == el.id;});
    },
    getRarityByScore(val){
        let rarity = _.find(this.rarities, (el) => { return val >= el.score;});

        return rarity ? rarity.name : this.lastRarity().name;
    },
    getRarityClass(val){
        let rarity = _.find(this.rarities, (el) => { return val <= el.mint;});

        return rarity ? rarity.css : this.lastRarity().css;
    },
    getRarityClassByScore(val){
        let rarity = _.find(this.rarities, (el) => { return val >= el.score;});

        return rarity ? rarity.css : this.lastRarity().css;
    },
    getRarityClassByName(val){
        let rarity = _.find(this.rarities, (el) => { return val == el.id;});

        return rarity ? rarity.css : this.lastRarity().css;
    },
    getRarityBorderByName(val){
        let rarity = _.find(this.rarities, (el) => { return val == el.id;});

        return rarity ? rarity.border : this.lastRarity().border;
    },
    filterTemplates(category){
        return _.filter(this.templates, (el) => { return el.category == category; })
    },
    filterTemplate(id){
        return _.find(this.templates, (el) => { return el.flow_id == parseInt(id, 10); })
    },
    getTemplateNameById(id){
        let template = this.filterTemplate(id);
        return template ? template.name : '';
    },
    getTemplateCategoryById(id){
        let template = this.filterTemplate(id);
        return template ? (template.category == 'boost' ? 'booster' : template.category) : '';
    },
    getTemplateRarityById(id){
        let template = this.filterTemplate(id);
        return template ? template.rarity : '';
    },
    signMessage(message){
        window.createSignature(message);
    },
    dapperMessageSigned(){

    },
    showBuyButton(category){
        return (category == 'boost' || category == 'spark' || category == 'hat' || category == 'eyeglasses' || category == 'accessory' || category == 'background');
    }
}));
