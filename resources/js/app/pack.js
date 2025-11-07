import Alpine from "alpinejs";

Alpine.data('packData', () => ({
    packData: null,
    reservingPack: false,
    init(){
        let self = this;
        //window.preReservePack('terrakit');
        //window.reservePack('dustpack', this.address());
    },
    purchasePack(){
        if(this.packData){
            window.dispatch('loading-started');
            window.buyPack(this.packData.id, this.packData.price, this.packData.signature)
        } else if(!this.reservingPack){
            this.reservingPack = true;
            window.reservePack('dustpack', this.address());
        }
    },
    purchasePackAll(packType){
        if(this.packData){
            window.dispatch('loading-started');
            window.buyPack(this.packData.id, this.packData.price, this.packData.signature)
        } else if(!this.reservingPack){
            this.reservingPack = true;
            window.reservePack(packType, this.address());
        }
    }
}));

window.initSales = function(dispatch){
    window.dispatch = dispatch;
}
