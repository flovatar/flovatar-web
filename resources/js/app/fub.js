import Alpine from "alpinejs";
import html2canvas from 'html2canvas';
import { Cloudinary } from "@cloudinary/url-gen";
import { vectorize } from "@cloudinary/url-gen/actions/effect";

Alpine.data('fubData', () => ({
    templates: Alpine.$persist([]),
    templateIds: {},
    layers: Alpine.$persist([]),
    templateSettings: Alpine.$persist({
        name: 'FUB Demo',
        basePrice: 1,
        baseMaxMint: 1000,
        currency: 'FLOW',
        traitsPriceIncrease: 0
    }),
    activeLayer: 0,
    tempEditLayer: null,
    tempEditTemplate: null,
    activeTemplate: null,
    showEditTemplate: false,
    showEditTemplateSettings: false,
    showFubSettings: false,
    showEditLayer: false,
    showDeleteTemplate: false,
    showResetTemplates: false,
    showDrawOverlay: true,
    showInfo: null,
    currentSvg: '',
    maxTemplates: 1000,
    maxId: Alpine.$persist(0),
    fubDemoSaleCount: 0,
    fubDemoSaleTotal: 0,
    fubDemoSales: [],
    activeTab: 1,
    showContextMenuItem: false,
    cld: null,
    init(){
        if(this.layers.length == 0){
            this.addLayer();
        }
        let self = this;
        window.addEventListener('message', function (e) {
            if(e.data && e.data.command == 'saveSVG'){
                self.currentSvg = e.data.data;
                console.log('received svg');
                self.showEditTemplate = false;
                self.templates = _.map(self.templates, (template) => {
                    if(template.id == self.activeTemplate){
                        template.svg = self.currentSvg;
                    }
                    return template;
                });
            }
        });
        this.randomizeCombination();
        this.cld = new Cloudinary({
            cloud: {
                cloudName: window.cloudNameCloudinary
            }
        });
    },
    cloudinaryLoaded(id){
        const tempImage = this.cld.image(id);
        l('cloudinary image uploaded');
        //l(tempImage);
        let vectImg = tempImage.effect(
            vectorize().numOfColors(10).detailsLevel("0.6").cornersLevel(60)
        ).format('svg');
        l(vectImg.toURL());

        let self = this;
        fetch(vectImg.toURL())
            .then(response => response.text())
            .then(data => {
                l('load svg from cloudinary vectorization');
                l(data);
                let replacedSvg = this.addViewBox(data);
                self.addTemplateDetails(self.activeLayer, 'Template ' + self.templates.length, replacedSvg, null, id);
            });

    },
    fubDemoSaleTotalFormatted(){
        return window.formatPriceNoDecimal(this.fubDemoSaleTotal) + ' ' + this.templateSettings.currency;
    },
    fubSoldOutValue(){
        return window.formatPriceNoDecimal(this.templateSettings.baseMaxMint * this.templateSettings.basePrice * this.layers.length) + ' ' + this.templateSettings.currency;
    },
    fubMintPrice(){
        return window.formatPriceNoDecimal(this.templateSettings.basePrice * this.layers.length) + ' ' + this.templateSettings.currency
    },
    setActiveTab(num){
        this.activeTab = num;
    },
    moveTab(num) {
        this.hideContextMenus();
        if(num < 0 && this.activeTab > 1) {
            this.activeTab += num;
        } else if(this.activeTab < 3) {
            this.activeTab += num;
        }
    },
    setTab(num) {
        this.hideContextMenus();
        this.activeTab = num;
    },
    showContextMenu(elem, menu) { 
        // hide all context menus
        var allContextMenus = Array.from(document.getElementsByClassName('context-menu'));
        for(var i = 0; i < allContextMenus.length; i++) {
            var m = allContextMenus[i];
            m.style.display = 'none';
        }
        // get coords of clicked element
        var bounds = elem.getBoundingClientRect();
        // get active context menu
        var activeMenu = this.$refs[menu];
        activeMenu.style.display = 'inline-block';
        // get dimensions of context menu
        var menuHeight = activeMenu.offsetHeight;
        var menuWidth = activeMenu.offsetWidth;
        // position in relation to window dimensions
        var xPos = bounds.left + window.pageXOffset;
        var yPos = bounds.top + window.pageYOffset - 90;

        if(menuWidth + bounds.left > window.innerWidth) {
            xPos = bounds.left - menuWidth + window.pageXOffset + bounds.width;
        }
        if(menuHeight + bounds.top > window.innerHeight) {
            yPos = bounds.top - menuHeight + window.pageYOffset;
        }
        activeMenu.style.top = yPos+'px';
        activeMenu.style.left = xPos+'px';
        
    },
    hideContextMenus() {
        // hide all context menus
        var allContextMenus = Array.from(document.getElementsByClassName('context-menu'));
        for(var i = 0; i < allContextMenus.length; i++) {
            var m = allContextMenus[i];
            m.style.display = 'none';
        }
    },
    demoMintNft(){
        this.fubDemoSaleCount++;
        this.fubDemoSaleTotal += this.getCurrentPrice();
        this.savePng();
    },
    getCurrentPrice(){
        let total = 0;
        _.each(this.layers, layer => {
            if(this.templateIds[layer.id]) {
                let temp = _.find(this.templates, {'id': this.templateIds[layer.id]});
                total += temp ? parseFloat(temp.price) : 0;
            }
        });
        return total;
    },
    getCurrentPriceFormatted(){
        return window.formatPrice(this.getCurrentPrice()) + ' ' + this.templateSettings.currency;
    },
    addViewBox(svg){
        let tempDiv = document.createElement('div');
        tempDiv.innerHTML = svg;
        let tempSvg = tempDiv.firstChild;
        if(tempSvg) {
            let viewBox = tempSvg.getAttribute('viewBox');
            if (!viewBox) {
                let tempWidth = tempSvg.getAttribute('width');
                let tempHeight = tempSvg.getAttribute('height');
                tempSvg.setAttribute('viewBox', '0 0 ' + tempWidth + ' ' + tempHeight);
                let tempSvgHtml = tempDiv.innerHTML;
                tempDiv.remove();
                return tempSvgHtml;
            } else {
                tempDiv.remove();
                return svg;
            }
        } else {
            return svg;
        }
    },
    processUploadFile(){
        _.each(event.target.files, file => {

            const reader = new FileReader();

            if(file.type == 'image/svg+xml') {
                reader.readAsText(file)
                reader.onload = () => {
                    let replacedSvg = this.addViewBox(reader.result);
                    this.addTemplateDetails(this.activeLayer, 'Template ' + this.templates.length, replacedSvg, null, null);
                };
            } else {
                reader.readAsDataURL(file)
                reader.onload = () => (this.addTemplateDetails(this.activeLayer, 'Template '+this.templates.length, '', reader.result, null));
            }

        });

    },
    processUploadJson(){
        if (event.target.files.length < 1) {
            return
        }
        let file = event.target.files[0];
        if(file){
            const reader = new FileReader()
            reader.readAsText(file);
            let self = this;
            reader.onload = () => {
                let jsonFinal = JSON.parse(reader.result);
                self.templates = jsonFinal.templates;
                self.layers = jsonFinal.layers;
                self.templateSettings = jsonFinal.templateSettings;
            };
        }

    },
    addLayer(){
        this.layers.push({
            id: this.layers.length,
            name: 'Layer '+this.layers.length
        });
        this.activeLayer = this.layers.length - 1;
    },
    addTemplate(layer){
        if(this.maxTemplates > this.templates.length){
            document.getElementById('tldraw-box').contentWindow.postMessage({command: 'reset'}, '*');

            this.maxId++;
            this.templates.push({
                id: this.maxId,
                name: 'Template '+this.templates.length,
                svg: '',
                png: null,
                cloudinary: null,
                cldColors: 5,
                cldDetails: 0.6,
                cldCorners: 60,
                price: this.templateSettings.basePrice ? this.templateSettings.basePrice : 1,
                maxMint: 1000,
                layer: layer
            });

            this.selectTemplate(this.maxId);
        }
    },
    uploadTemplate(layer){
        let el = document.getElementById('upload-fub-image');
        const evt = new MouseEvent("click", {
            view: window,
            bubbles: false,
            cancelable: true
        });
        el.dispatchEvent(evt);
    },
    loadJson(){
        let el = document.getElementById('upload-fub-json');
        const evt = new MouseEvent("click", {
            view: window,
            bubbles: false,
            cancelable: true
        });
        el.dispatchEvent(evt);
    },
    addLayerName(name){
        this.layers.push({
            id: this.layers.length,
            name: name
        });
    },
    addTemplateDetails(layer, name, svg, png, cloudinary){
        if(this.maxTemplates > this.templates.length){
            this.maxId++;
            this.templates.push({
                id: this.maxId,
                name: name,
                svg: svg,
                png: png,
                cloudinary: cloudinary,
                cldColors: 5,
                cldDetails: 0.6,
                cldCorners: 60,
                price: this.templateSettings.basePrice ? this.templateSettings.basePrice : 1,
                maxMint: this.templateSettings.baseMaxMint ? this.templateSettings.baseMaxMint : 1000,
                layer: layer
            });

            this.selectTemplate(this.maxId);
            return this.maxId;
        }
        return null;
    },
    saveTemplateSvg(id, svg){
        for(let i=0; i < this.templates.length; i++){
            if(this.templates[i].id == id){
                this.templates[i].svg = svg;
            }
        }
    },
    reloadCloudinaryImage(){
        let template = this.getTemplateById(this.activeTemplate);
        if(template.cloudinary){
            const tempImage = this.cld.image(template.cloudinary);
            l('cloudinary image refresh');
            l(template);
            let vectImg = tempImage.effect(
                vectorize().numOfColors(parseInt(template.cldColors,10)).detailsLevel(template.cldDetails+'').cornersLevel(parseInt(template.cldCorners,10))
            ).format('svg');
            l(vectImg.toURL());
            let self = this;
            fetch(vectImg.toURL())
                .then(response => response.text())
                .then(data => {
                    l('load svg from cloudinary vectorization');
                    l(data);
                    let replacedSvg = this.addViewBox(data);
                    self.saveTemplateSvg(this.activeTemplate, replacedSvg);
                });
        }
    },
    deleteActiveTemplate(){
        this.hideContextMenus();
        let activeTemplate = this.activeTemplate;
        console.log('remove template');
        console.log(this.templates);
        console.log(activeTemplate);
        this.templates = _.remove(this.templates, (template) => {
            return template.id != activeTemplate;
        });
        console.log(this.templates);
        this.activeTemplate = null;
        this.showDeleteTemplate = false;
    },
    resetTemplates(){
        this.layers = [];
        this.templates = [];
        this.activeLayer = 0;
        this.activeTemplate = null;
        this.showResetTemplates = false;
        this.addLayer();
        this.maxId = 0;
        this.maxTemplates = 50;
    },
    remainingTemplates(){
        return (this.templates && this.maxTemplates) ? this.maxTemplates - this.templates.length : 0;
    },
    getLayerClass(layer){
        return (this.activeLayer == layer.id) ? 'active' : '';
    },
    getTemplateById(id){
        return _.find(this.templates, {'id': parseInt(id, 10)});
    },
    getLayerId(name){
        let layer = _.find(this.layers, {'name': name});
        return layer ? layer.id : null;
    },
    getActiveLayer(){
        return _.find(this.layers, {'id': parseInt(this.activeLayer, 10)});
    },
    getLayer(id){
        return _.find(this.layers, {'id': parseInt(id, 10)});
    },
    getLayerName(id){
        let layer = this.getLayer(id);
        return layer ? layer.name : null;
    },
    getActiveTemplate(){
        return this.getTemplateById(this.activeTemplate);
    },
    getActiveTemplateAttribute(attr){
        if(!this.activeTemplate){
            return '';
        }
        let temp = this.getTemplateById(this.activeTemplate);
        return (temp) ? temp[attr] : '';
    },
    getTemplateSvg(){
        return this.getActiveTemplateAttribute('svg');
    },
    getTemplateName(){
        return this.getActiveTemplateAttribute('name');
    },
    getTemplateClass(id){
        return (id === this.templateIds[this.activeLayer]) ? 'active ' : '';
    },
    selectTemplate(id){
        this.templateIds[this.activeLayer] = id;
        this.activeTemplate = id;
        let temp = this.getTemplateById(this.activeTemplate);
        //console.log(temp);
    },
    selectLayer(id){
        this.activeLayer = id;
        this.activeTemplate = this.templateIds[this.activeLayer] ? this.templateIds[this.activeLayer] : null;
    },
    saveTemplate(){
        this.showEditTemplate = false;
        document.getElementById('tldraw-box').contentWindow.postMessage({command: 'save'}, '*');
    },
    editTemplate(){
        this.hideContextMenus();
        this.tempEditTemplate = this.getActiveTemplate();
        this.showEditTemplateSettings = true;
        console.log(this.tempEditTemplate);
    },
    editLayer(){
        this.hideContextMenus();
        this.tempEditLayer = this.getActiveLayer();
        this.showEditLayer = true;
    },
    saveLayerSettings(){
        this.showEditLayer = false;
        this.tempEditLayer = null;
    },
    saveTemplateSettings(){
        this.showEditTemplateSettings = false;
        this.tempEditTemplate = null;
        this.reloadCloudinaryImage();
    },
    saveFubSettings(){
        this.showFubSettings = false;
    },
    filterTemp(layer){
        return _.filter(this.templates, function(temp){
            return temp.layer == layer
        });
    },
    randomTemp(layer){
        let filterList = this.filterTemp(layer);

        if(!filterList){
            return null;
        }
        let sample = _.sample(filterList);
        return sample ? sample.id : null;
    },
    randomizeCombination(){
        _.each(this.layers, (layer) => {
            this.templateIds[layer.id] = this.randomTemp(layer.id);
        });

        if(!this.activeLayer && this.layers.length > 0){
            this.activeLayer = this.layers[0].id;
        }

        this.activeTemplate = this.templateIds[this.activeLayer];
    },
    getFullSvg(){
        let svg = ''
        _.each(this.layers, (layer) => {
            if(this.templateIds[layer.id]) {
                let temp = _.find(this.templates, {'id': this.templateIds[layer.id]});
                if(temp){
                    svg += temp.png ? '<img src="'+temp.png+'" />' : temp.svg;
                }
            }
        });
        return svg;
    },
    savePng() {

        html2canvas(document.querySelector("#flovatar-builder-box"),{
            backgroundColor: null
        }).then(canvas => {
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
        });

    },
    saveJson() {
        l('save json');
        let jsonTemp = {
            templateSettings: this.templateSettings,
            templates: this.templates,
            layers: this.layers
        };
        var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(jsonTemp));

        const evt = new MouseEvent("click", {
            view: window,
            bubbles: false,
            cancelable: true
        });
        const a = document.createElement("a");
        a.setAttribute("download", `fub-${ (new Date().getTime()).toString(10) }.json`);
        a.setAttribute("href", dataStr);
        a.setAttribute("target", '_blank');
        a.dispatchEvent(evt);
        a.remove();
    },
    loadTemplateDemo(layer, name){
        let layerId = this.getLayerId(layer);
        let tempId = this.addTemplateDetails(layerId, name, '', null, null);
        if(tempId){
            let self = this;
            fetch('/images/v1/'+layer+'/'+name+'.svg')
                .then(response => response.text())
                .then(data => {
                    self.saveTemplateSvg(tempId, data);
                });
        }
    },
    loadDemo(){
        this.layers = [];
        this.templates = [];
        this.activeLayer = 0;
        this.activeTemplate = null;
        this.maxId = 0;
        this.showResetTemplates = false;
        this.maxTemplates = 300;
        this.addLayerName('body');
        this.addLayerName('clothing');
        this.addLayerName('eyes');
        this.addLayerName('nose');
        this.addLayerName('mouth');
        this.addLayerName('hair');
        this.addLayerName('eyeglasses');
        this.addLayerName('accessory');
        this.loadTemplateDemo('body', 'alien');
        this.loadTemplateDemo('body', 'black');
        this.loadTemplateDemo('body', 'brown');
        this.loadTemplateDemo('body', 'light-brown');
        this.loadTemplateDemo('body', 'light-pink');
        this.loadTemplateDemo('body', 'purple');
        this.loadTemplateDemo('body', 'scars');
        this.loadTemplateDemo('body', 'simpson');
        this.loadTemplateDemo('body', 'white');
        this.loadTemplateDemo('body', 'zombie');
        this.loadTemplateDemo('clothing', 'ballerina');
        this.loadTemplateDemo('clothing', 'basketball');
        this.loadTemplateDemo('clothing', 'bikini');
        this.loadTemplateDemo('clothing', 'constructor-worker');
        this.loadTemplateDemo('clothing', 'doctor');
        this.loadTemplateDemo('clothing', 'dress');
        this.loadTemplateDemo('clothing', 'hawaii-shirt');
        this.loadTemplateDemo('clothing', 'hippie');
        this.loadTemplateDemo('clothing', 'hipster');
        this.loadTemplateDemo('clothing', 'hoodie');
        this.loadTemplateDemo('clothing', 'jedi');
        this.loadTemplateDemo('clothing', 'mermaid');
        this.loadTemplateDemo('clothing', 'military');
        this.loadTemplateDemo('clothing', 'ninja');
        this.loadTemplateDemo('clothing', 'pijama-boy');
        this.loadTemplateDemo('clothing', 'pijama-girl');
        this.loadTemplateDemo('clothing', 'police');
        this.loadTemplateDemo('clothing', 'punk');
        this.loadTemplateDemo('clothing', 'scientist');
        this.loadTemplateDemo('clothing', 'skirt');
        this.loadTemplateDemo('clothing', 'sportsuit');
        this.loadTemplateDemo('clothing', 'suit');
        this.loadTemplateDemo('clothing', 'superhero');
        this.loadTemplateDemo('clothing', 'swimwear');
        this.loadTemplateDemo('clothing', 'tailcoat');
        this.loadTemplateDemo('clothing', 'towel');
        this.loadTemplateDemo('clothing', 'tshirt');
        this.loadTemplateDemo('eyes', 'angry');
        this.loadTemplateDemo('eyes', 'calm');
        this.loadTemplateDemo('eyes', 'cry');
        this.loadTemplateDemo('eyes', 'drugs-girl');
        this.loadTemplateDemo('eyes', 'drugs');
        this.loadTemplateDemo('eyes', 'happy-closed');
        this.loadTemplateDemo('eyes', 'happy-girl');
        this.loadTemplateDemo('eyes', 'not-impressed');
        this.loadTemplateDemo('eyes', 'sarcasm-girl');
        this.loadTemplateDemo('eyes', 'sarcasm');
        this.loadTemplateDemo('eyes', 'silly');
        this.loadTemplateDemo('eyes', 'snake');
        this.loadTemplateDemo('eyes', 'tired-girl');
        this.loadTemplateDemo('eyes', 'tired');
        this.loadTemplateDemo('eyes', 'wink');
        this.loadTemplateDemo('nose', 'alien');
        this.loadTemplateDemo('nose', 'big');
        this.loadTemplateDemo('nose', 'cat');
        this.loadTemplateDemo('nose', 'french');
        this.loadTemplateDemo('nose', 'pig');
        this.loadTemplateDemo('nose', 'regular');
        this.loadTemplateDemo('nose', 'small');
        this.loadTemplateDemo('mouth', 'angry');
        this.loadTemplateDemo('mouth', 'tongue');
        this.loadTemplateDemo('mouth', 'sad');
        this.loadTemplateDemo('mouth', 'shocked');
        this.loadTemplateDemo('mouth', 'smile-open');
        this.loadTemplateDemo('mouth', 'smile');
        this.loadTemplateDemo('mouth', 'surprised');
        this.loadTemplateDemo('mouth', 'tooth');
        this.loadTemplateDemo('hair', 'afro-1-blonde');
        this.loadTemplateDemo('hair', 'afro-1-dark');
        this.loadTemplateDemo('hair', 'afro-1-redhead');
        this.loadTemplateDemo('hair', 'afro-2-brown');
        this.loadTemplateDemo('hair', 'afro-2-grey');
        this.loadTemplateDemo('hair', 'bald');
        this.loadTemplateDemo('hair', 'curly-blonde');
        this.loadTemplateDemo('hair', 'curly-dark');
        this.loadTemplateDemo('hair', 'curly-girl-blonde');
        this.loadTemplateDemo('hair', 'curly-girl-dark');
        this.loadTemplateDemo('hair', 'curly-redhead');
        this.loadTemplateDemo('hair', 'curly-white');
        this.loadTemplateDemo('hair', 'elvis-blonde');
        this.loadTemplateDemo('hair', 'elvis-dark');
        this.loadTemplateDemo('hair', 'elvis-redhead');
        this.loadTemplateDemo('hair', 'kid-blonde');
        this.loadTemplateDemo('hair', 'kid-redhead');
        this.loadTemplateDemo('hair', 'kid-dark');
        this.loadTemplateDemo('hair', 'long-straight-blonde');
        this.loadTemplateDemo('hair', 'long-straight-dark');
        this.loadTemplateDemo('hair', 'long-wave-blonde');
        this.loadTemplateDemo('hair', 'long-wave-dark');
        this.loadTemplateDemo('hair', 'mohawk-brown');
        this.loadTemplateDemo('hair', 'mohawk-dark');
        this.loadTemplateDemo('hair', 'short-brown');
        this.loadTemplateDemo('hair', 'short-grey');
        this.loadTemplateDemo('hair', 'short-white');
        this.loadTemplateDemo('hair', 'short-red');
        this.loadTemplateDemo('accessory', 'cigarette');
        this.loadTemplateDemo('accessory', 'cigarette');
        this.loadTemplateDemo('accessory', 'covid-mask');
        this.loadTemplateDemo('accessory', 'ear-piercing');
        this.loadTemplateDemo('accessory', 'earring-large');
        this.loadTemplateDemo('accessory', 'earring-small');
        this.loadTemplateDemo('accessory', 'gold-chain');
        this.loadTemplateDemo('accessory', 'headphones');
        this.loadTemplateDemo('accessory', 'jedi-sword');
        this.loadTemplateDemo('accessory', 'pacifier');
        this.loadTemplateDemo('accessory', 'pearl-necklace');
        this.loadTemplateDemo('accessory', 'punk-metal');
        this.loadTemplateDemo('accessory', 'screwdriver');
        this.loadTemplateDemo('accessory', 'steam');
        this.loadTemplateDemo('eyeglasses', 'aviator');
        this.loadTemplateDemo('eyeglasses', 'generic');
        this.loadTemplateDemo('eyeglasses', 'harry-potter');
        this.loadTemplateDemo('eyeglasses', 'heart');
        this.loadTemplateDemo('eyeglasses', 'monocle-gold');
        this.loadTemplateDemo('eyeglasses', 'monocle');
        this.loadTemplateDemo('eyeglasses', 'pirate');
        this.loadTemplateDemo('eyeglasses', 'scuba');
        this.loadTemplateDemo('eyeglasses', 'square');
        this.loadTemplateDemo('eyeglasses', 'star');
        this.loadTemplateDemo('eyeglasses', 'teardrop');
        this.loadTemplateDemo('eyeglasses', 'vr');
        this.loadTemplateDemo('eyeglasses', 'wayfarer-transparent');
        this.loadTemplateDemo('eyeglasses', 'wayfarer');
        this.randomizeCombination();
    }
}));

window.initFub = function(dispatch){
    window.dispatch = dispatch;
}
