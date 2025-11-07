import Alpine from "alpinejs";

Alpine.data('treasureHuntingData', () => ({
    init() {

    },
    loadTreasureHuntingPage(){
        window.location.reload(true);
    },
    failedTreasureHuntingLogin(){

    }
}));


window.initTreasureHunting = function(dispatch){
    window.dispatch = dispatch;
}
