import {DateTime} from "luxon";
import * as fcl from "@onflow/fcl";
import Cookies from "js-cookie";


window.parseDate = function(date){
    return DateTime.fromSeconds(parseFloat(date)).toLocaleString(DateTime.DATE_SHORT);
}
window.parseTime = function(date){
    return DateTime.fromSeconds(parseFloat(date)).toLocaleString(DateTime.TIME_SIMPLE);
}

window.parseDateTime = function(date){
    return window.parseDate(date) + ' ' + window.parseTime(date);
}


window.parseDateTimeString = function(date){
    let tempDate = DateTime.fromMillis(Date.parse(date)).toLocaleString(DateTime.DATE_SHORT);
    let tempTime = DateTime.fromMillis(Date.parse(date)).toLocaleString(DateTime.TIME_SIMPLE);

    return tempDate + ' ' + tempTime;
}

window.logout = function(){
    console.log('logout');
    fcl.unauthenticate();
    window.user = null;
    window.profile = null;
    window.dispatch('logged-out');

    // Also end the server (Laravel) session, without leaving the page.
    fetch('/logout', {credentials: 'same-origin', redirect: 'manual', cache: 'no-store'}).catch(function(){});
    window.serverAuth = {authenticated: false, address: null};
    window.dispatchEvent(new CustomEvent('server-auth', {detail: window.serverAuth}));
    window.accountProofNonce = null;
    if(window.loadLoginNonce){ setTimeout(window.loadLoginNonce, 500); }
}

window.getDiffString = function(start, end){
    let startD = (!start) ? DateTime.now() : DateTime.fromSeconds(parseFloat(start));
    let endD = (!end) ? DateTime.now() : DateTime.fromSeconds(parseFloat(end));
    let diff = endD.diff(startD, ['hours', 'minutes', 'seconds']);
    return diff.hours+'h '+diff.minutes+'m '+Math.round(diff.seconds)+'s';
}

window.formatPrice = function(price){
    return new Intl.NumberFormat('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2, fractionDigits: 2}).format(parseFloat(price));
}

window.formatPriceSmall = function(price){
    let intPrice = Math.floor(parseFloat(price));
    let decimals = '' + new Intl.NumberFormat('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2, fractionDigits: 2}).format(parseFloat(price) - intPrice);
    decimals = decimals.replace('0.','');
    return intPrice + '.<small>' + decimals + '</small>';
}
window.formatPriceNoDecimal = function(price){
    return new Intl.NumberFormat('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0, fractionDigits: 0}).format(parseFloat(price));
}
window.formatPriceNoDecimalNoComma = function(price){
    return Math.floor(parseFloat(price));
}

window.floatArg = function(val){
    return new Intl.NumberFormat('en-US', {useGrouping: false, minimumFractionDigits: 2, maximumFractionDigits: 2, fractionDigits: 2}).format(parseFloat(val));
}

window.floatArgFull = function(val){
    return new Intl.NumberFormat('en-US', {useGrouping: false, minimumFractionDigits: 6, maximumFractionDigits: 6, fractionDigits: 6}).format(parseFloat(val));
}

window.wrapSvg = function(svg){
    svg = (!svg) ? '<rect x="0" y="0" width="3000" height="3000" style="fill:#fff;fill-opacity:0;"/>' : svg;
    return window.svgOpen+svg+window.svgClose;
}

window.getSvg = function(templateId){
    let template = _.find(window.templates, function (x) { return x.flow_id == templateId });
    return (template) ? window.wrapSvg(template.svg) : '';
}

window.wrapFlobotSvg = function(svg){
    svg = (!svg) ? '<rect x="0" y="0" width="3000" height="3000" style="fill:#fff;fill-opacity:0;"/>' : svg;
    return window.svgOpenFlobot+svg+window.svgClose;
}

window.getFlobotSvg = function(templateId){
    let template = _.find(window.templates, function (x) { return x.flow_id == templateId });
    return (template) ? window.wrapFlobotSvg(template.svg) : '';
}


window.flovatarSvg = function(flovatar){
    if(!flovatar){
        return '';
    }
    let background = flovatar.backgroundId ? _.find(window.templates, function(item){return item.flow_id == flovatar.backgroundId;}) : '';
    background = background ? background.svg : '';
    let accessory = flovatar.accessoryId ? _.find(window.templates, function(item){return item.flow_id == flovatar.accessoryId;}) : '';
    accessory = accessory ? accessory.svg : '';
    let hat = flovatar.hatId ? _.find(window.templates, function(item){return item.flow_id == flovatar.hatId;}) : '';
    hat = hat ? hat.svg : '';
    let eyeglasses = flovatar.eyeglassesId ? _.find(window.templates, function(item){return item.flow_id == flovatar.eyeglassesId;}) : '';
    eyeglasses = eyeglasses ? eyeglasses.svg : '';
    return window.wrapSvg(background+flovatar.metadata.svg+accessory+eyeglasses+hat);
}


window.signatureToArrayScore = function(signature){
    let arr = {};

    let sigArr = signature.replace('B', '');
    sigArr = sigArr.split('H');
    arr.body = getTemplateScore(sigArr[0]);
    sigArr = sigArr[1].split('F');
    arr.hair = getTemplateScore(sigArr[0]);
    sigArr = sigArr[1].split('E');
    arr.facialHair = (sigArr[0] == 'x') ? 0 : getTemplateScore(sigArr[0]);
    sigArr = sigArr[1].split('N');
    arr.eyes = getTemplateScore(sigArr[0]);
    sigArr = sigArr[1].split('M');
    arr.nose = getTemplateScore(sigArr[0]);
    sigArr = sigArr[1].split('C');
    arr.mouth = getTemplateScore(sigArr[0]);
    arr.clothing = getTemplateScore(sigArr[1]);

    return arr;
}
window.signatureToArray = function(signature){
    let arr = {};

    let sigArr = signature.replace('B', '');
    sigArr = sigArr.split('H');
    arr.body = parseInt(sigArr[0],10);
    sigArr = sigArr[1].split('F');
    arr.hair = parseInt(sigArr[0],10);
    sigArr = sigArr[1].split('E');
    arr.facialHair = (sigArr[0] == 'x') ? 0 : parseInt(sigArr[0],10);
    sigArr = sigArr[1].split('N');
    arr.eyes = parseInt(sigArr[0],10);
    sigArr = sigArr[1].split('M');
    arr.nose = parseInt(sigArr[0],10);
    sigArr = sigArr[1].split('C');
    arr.mouth = parseInt(sigArr[0],10);
    arr.clothing = parseInt(sigArr[1],10);

    return arr;
}



window.signatureToTemplateArray = function(signature){
    let arr = {};

    let sigArr = signature.replace('B', '');
    sigArr = sigArr.split('H');
    arr.body = parseInt(sigArr[0],10);
    sigArr = sigArr[1].split('F');
    arr.hair = parseInt(sigArr[0],10);
    sigArr = sigArr[1].split('E');
    arr.facialHair = (sigArr[0] == 'x') ? 0 : parseInt(sigArr[0],10);
    sigArr = sigArr[1].split('N');
    arr.eyes = parseInt(sigArr[0],10);
    sigArr = sigArr[1].split('M');
    arr.nose = parseInt(sigArr[0],10);
    sigArr = sigArr[1].split('C');
    arr.mouth = parseInt(sigArr[0],10);
    arr.clothing = parseInt(sigArr[1],10);

    return arr;
}
window.signatureToFlobotTemplateArray = function(signature){
    let arr = {};

    let sigArr = signature.replace('B', '');
    sigArr = sigArr.split('H');
    arr.body = parseInt(sigArr[0],10);
    sigArr = sigArr[1].split('A');
    arr.head = parseInt(sigArr[0],10);
    sigArr = sigArr[1].split('L');
    arr.arms = parseInt(sigArr[0],10);
    sigArr = sigArr[1].split('F');
    arr.legs = parseInt(sigArr[0],10);
    arr.face = parseInt(sigArr[1],10);

    return arr;
}


window.getRarityScoreBySignature = function(signature){
    //B90H25FxE50N3M5C71

    let sigArr = signatureToArrayScore(signature);

    let score = sigArr.body + sigArr.hair + sigArr.facialHair + sigArr.eyes + sigArr.nose + sigArr.mouth + sigArr.clothing;
    let min = (sigArr.facialHair == 0) ? 60 : 70;
    let max = (sigArr.facialHair == 0) ? 60000 : 70000;

    score = (max - score) * 100.0 / (max - min) ;

    return new Intl.NumberFormat('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2, fractionDigits: 2}).format(parseFloat(score));
}
window.getRarityScore = function(flovatar){
    if(!flovatar.metadata){
        return 0;
    }
    let legendaryCount = parseInt(flovatar.metadata.legendaryCount,10);
    let epicCount = parseInt(flovatar.metadata.epicCount,10);
    let rareCount = parseInt(flovatar.metadata.rareCount,10);

    return window.getRarityScoreFromCounts(legendaryCount, epicCount, rareCount);
}

window.getRarityScoreFromCounts = function(legendaryCount, epicCount, rareCount){
    let totalBoosters = legendaryCount + epicCount + rareCount;
    let totalCommon = 6 - totalBoosters;
    totalCommon = (totalCommon < 0) ? 0 : totalCommon;

    if(totalBoosters > 6){
        if(rareCount > 0) {
            rareCount = rareCount - 1;
        } else if(epicCount > 0) {
            epicCount = epicCount - 1;
        } else if(legendaryCount > 0) {
            legendaryCount = legendaryCount - 1;
        }
    }


    let score = (legendaryCount * 125) + (epicCount * 25) + (rareCount * 5) + totalCommon;
    let min = 6;
    let max = 6 * 125;

    score = (score - min) * 100.0 / (max - min) ;

    return new Intl.NumberFormat('en-US', {minimumFractionDigits: 1, maximumFractionDigits: 1, fractionDigits: 1}).format(parseFloat(score));
}


window.getCollectibleRarityScoreFromCounts = function(legendaryCount, epicCount, rareCount){
    let totalBoosters = legendaryCount + epicCount + rareCount;
    let totalCommon = 5 - totalBoosters;
    totalCommon = (totalCommon < 0) ? 0 : totalCommon;

    let score = (legendaryCount * 125) + (epicCount * 25) + (rareCount * 5) + totalCommon;
    let min = 5;
    let max = 5 * 125;

    score = (score - min) * 100.0 / (max - min) ;

    return new Intl.NumberFormat('en-US', {minimumFractionDigits: 1, maximumFractionDigits: 1, fractionDigits: 1}).format(parseFloat(score));
}


window.getFlovatarRarityText = function(flovatar){
    if(!flovatar.metadata){
        return '';
    }

    if(flovatar.metadata.legendaryCount > 0){
        return 'legendary';
    }
    if(flovatar.metadata.epicCount > 0){
        return 'epic';
    }
    if(flovatar.metadata.rareCount > 0){
        return 'rare';
    }
    return 'common';
}


window.getCollectibleRarityScore = function(collectible, templates){
    if(!collectible || !collectible.layers){
        return 0;
    }


    let legendaryCount = 0;
    let epicCount = 0;
    let rareCount = 0;

    _.each(collectible.layers, item => {
        let template = _.find(templates, temp => { return temp.flow_id == item; });
        if(template){
            if(template.rarity == 'legendary'){
                legendaryCount++;
            } else if(template.rarity == 'epic'){
                epicCount++;
            } else if(template.rarity == 'rare'){
                rareCount++;
            }
        }
    });

    return window.getCollectibleRarityScoreFromCounts(legendaryCount, epicCount, rareCount);
}

window.getCollectibleRarityScoreFromCounts = function(legendaryCount, epicCount, rareCount){
    let totalBoosters = legendaryCount + epicCount + rareCount;
    let totalCommon = 5 - totalBoosters;
    totalCommon = (totalCommon < 0) ? 0 : totalCommon;

    let score = (legendaryCount * 125) + (epicCount * 25) + (rareCount * 5) + totalCommon;
    let min = 5;
    let max = 5 * 125;

    score = (score - min) * 100.0 / (max - min) ;

    return new Intl.NumberFormat('en-US', {minimumFractionDigits: 1, maximumFractionDigits: 1, fractionDigits: 1}).format(parseFloat(score));
}
window.getCollectibleRarityText = function(collectible, templates){
    if(!collectible || !collectible.layers){
        return 'common';
    }

    let legendaryCount = 0;
    let epicCount = 0;
    let rareCount = 0;

    _.each(collectible.layers, item => {
        let template = _.find(templates, temp => { return temp.flow_id == item; });
        if(template){
            if(template.rarity == 'legendary'){
                legendaryCount++;
            } else if(template.rarity == 'epic'){
                epicCount++;
            } else if(template.rarity == 'rare'){
                rareCount++;
            }
        }
    });

    if(legendaryCount > 0){
        return 'legendary';
    }
    if(epicCount > 0){
        return 'epic';
    }
    if(rareCount > 0){
        return 'rare';
    }
    return 'common';
}



window.getFlovatarRarityImages = function(flovatar){
    if(!flovatar || !flovatar.metadata){
        return '';
    }
    return window.getFlovatarRarityImagesFromMeta(flovatar.metadata);

}
window.getFlovatarRarityImagesFromMeta = function(metadata){

    let out = '<div>';

    for(let i = 0; i < metadata.rareCount; i++){
        out += '<img src="/images/booster-rare.svg" />';
    }
    for(let i = 0; i < metadata.epicCount; i++){
        out += '<img src="/images/booster-epic.svg" />';
    }
    for(let i = 0; i < metadata.legendaryCount; i++){
        out += '<img src="/images/booster-legendary.svg" />';
    }

    out += '</div>';
    return out;
}


window.getTemplateScore = function(templateId){
    if(!window.templates){
        return 0;
    }
    let template = _.find(window.templates, function (x) { return x.flow_id == templateId });

    return (template) ? template.max_mintable : null;

}


window.writeHashParams = function(params){
    let hash = '';
    _.each(params, function(val, key){
        hash += key + '=' + val + '&';
    });
    hash = _.trim(hash,'&');
    hash = '#' + hash;
    if(window.location.hash != hash){
        //console.log('write hash params');
        //console.log(window.location.hash);
        //console.log(hash);
        window.history.pushState(params, null, hash);
    }
}



window.removeCookiesList = [
];

window.removeQueueCookies = function(){

    for (let cookieName of window.removeCookiesList) {
        Cookies.remove(cookieName);
    }
}

window.l = function(data){
    console.log(data);
}
