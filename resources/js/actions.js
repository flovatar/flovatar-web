
import {builderCheckoutTx} from './flow/builder-checkout.tx';
import {checkAvailabilityScript} from './flow/check-availability.script';
import {checkCollectibleAvailabilityScript} from './flow/check-collectible-availability.script';
import {checkFlobotAvailabilityScript} from './flow/check-flobot-availability.script';
import {componentSaleBuyTx} from './flow/component-sale-buy.tx';
import {componentSaleCancelTx} from './flow/component-sale-cancel.tx';
import {componentSaleCreateTx} from './flow/component-sale-create.tx';
import {componentSaleUpdateTx} from './flow/component-sale-update.tx';
import {componentSaleBuyDapperTx} from './flow/component-sale-buy-dapper.tx';
import {componentSaleCancelDapperTx} from './flow/component-sale-cancel-dapper.tx';
import {componentSaleCreateDapperTx} from './flow/component-sale-create-dapper.tx';
import {flovatarAddAccessoryTx} from './flow/flovatar-add-accessory.tx';
import {flovatarAddEyeglassesTx} from './flow/flovatar-add-eyeglasses.tx';
import {flovatarAddHatTx} from './flow/flovatar-add-hat.tx';
import {flovatarAddBackgroundTx} from './flow/flovatar-add-background.tx';
import {flovatarRemoveAccessoryTx} from './flow/flovatar-remove-accessory.tx';
import {flovatarRemoveEyeglassesTx} from './flow/flovatar-remove-eyeglasses.tx';
import {flovatarRemoveHatTx} from './flow/flovatar-remove-hat.tx';
import {flovatarRemoveBackgroundTx} from './flow/flovatar-remove-background.tx';
import {flovatarSetNameTx} from './flow/flovatar-set-name.tx';
import {flovatarSetPositionTx} from './flow/flovatar-set-position.tx';
import {flovatarUnlock3dTx} from './flow/flovatar-unlock-3d.tx';
import {flovatarAddStoryTx} from './flow/flovatar-add-story.tx';
import {flobotAddBackgroundTx} from './flow/flobot-add-background.tx';
import {flobotRemoveBackgroundTx} from './flow/flobot-remove-background.tx';
import {collectibleSetNameTx} from './flow/collectible-set-name.tx';
import {collectibleSetPositionTx} from './flow/collectible-set-position.tx';
import {collectibleAddStoryTx} from './flow/collectible-add-story.tx';
import {flovatarCreateTx} from './flow/flovatar-create.tx';
import {flobotCreateTx} from './flow/flobot-create.tx';
import {flovatarSaleBuyTx} from './flow/flovatar-sale-buy.tx';
import {flovatarSaleCancelTx} from './flow/flovatar-sale-cancel.tx';
import {flovatarSaleCreateTx} from './flow/flovatar-sale-create.tx';
import {flovatarSaleUpdateTx} from './flow/flovatar-sale-update.tx';
import {flovatarSaleBuyDapperTx} from './flow/flovatar-sale-buy-dapper.tx';
import {flovatarSaleCancelDapperTx} from './flow/flovatar-sale-cancel-dapper.tx';
import {flovatarSaleCreateDapperTx} from './flow/flovatar-sale-create-dapper.tx';
import {getClaimablesScript} from './flow/get-claimables.script';
import {getClaimablesByNameScript} from './flow/get-claimables-by-name.script';
import {getCollectionsScript} from './flow/get-collections.script';
import {getCollectionsByNameScript} from './flow/get-collections-by-name.script';
import {getCollectionsPackScript} from './flow/get-collections-pack.script';
import {getCollectionsByNamePackScript} from './flow/get-collections-by-name-pack.script';
import {getCollectionsFlovatarScript} from './flow/get-collections-flovatar.script';
import {getCollectionsFlobotScript} from './flow/get-collections-flobot.script';
import {getCollectionsCollectibleScript} from './flow/get-collections-collectible.script';
import {getCollectionsFlovatarIdsScript} from './flow/get-collections-flovatar-ids.script';
import {getCollectionsByNameFlovatarScript} from './flow/get-collections-by-name-flovatar.script';
import {getCollectionsByNameFlobotScript} from './flow/get-collections-by-name-flobot.script';
import {getCollectionsByNameCollectibleScript} from './flow/get-collections-by-name-collectible.script';
import {getCollectionsByNameFlovatarIdsScript} from './flow/get-collections-by-name-flovatar-ids.script';
import {getCollectionsFlovatarSaleScript} from './flow/get-collections-flovatar-sale.script';
import {getCollectionsByNameFlovatarSaleScript} from './flow/get-collections-by-name-flovatar-sale.script';
import {getCollectionsComponentScript} from './flow/get-collections-component.script';
import {getCollectionsByNameComponentScript} from './flow/get-collections-by-name-component.script';
import {getCollectionsComponentSaleScript} from './flow/get-collections-component-sale.script';
import {getCollectionsByNameComponentSaleScript} from './flow/get-collections-by-name-component-sale.script';
import {getComponentScript} from './flow/get-component.script';
import {getComponentSaleScript} from './flow/get-component-sale.script';
import {getComponentSalesScript} from './flow/get-component-sales.script';
import {getComponentsScript} from './flow/get-components.script';
import {getFlovatarScript} from './flow/get-flovatar.script';
import {getFlobotScript} from './flow/get-flobot.script';
import {getFlovatarSaleScript} from './flow/get-flovatar-sale.script';
import {getFlovatarSalesScript} from './flow/get-flovatar-sales.script';
import {getFlovatarsAndSalesScript} from './flow/get-flovatars-and-sales.script';
import {getFlovatarsScript} from './flow/get-flovatars.script';
import {getProfileScript} from './flow/get-profile.script';
import {getProfileDustScript} from './flow/get-profile-dust.script';
import {getTemplateScript} from './flow/get-template.script';
import {getTemplatesScript} from './flow/get-templates.script';
import {getDustAccessoryScript} from './flow/get-dust-accessory.script';
import {getDustAccessoriesScript} from './flow/get-dust-accessories.script';
import {getDustCollectibleScript} from './flow/get-dust-collectible.script';
import {initAccountTx} from './flow/init-account.tx';
import {initAccountDapperTx} from './flow/init-account-dapper.tx';
import {initAccountDustTx} from './flow/init-account-dust.tx';
import {initAccountDapperDustTx} from './flow/init-account-dapper-dust.tx';
import {isInitializedScript} from './flow/is-initialized.script';
import {isInitializedDapperScript} from './flow/is-initialized-dapper.script';
import {isInitializedDustScript} from './flow/is-initialized-dust.script';
import {isInitializedDapperDustScript} from './flow/is-initialized-dapper-dust.script';
import {initMetadataViewsTx} from './flow/init-metadata-views.tx';
import {isMetadataInitializedScript} from './flow/is-metadata-initialized.script';
import {packBuyTx} from './flow/pack-buy.tx';
import {packBuyDapperTx} from './flow/pack-buy-dapper.tx';
import {packBuyV2Tx} from './flow/pack-buy-v2.tx';
import {packBuyDapperV2Tx} from './flow/pack-buy-dapper-v2.tx';
import {packOpenTx} from './flow/pack-open.tx';
import {flovatarSendGiftTx} from './flow/flovatar-send-gift.tx';
import {flobotSendGiftTx} from './flow/flobot-send-gift.tx';
import {collectibleSendGiftTx} from './flow/collectible-send-gift.tx';
import {componentSendGiftTx} from './flow/component-send-gift.tx';
import {signDapperMessageTx} from './flow/sign-dapper-message.tx';
import {claimAllTx} from './flow/claim-all.tx';
import {claimDustTx} from './flow/claim-dust.tx';
import {claimDustByIdTx} from './flow/claim-dust-by-id.tx';
import {claimChildDustByIdTx} from './flow/claim-child-dust-by-id.tx';
import {claimFlobitsTx} from './flow/claim-flobits.tx';
import {collectibleCreateTx} from './flow/collectible-create.tx';
import {upgradeFlobitsTx} from './flow/upgrade-flobits.tx';
import {getFlovatarClaimablesScript} from './flow/get-flovatar-claimables.script';
import {claimFlovatarDustTx} from './flow/claim-flovatar-dust.tx';
import {claimFlovatarAirdropTx} from './flow/claim-flovatar-airdrop.tx';

import {collectibleSaleBuyTx} from './flow/collectible-sale-buy.tx';
import {collectibleSaleCancelTx} from './flow/collectible-sale-cancel.tx';
import {collectibleSaleCreateTx} from './flow/collectible-sale-create.tx';
import {collectibleSaleBuyDapperTx} from './flow/collectible-sale-buy-dapper.tx';
import {collectibleSaleCreateDapperTx} from './flow/collectible-sale-create-dapper.tx';

import {flobotSaleBuyTx} from './flow/flobot-sale-buy.tx';
import {flobotSaleCancelTx} from './flow/flobot-sale-cancel.tx';
import {flobotSaleCreateTx} from './flow/flobot-sale-create.tx';
import {flobotSaleBuyDapperTx} from './flow/flobot-sale-buy-dapper.tx';
import {flobotSaleCreateDapperTx} from './flow/flobot-sale-create-dapper.tx';

import {sendDustToAddressTx} from './flow/send-dust-to-address.tx';


import './classes/Helpers';

import { DateTime } from 'luxon'
import { toSvg } from "jdenticon"
import Cookies from 'js-cookie'


require('./bootstrap');

import Alpine from 'alpinejs';


import * as fcl from "@onflow/fcl"
import * as t from "@onflow/types"
import {config} from "@onflow/fcl";


window.fcl = fcl;
window.t = t;
window.user = null;
window.profile = null;
window.dispatch = null;
window.currentWallet = null;

/**
 * Which wallet is connected: 'dapper' or 'flow'. Looks at every FCL service (FCL >= 1.10 no longer guarantees
 * that services[0] is the authn service), the authn endpoint and the provider address.
 */
window.detectWallet = function(user){
    const services = (user && user.services) ? user.services : [];
    const isDapper = services.some(function(s){
        const uid = (s.uid || '').toLowerCase();
        const endpoint = (s.endpoint || '').toLowerCase();
        const provider = (s.provider && s.provider.address) ? s.provider.address.toLowerCase() : '';
        return uid.indexOf('dapper') >= 0 || endpoint.indexOf('meetdapper.com') >= 0
            || provider === '0xead892083b3e2c6c' || provider === '0x82ec283f88a62e65';
    });
    return isDapper ? 'dapper' : 'flow';
}

window.blacklistedAddresses = [
    '0x2b3733faa486deb3',
    '0x34f8fd310486de0f',
    '0xd10e75f35554d13a'
];


//--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------



window.initApp = function(dispatch){
    window.dispatch = dispatch;
    fcl.currentUser().subscribe(updateUser);
}

window.transError = function(transaction){
    if(transaction.errorMsg){
        return transaction.errorMsg;
    }
    if(transaction.errorMessage){
        return transaction.errorMessage;
    }
    return '';
}

async function updateUser(user){
    console.log('update user');
    console.log(user);
    window.user = user;

    if(user.addr) {
        // Decide the wallet here, before choosing which init check / transactions to use
        window.currentWallet = window.detectWallet(user);
        console.log('wallet: ' + window.currentWallet);

        let isBlacklisted = _.find(window.blacklistedAddresses, (item) => {
            return item == user.addr;
        });

        if(isBlacklisted){
            window.dispatch('user-blacklisted', {});
            window.logout();
        } else {
            jQuery('#moonpay-modal').removeClass('hidden');
            window.dispatch('user-loaded', {user: user});

            //TODO CADENCE 1.0 !!!
            console.log('run check is initialized script');
            //await isInitializedScript(user.addr).then(checkInitUser);

            if(window.currentWallet != 'dapper'){

                if(window.dustEnabled){
                    await isInitializedDustScript(user.addr).then(checkInitUser);
                } else {
                    await isInitializedScript(user.addr).then(checkInitUser);
                }
                //window.getMoonpaySignature(user.addr);
            } else {
                if(window.dustEnabled){
                    await isInitializedDapperDustScript(user.addr).then(checkInitUser);
                } else {
                    await isInitializedDapperScript(user.addr).then(checkInitUser);
                }
            }

        }
    } else {
        window.dispatch('user-check-finish', {user: user});
    }
}

async function checkInitUser(data){
    console.log('check init user');
    if(!data){
        window.dispatch('user-not-initialized');
        console.log('user not initialized');
    } else {
        //await isInitializedScript(user.addr).then(checkInitMetadataViews);

        console.log('user initialized');
        await refreshProfile();
    }
}

async function checkInitMetadataViews(data){
    if(!data){
        window.dispatch('metadata-not-initialized');
        console.log('metadata views not initialized');
    } else {
        await refreshProfile();
    }
}

window.readTransaction = function(transaction){
    console.log(transaction);
    try {
        if(transaction && transaction.events && transaction.events[0] && transaction.events[0].transactionId){
            //console.log(transaction.events[0].transactionId);
            window.dispatch('executing-transaction', {txId: transaction.events[0].transactionId});
        }
    } catch(error){
        console.log('error reading transaction ID');
        console.log(error);
    }
}


window.loadInitUser = async function(){
    try {
        window.dispatch('loading-start');
        let txId = null;
        if(window.currentWallet == 'dapper'){
            if(window.dustEnabled){
                //txId = await initAccountDapperTx();
                txId = await initAccountDapperDustTx();
            } else {
                txId = await initAccountDapperTx();
            }
        } else {
            if(window.dustEnabled){
                txId = await initAccountDustTx();
            } else {
                txId = await initAccountTx();
            }
        }
        //console.log('initializing account...');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            window.readTransaction(transaction);
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    window.dispatch('loading-completed');
                    //console.log(transaction);
                    window.dispatch('account-initialized', {account: window.user});
                    refreshProfile();
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.loadInitMetadataViews = async function(){
    try {
        window.dispatch('loading-start');
        let txId = await initMetadataViewsTx();
        console.log('initializing metadata views...');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            window.readTransaction(transaction);
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    window.dispatch('loading-completed');
                    //console.log(transaction);
                    window.dispatch('metadata-views-initialized', {account: data});
                    refreshProfile();
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.loadProfile = function(data){
    console.log('profile loaded');
    console.log(data);
    window.profile = data;
    window.dispatch('profile-loaded', {profile: data});
    return data;
}

window.refreshProfile = async function(){

    console.log('refresh profile');
    //TODO CADENCE 1.0 !!! -
    if(window.dustEnabled){
        await getProfileDustScript(window.user.addr).then(loadProfile);
    } else {
        await getProfileScript(window.user.addr).then(loadProfile);
    }
}

window.loadAddressProfile = function(data){
    //console.log('address profile loaded');
    //console.log(data);
    window.dispatch('address-profile-loaded', {profile: data});
    return data;
}

window.getAddressProfile = async function(address){
    if(window.dustEnabled){
        await getProfileDustScript(address).then(loadAddressProfile);
    } else {
        await getProfileScript(address).then(loadAddressProfile);
    }
}

window.loadAddressOwnerProfile = function(data){
    //console.log('address profile owner loaded');
    //console.log(data);
    window.dispatch('address-profile-owner-loaded', {profile: data});
    return data;
}

window.getAddressOwnerProfile = async function(address){
    if(window.dustEnabled){
        await getProfileDustScript(address).then(loadAddressOwnerProfile);
    } else {
        await getProfileScript(address).then(loadAddressOwnerProfile);
    }
}

window.addressFound = function(data){
    //console.log('address found');
    //console.log(data);
    window.dispatch('address-found', {profile: data});
    return data;
}

window.lookupAddress = async function(address){
    if(window.dustEnabled){
        await getProfileDustScript(window.user.addr).then(addressFound);
    } else {
        await getProfileScript(window.user.addr).then(addressFound);
    }
}


window.findAccountProof = function(user){
    if(!user || !user.services){
        return null;
    }
    let service = user.services.find(function(item){ return item.type === 'account-proof'; });
    return (service && service.data && service.data.nonce && service.data.signatures) ? service.data : null;
}

window.walletLogin = function(){
    window.createSignature(window.loginMessage);
}

window.createSignature = async function(message){
    console.log('signature create start');
    window.dispatch('loading-start');

    try{
        const user = await fcl.currentUser().snapshot();
        const proof = window.findAccountProof(user);

        if(proof && window.accountProofNonce && proof.nonce === window.accountProofNonce){
            // Account-proof returned by the wallet during authentication (Dapper Wallet and others)
            window.dispatch('loading-completed');
            window.sendAccountProofLogin(proof);
        } else if(window.currentWallet == 'dapper' || proof){
            // Missing or stale account-proof: re-authenticate to sign the current nonce
            const freshUser = await fcl.reauthenticate();
            const freshProof = window.findAccountProof(freshUser);
            window.dispatch('loading-completed');
            if(freshProof){
                window.sendAccountProofLogin(freshProof);
            } else {
                window.dispatch('login-failed');
            }
        } else {
            const MSG = Buffer.from(message || window.loginMessage || '').toString("hex");
            await fcl.currentUser().signUserMessage(MSG).then(function(data){
                console.log('signature created');
                window.dispatch('signature-created', {signature: data});
                window.dispatch('loading-completed');
                window.sendLogin(MSG, data);
            });
        }
    } catch(error){
        window.dispatch('transaction-error', {message: error});
        console.log('error');
        console.log(error);
    }
}

window.verifySignature = async function(message, signature){
    try{
        await fcl.verifyUserSignatures(message, signature).then(function(data){
            console.log('signature verified');
            //console.log(data);
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.sendSubscription = function(email) {
    if(email) {
        console.log('send subscription');
        axios.post('/subscribe', {
            email: email
        })
            .then(function (response) {
                window.dispatch('subscribed-email');
                //console.log(response);
            })
            .catch(function (error) {
                console.log(error);
            });
    }
}



window.getMoonpaySignature = function(address) {
    if(address) {
        //console.log('request moonpay signature');
        axios.post('/moonpay/sign', {
            address: address
        })
            .then(function (response) {
                window.dispatch('moonpay-signed', {signature: response.data});
                //console.log(response);
            })
            .catch(function (error) {
                console.log(error);
            });
    }
}

window.getSportvatarCodes = function() {
    axios.post('/sportpacks', { })
        .then(function (response) {
            window.dispatch('sportack-loaded', {packs: response.data});
            //console.log(response);
        })
        .catch(function (error) {
            console.log(error);
        });
}

window.handleLoginResponse = function(response){
    if(response.data && response.data.result && response.data.result === true){
        window.dispatch('logged-in');
        window.location.reload(true);
    } else {
        window.loadLoginNonce();
        window.dispatch('login-failed');
    }
}

window.sendLogin = function(msg, compSigs) {

    console.log('send login pre');
    let signatures = (compSigs || []).map(function(compSig){
        return {keyId: compSig.keyId, signature: compSig.signature};
    });

    if(window.user && window.user.addr && signatures.length > 0) {
        console.log('send login ok');
        axios.post('/login', {
            message: msg,
            address: window.user.addr,
            signatures: signatures
        })
            .then(window.handleLoginResponse)
            .catch(function (error) {
                console.log(error);
                window.dispatch('login-failed');
            });
    }
}

window.sendAccountProofLogin = function(proof) {

    axios.post('/login', {
        proofType: 'account-proof',
        address: proof.address,
        nonce: proof.nonce,
        signatures: proof.signatures.map(function(compSig){
            return {keyId: compSig.keyId, signature: compSig.signature};
        })
    })
        .then(window.handleLoginResponse)
        .catch(function (error) {
            console.log(error);
            window.dispatch('login-failed');
        });
}

window.getClaimables = async function(address){
    console.log('claimables started');
    await getClaimablesScript(address).then(function(data){
        console.log('claimables loaded');
        //console.log(data);
        window.dispatch('collections-claimables-loaded', {claimables: data});
    });
}
window.getClaimablesByName = async function(addressName){
    console.log('claimables started');
    await getClaimablesByNameScript(addressName).then(function(data){
        console.log('claimables loaded');
        //console.log(data);
        window.dispatch('collections-claimables-loaded', {claimables: data});
    });
}

window.getFlovatarClaimables = async function(address, id){
    console.log('flovatar claimables loading');
    await getFlovatarClaimablesScript(address, id).then(function(data){
        console.log('flovatar claimables loaded');
        //console.log(data);
        window.dispatch('flovatar-claimables-loaded', {claimables: data});
    });
}


window.getCollections = async function(address){
    await getCollectionsScript(address).then(function(data){
        console.log('collections loaded');
        //console.log(data);
        window.dispatch('collections-loaded', {collections: data});
    });
}
window.getCollectionsByName = async function(addressName){
    await getCollectionsByNameScript(addressName).then(function(data){
        console.log('collections loaded');
        //console.log(data);
        window.dispatch('collections-loaded', {collections: data});
    });
}
window.getCollectionsPack = async function(address){
    await getCollectionsPackScript(address).then(function(data){
        console.log('collections pack loaded');
        //console.log(data);
        window.dispatch('collections-pack-loaded', {collections: data});
    });
}
window.getCollectionsByNamePack = async function(addressName){
    await getCollectionsByNamePackScript(addressName).then(function(data){
        console.log('collections pack loaded');
        //console.log(data);
        window.dispatch('collections-pack-loaded', {collections: data});
    });
}

window.getCollectionsFlovatar = async function(address){
    await getCollectionsFlovatarScript(address).then(function(data){
        console.log('collections flovatar loaded');
        //console.log(data);
        window.dispatch('collections-flovatar-loaded', {collections: data});
    })
    .catch(function (error) {
        //console.log(error);
        console.log('failed to load Flovatar collection');
        window.dispatch('collections-flovatar-failed', {});
    });
}

window.getCollectionsFlobot = async function(address){
    await getCollectionsFlobotScript(address).then(function(data){
        console.log('collections flobot loaded');
        //console.log(data);
        window.dispatch('collections-flobot-loaded', {collections: data});
    })
        .catch(function (error) {
            //console.log(error);
            console.log('failed to load Flobot collection');
            window.dispatch('collections-flobot-failed', {});
        });
}

window.getCollectionsCollectible = async function(address){
    await getCollectionsCollectibleScript(address).then(function(data){
        console.log('collections collectible loaded');
        //console.log(data);
        window.dispatch('collections-collectible-loaded', {collections: data});
    })
        .catch(function (error) {
            //console.log(error);
            console.log('failed to load Collectible collection');
            window.dispatch('collections-collectible-failed', {});
        });
}


window.getCollectionsFlovatarIds = async function(address){
    await getCollectionsFlovatarIdsScript(address).then(function(data){
        console.log('collections flovatar ids loaded');
        //console.log(data);
        window.dispatch('collections-flovatar-ids-loaded', {collections: data});
    });
}

window.getCollectionsByNameFlovatar = async function(addressName){
    await getCollectionsByNameFlovatarScript(addressName).then(function(data){
        console.log('collections flovatar loaded');
        //console.log(data);
        window.dispatch('collections-flovatar-loaded', {collections: data});
    })
    .catch(function (error) {
        //console.log(error);
        console.log('failed to load Flovatar collection');
        window.dispatch('collections-flovatar-failed', {});
    });
}

window.getCollectionsByNameFlobot = async function(addressName){
    await getCollectionsByNameFlobotScript(addressName).then(function(data){
        console.log('collections flobot loaded');
        //console.log(data);
        window.dispatch('collections-flobot-loaded', {collections: data});
    })
        .catch(function (error) {
            //console.log(error);
            console.log('failed to load Flobot collection');
            window.dispatch('collections-flobot-failed', {});
        });
}

window.getCollectionsByNameCollectible = async function(addressName){
    await getCollectionsByNameCollectibleScript(addressName).then(function(data){
        console.log('collections collectible loaded');
        //console.log(data);
        window.dispatch('collections-collectible-loaded', {collections: data});
    })
        .catch(function (error) {
            //console.log(error);
            console.log('failed to load Collectible collection');
            window.dispatch('collections-collectible-failed', {});
        });
}

window.getCollectionsByNameFlovatarIds = async function(addressName){
    await getCollectionsByNameFlovatarIdsScript(addressName).then(function(data){
        console.log('collections flovatar ids loaded');
        //console.log(data);
        window.dispatch('collections-flovatar-ids-loaded', {collections: data});
    });
}

window.getCollectionsFlovatarSale = async function(address){
    await getCollectionsFlovatarSaleScript(address).then(function(data){
        console.log('collections flovatar sale loaded');
        //console.log(data);
        window.dispatch('collections-flovatar-sale-loaded', {collections: data});
    });
}
window.getCollectionsByNameFlovatarSale = async function(addressName){
    await getCollectionsByNameFlovatarSaleScript(addressName).then(function(data){
        console.log('collections flovatar sale loaded');
        //console.log(data);
        window.dispatch('collections-flovatar-sale-loaded', {collections: data});
    });
}

window.getCollectionsComponent = async function(address){
    await getCollectionsComponentScript(address).then(function(data){
        console.log('collections component loaded');
        //console.log(data);
        window.dispatch('collections-component-loaded', {collections: data});
    });
}
window.getCollectionsByNameComponent = async function(addressName){
    await getCollectionsByNameComponentScript(addressName).then(function(data){
        console.log('collections component loaded');
        //console.log(data);
        window.dispatch('collections-component-loaded', {collections: data});
    });
}
window.getCollectionsComponentSale = async function(address){
    await getCollectionsComponentSaleScript(address).then(function(data){
        console.log('collections component sale loaded');
        //console.log(data);
        window.dispatch('collections-component-sale-loaded', {collections: data});
    });
}
window.getCollectionsByNameComponentSale = async function(addressName){
    await getCollectionsByNameComponentSaleScript(addressName).then(function(data){
        console.log('collections component sale loaded');
        //console.log(data);
        window.dispatch('collections-component-sale-loaded', {collections: data});
    });
}


window.getComponent = async function(address, componentId){
    await getComponentScript(address, componentId).then(function(data){
        console.log('component loaded');
        //console.log(data);
        window.dispatch('fcomponent-loaded', {component: data});
    });
}
window.getComponents = async function(address){
    await getComponentsScript(address).then(function(data){
        console.log('components loaded');
        //console.log(data);
        window.dispatch('fcomponents-loaded', {components: data});
    });
}
window.getComponentSale = async function(address, componentId){
    await getComponentSaleScript(address, componentId).then(function(data){
        console.log('component sale loaded');
        //console.log(data);
        window.dispatch('fcomponent-sale-loaded', {sale: data});
    });
}
window.getComponentSales = async function(address){
    await getComponentSalesScript(address).then(function(data){
        console.log('component sales loaded');
        //console.log(data);
        window.dispatch('fcomponent-sales-loaded', {sales: data});
    });
}

window.getDustAccessory = async function(address, accessoryId){
    await getDustAccessoryScript(address, accessoryId).then(function(data){
        console.log('dust accessory loaded');
        //console.log(data);
        window.dispatch('faccessory-loaded', {accessory: data});
    });
}
window.getDustAccessories = async function(address){
    await getDustAccessoriesScript(address).then(function(data){
        console.log('dust accessories loaded');
        //console.log(data);
        window.dispatch('faccessories-loaded', {accessories: data});
    });
}
window.getDustCollectible = async function(address, collectibleId){
    console.log('get dust collectible');
    await getDustCollectibleScript(address, collectibleId).then(function(data){
        console.log('dust collectible loaded');
        //console.log(data);
        window.dispatch('fcollectible-loaded', {collectible: data});
    });
}

window.openPack = async function(packId){
    console.log('opening pack');
    window.dispatch('loading-start');
    try{
        let txId = await packOpenTx(packId);
        console.log('pack opened');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            window.dispatch('pack-opening-started');
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('pack opened sealed');
                    //console.log(transaction);
                    window.dispatch('waiting-seal');
                    window.dispatch('pack-opened');
                    window.dispatch('loading-completed');
                    window.getCollectionsComponent(window.user.addr);

                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}



window.reservePack = function(packType, address){

    console.log('reserve pack');
    console.log(packType);
    axios.post('/pack/reserve', {
        type: packType,
        wallet: window.currentWallet
    }).then(function (response) {
            //console.log(response);
            if(response.data && response.data.packId && response.data.signature){
                window.dispatch('pack-reserved');
                window.dispatch('loading-start');
                let price = (packType == 'deluxe') ? window.packPrice2 : window.packPrice;
                price = (packType == 'flobit') ? 39.0 : price;
                price = (packType == 'terrakit') ? 49.0 : price;
                price = (packType == 'dustpack') ? 3000.0 : price;
                // Packs v2: the authorisation also carries an expiry (verifier inside FlovatarPack)
                window.buyPack(response.data.packId, price, response.data.signature, response.data.version == 2 ? response.data.expiry : null)
            } else {
                window.dispatch('reserve-failed');
                console.log(response);
            }
        })
        .catch(function (error) {
            console.log(error);
            window.dispatch('reserve-failed');
            if(error.response && error.response.status == 401){
                // Reservations require a verified wallet login
                window.walletLogin();
            }
        });
}


window.preReservePack = function(packType){

    console.log('reserve pack');
    console.log(packType);
    axios.post('/pack/reserve', {
        type: packType,
        wallet: window.currentWallet
    }).then(function (response) {
        //console.log(response);
        if(response.data && response.data.packId && response.data.signature){
            let price = (packType == 'deluxe') ? window.packPrice2 : window.packPrice;
            price = (packType == 'flobit') ? 39.0 : price;
            price = (packType == 'terrakit') ? 49.0 : price;
            window.dispatch('pack-pre-reserved', {signature: response.data.signature, id: response.data.packId, price: price, expiry: response.data.version == 2 ? response.data.expiry : null});
        } else {
            window.dispatch('pre-reserve-failed');
            console.log(response);
        }
    })
        .catch(function (error) {
            console.log(error);
            window.dispatch('pre-reserve-failed');
        });
}


window.buyPack = async function(packId, price, signature, expiry = null){
    //saleAddress, tokenId, amount, secret
    console.log('purchasing pack');
    window.dispatch('loading-start');
    try{
        let txId = null;
        if(expiry) {
            // Packs v2 (FlovatarPack.purchasePack): authorisation bound to this wallet, with expiry
            txId = (window.currentWallet == 'dapper')
                ? await packBuyDapperV2Tx(packId, price, expiry, signature)
                : await packBuyV2Tx(packId, price, expiry, signature);
        } else if(window.currentWallet == 'dapper') {
            txId = await packBuyDapperTx(process.env.MIX_DAPPER_MERCHANT, packId, price, signature, process.env.MIX_CONTRACT_FLOW);
        } else {
            txId = await packBuyTx(process.env.MIX_CONTRACT_FLOW, packId, price, signature);
        }

        console.log('pack purchased');
        window.dispatch('waiting-seal');
        window.dispatch('pack-sealing');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            //console.log(transaction);
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('pack purchased sealed');
                    //console.log(transaction);
                    window.dispatch('pack-purchased');
                    window.dispatch('loading-completed');
                    //window.getCollections(window.user.addr);

                    if(false && window.vipCookieVal != 'hdfh55yyerg5g34t34t344'){
                        axios.post('/pack/purchased', {}).then(() => {
                            window.removeQueueCookies();
                        });
                    }
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

//

window.claimAll = async function(){
    //saleAddress, tokenId, amount, secret
    console.log('claim all inbox content');
    window.dispatch('loading-start');
    try{
        let txId = await claimAllTx();

        console.log('claiming all inbox content');
        window.dispatch('waiting-seal');
        window.dispatch('inbox-claiming');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            //console.log(transaction);
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('inbox claiming sealed');
                    //console.log(transaction);
                    window.dispatch('inbox-claimed');
                    window.dispatch('loading-completed');
                    //window.getCollections(window.user.addr);

                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.claimFlobits = async function(){
    //saleAddress, tokenId, amount, secret
    console.log('claim all flobit inbox content');
    window.dispatch('loading-start');
    try{
        let txId = await claimFlobitsTx();

        console.log('claiming all inbox content');
        window.dispatch('waiting-seal');
        window.dispatch('inbox-flobit-claiming');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            //console.log(transaction);
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('inbox flobit claiming sealed');
                    //console.log(transaction);
                    window.dispatch('inbox-flobit-claimed');
                    window.dispatch('loading-completed');
                    //window.getCollections(window.user.addr);
                    window.getCollectionsComponent(window.user.addr);

                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.claimAllDust = async function(){
    //saleAddress, tokenId, amount, secret
    console.log('claim all dust inbox content');
    window.dispatch('loading-start');
    try{
        let txId = await claimDustTx();

        console.log('claiming all inbox content');
        window.dispatch('waiting-seal');
        window.dispatch('inbox-dust-claiming');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            //console.log(transaction);
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('inbox dust claiming sealed');
                    //console.log(transaction);
                    window.dispatch('inbox-dust-claimed');
                    window.dispatch('loading-completed');
                    //window.getCollections(window.user.addr);

                    window.getClaimables(window.user.addr);
                    window.refreshProfile();

                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.claimAllDustByIds = async function(ids){
    //saleAddress, tokenId, amount, secret
    console.log('claim all dust inbox content by ids');
    window.dispatch('loading-start');
    try{
        let txId = await claimDustByIdTx(ids);

        console.log('claiming all inbox content by ids');
        window.dispatch('waiting-seal');
        window.dispatch('inbox-dust-claiming');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            //console.log(transaction);
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('inbox dust claiming sealed by ids');
                    //console.log(transaction);
                    window.dispatch('inbox-dust-claimed');
                    window.dispatch('loading-completed');
                    //window.getCollections(window.user.addr);

                    window.getClaimables(window.user.addr);
                    window.refreshProfile();

                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.claimAllChildDustByIds = async function(ids, addresses){
    //saleAddress, tokenId, amount, secret
    console.log('claim all dust inbox content by ids');
    window.dispatch('loading-start');
    try{
        let txId = await claimChildDustByIdTx(ids, addresses);

        console.log('claiming all inbox content by ids');
        window.dispatch('waiting-seal');
        window.dispatch('inbox-dust-claiming');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            //console.log(transaction);
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('inbox dust claiming sealed by ids');
                    //console.log(transaction);
                    window.dispatch('inbox-dust-claimed');
                    window.dispatch('loading-completed');
                    //window.getCollections(window.user.addr);

                    window.getClaimables(window.user.addr);
                    window.refreshProfile();

                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.claimAllInbox = async function(){
    //saleAddress, tokenId, amount, secret
    console.log('claim all inbox content');
    window.dispatch('loading-start');
    try{
        let txId = await claimAllTx();

        console.log('claiming all inbox content');
        window.dispatch('waiting-seal');
        window.dispatch('inbox-claiming');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            //console.log(transaction);
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('inbox claiming sealed');
                    //console.log(transaction);
                    window.dispatch('inbox-claimed');
                    window.dispatch('loading-completed');
                    //window.getCollections(window.user.addr);
                    window.getClaimables(window.user.addr);
                    window.refreshProfile();

                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.sendDustToAddress = async function(amount, address){
    //saleAddress, tokenId, amount, secret
    console.log('send dust to address');
    window.dispatch('loading-start');
    try{
        let txId = await sendDustToAddressTx(amount, address);

        console.log('sending dust to address');
        window.dispatch('waiting-seal');
        window.dispatch('dust-sending');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            //console.log(transaction);
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('dust sending sealed');
                    //console.log(transaction);
                    window.dispatch('dust-sent');
                    window.dispatch('loading-completed');

                    window.refreshProfile();

                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}



window.claimFlovatarDust = async function(id){
    //saleAddress, tokenId, amount, secret
    console.log('claim all dust inbox content');
    window.dispatch('loading-start');
    try{
        let txId = await claimFlovatarDustTx(id);

        console.log('claiming flovatar dust');
        window.dispatch('waiting-seal');
        window.dispatch('flovatar-dust-claiming');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            //console.log(transaction);
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar dust claiming sealed');
                    //console.log(transaction);
                    window.dispatch('flovatar-dust-claimed');
                    window.dispatch('loading-completed');
                    //window.getCollections(window.user.addr);
                    window.getClaimables(window.user.addr);
                    window.refreshProfile();

                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.claimFlovatarAirdrop = async function(id){
    //saleAddress, tokenId, amount, secret
    console.log('claim all dust inbox content');
    window.dispatch('loading-start');
    try{
        let txId = await claimFlovatarAirdropTx(id);

        console.log('claiming flovatar airdrop');
        window.dispatch('waiting-seal');
        window.dispatch('flovatar-airdrop-claiming');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            //console.log(transaction);
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar airdrop claiming sealed');
                    //console.log(transaction);
                    window.dispatch('flovatar-airdrop-claimed');
                    window.dispatch('loading-completed');
                    window.getClaimables(window.user.addr);
                    //window.refreshProfile();

                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}



window.createFlovatar = async function(spark, body, hair, facialHair, eyes, nose, mouth, clothing, accessory, hat, eyeglasses, background, rareBoost, epicBoost, legendaryBoost){
    console.log('creating flovatar');
    //console.log(spark+' - '+body+' - '+hair+' - '+facialHair+' - '+eyes+' - '+nose+' - '+mouth+' - '+clothing+' - '+accessory+' - '+hat+' - '+eyeglasses);
    //console.log(facialHair);
    //console.log(rareBoost);
    //console.log(epicBoost);
    //console.log(legendaryBoost);
    facialHair = facialHair ? facialHair : null;
    window.dispatch('loading-start');
    try{
        let txId = await flovatarCreateTx(spark, body, hair, facialHair, eyes, nose, mouth, clothing,  accessory, hat, eyeglasses, background, rareBoost, epicBoost, legendaryBoost);
        console.log('flovatar created');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            //console.log('flovatar created callback');
            //console.log(transaction);
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    window.dispatch('flovatar-created');
                    console.log('flovatar created sealed');
                    window.dispatch('waiting-seal');
                    //alert('Your Flovatar has been created! Go check it out in your collection.');
                    window.dispatch('loading-completed');
                    window.getCollections(window.user.addr);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.createFlobot = async function(flobotkit, body, head, arms, legs, face, background){
    console.log('creating flobot');
    //console.log(spark+' - '+body+' - '+hair+' - '+facialHair+' - '+eyes+' - '+nose+' - '+mouth+' - '+clothing+' - '+accessory+' - '+hat+' - '+eyeglasses);
    //console.log(facialHair);
    //console.log(rareBoost);
    //console.log(epicBoost);
    //console.log(legendaryBoost);
    window.dispatch('loading-start');
    try{
        let txId = await flobotCreateTx(flobotkit, body, head, arms, legs, face, background);
        console.log('flobot created');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            //console.log('flovatar created callback');
            //console.log(transaction);
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    window.dispatch('flobot-created');
                    console.log('flobot created sealed');
                    window.dispatch('waiting-seal');
                    //alert('Your Flovatar has been created! Go check it out in your collection.');
                    window.dispatch('loading-completed');
                    window.getCollections(window.user.addr);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.createCollectible = async function(series, layers, amount){
    console.log('creating collectible');
    l(layers);
    let layersVal = _.map(Object.values(layers), item => {
        return item ? ''+item : null;
    });
    l(Object.keys(layers));
    l(layersVal);

    window.dispatch('loading-start');
    try{
        let txId = await collectibleCreateTx(series, Object.keys(layers), layersVal, window.floatArgFull(amount));
        console.log('collectible created');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            //console.log('flovatar created callback');
            //console.log(transaction);
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    window.dispatch('collectible-created');
                    console.log('collectible created sealed');
                    window.dispatch('waiting-seal');
                    //alert('Your Flovatar has been created! Go check it out in your collection.');
                    window.dispatch('loading-completed');
                    window.getCollections(window.user.addr);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.checkAvailability = async function(body, hair, facialHair, eyes, nose, mouth, clothing){
    //console.log('start check availability');
    //console.log(body+' - '+hair+' - '+facialHair+' - '+eyes+' - '+nose+' - '+mouth+' - '+clothing);
    await checkAvailabilityScript(body, hair, facialHair ? facialHair : null, eyes, nose, mouth, clothing).then(function(data){
        console.log('availability checked');
        //console.log(data);
        window.dispatch('availability-checked', {result: data});
    });
}

window.checkFlobotAvailability = async function(body, head, arms, legs, face){
    //console.log('start check availability');
    await checkFlobotAvailabilityScript(body, head, arms, legs, face).then(function(data){
        console.log('availability checked');
        console.log(data);
        window.dispatch('availability-checked', {result: data});
    });
}

window.checkCollectibleAvailability = async function(series, layers){
    console.log('start check collectible availability');
    //console.log(Object.keys(layers));
    let layersVal = _.map(Object.values(layers), item => {
        return item ? ''+item : null;
    });
    //console.log(layersVal);
    await checkCollectibleAvailabilityScript(series, Object.keys(layers), layersVal).then(function(data){
        console.log('availability checked');
        //console.log(data);
        window.dispatch('availability-checked', {result: data});
    });
}



window.builderCheckout = async function(saleAddresses, tokenIds, amounts){
    console.log('builder checkout start');
    window.dispatch('loading-start');
    try{
        let txId = await builderCheckoutTx(saleAddresses, tokenIds, amounts);
        console.log('builder-checkout finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            console.log(transaction);
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('builder checkout sealed');
                    //console.log(transaction);
                    window.dispatch('builder-checkout-completed');
                    window.dispatch('loading-completed');
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.buyComponentSale = async function(saleAddress, tokenId, amount, saleId){
    console.log('component purchase start');
    window.dispatch('loading-start');

    try{
        let txId = null;
        if(window.currentWallet == 'dapper') {
            txId = await componentSaleBuyDapperTx(saleAddress, saleId, amount, process.env.MIX_CONTRACT_FLOW);
        } else {
            txId = await componentSaleBuyTx(saleAddress, tokenId, amount);
        }

        console.log('component purchase finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('component purchase sealed');
                    //console.log(transaction);
                    window.dispatch('fcomponent-sale-purchased');
                    window.dispatch('loading-completed');
                    window.location = '/components/'+tokenId+'/'+window.user.addr;
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.cancelComponentSale = async function(componentId, saleId){
    console.log('component sale cancel start');
    window.dispatch('loading-start');
    try{
        let txId = null;
        if(window.currentWallet == 'dapper') {
            txId = await componentSaleCancelDapperTx(saleId);
        } else {
            txId = await componentSaleCancelTx(componentId);
        }
        console.log('component sale cancel finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('component sale cancel sealed');
                    //console.log(transaction);
                    window.dispatch('fcomponent-sale-cancelled');
                    window.dispatch('loading-completed');
                    window.getComponent(window.user.addr, componentId);
                    window.getComponentSale(window.user.addr, componentId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.createComponentSale = async function(componentId, price){
    console.log('component sale create start');
    window.dispatch('loading-start');
    try{
        let txId = null;
        if(window.currentWallet == 'dapper') {
            let expiry = Math.floor(Date.now()) + (60 * 60 * 24 * 365); //expires in one year
            txId = await componentSaleCreateDapperTx(componentId, price, 'Flovatar', expiry);
        } else {
            txId = await componentSaleCreateTx(componentId, price);
        }
        console.log('component sale create finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('component sale create sealed');
                    //console.log(transaction);
                    window.dispatch('fcomponent-sale-created');
                    window.dispatch('loading-completed');
                    window.getComponent(window.user.addr, componentId);
                    window.getComponentSale(window.user.addr, componentId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.updateComponentSale = async function(componentId, price){
    console.log('component sale update start');
    window.dispatch('loading-start');
    try{
        let txId = await componentSaleUpdateTx(componentId, price);
        console.log('component sale update finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('component sale update sealed');
                    //console.log(transaction);
                    window.dispatch('fcomponent-sale-updated');
                    window.dispatch('loading-completed');
                    window.getComponent(window.user.addr, componentId);
                    window.getComponentSale(window.user.addr, componentId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}



window.componentSendGift = async function(componentId, address){
    console.log('component send gift start');
    window.dispatch('loading-start');
    try{
        let txId = await componentSendGiftTx(componentId, address);
        console.log('component send gift finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('component gift sent');
                    //console.log(transaction);
                    window.dispatch('component-gift-sent');
                    window.dispatch('loading-completed');
                    window.location = '/components/'+componentId+'/'+address;
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}





window.buyCollectibleSale = async function(saleAddress, tokenId, amount, saleId){
    console.log('collectible purchase start');
    window.dispatch('loading-start');

    try{
        let txId = null;
        if(window.currentWallet == 'dapper') {
            txId = await collectibleSaleBuyDapperTx(saleAddress, saleId, amount, process.env.MIX_CONTRACT_FLOW);
        } else {
            txId = await collectibleSaleBuyTx(saleAddress, saleId, amount, process.env.MIX_CONTRACT_FLOW);
        }

        console.log('collectible purchase finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('collectible purchase sealed');
                    //console.log(transaction);
                    window.dispatch('fcollectible-sale-purchased');
                    window.dispatch('loading-completed');
                    window.location = '/collectibles/'+tokenId+'/'+window.user.addr;
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.cancelCollectibleSale = async function(collectibleId, saleId){
    console.log('collectible sale cancel start');
    window.dispatch('loading-start');
    try{
        let txId = await collectibleSaleCancelTx(saleId);

        console.log('collectible sale cancel finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('collectible sale cancel sealed');
                    //console.log(transaction);
                    window.dispatch('fcollectible-sale-cancelled');
                    window.dispatch('loading-completed');
                    window.getDustCollectible(window.user.addr, collectibleId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.createCollectibleSale = async function(collectibleId, price){
    console.log('collectible sale create start');
    window.dispatch('loading-start');
    try{
        let txId = null;
        let expiry = Math.floor(Date.now()) + (60 * 60 * 24 * 365); //expires in one year
        if(window.currentWallet == 'dapper') {
            txId = await collectibleSaleCreateDapperTx(collectibleId, price, 'Flovatar', expiry);
        } else {
            txId = await collectibleSaleCreateTx(collectibleId, price, 'Flovatar', expiry);
        }
        console.log('collectible sale create finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('collectible sale create sealed');
                    //console.log(transaction);
                    window.dispatch('fcollectible-sale-created');
                    window.dispatch('loading-completed');
                    window.getDustCollectible(window.user.addr, collectibleId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}



window.buyFlobotSale = async function(saleAddress, tokenId, amount, saleId){
    console.log('flobot purchase start');
    window.dispatch('loading-start');

    try{
        let txId = null;
        if(window.currentWallet == 'dapper') {
            txId = await flobotSaleBuyDapperTx(saleAddress, saleId, amount, process.env.MIX_CONTRACT_FLOW);
        } else {
            txId = await flobotSaleBuyTx(saleAddress, saleId, amount, process.env.MIX_CONTRACT_FLOW);
        }

        console.log('flobot purchase finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flobot purchase sealed');
                    //console.log(transaction);
                    window.dispatch('flobot-sale-purchased');
                    window.dispatch('loading-completed');
                    window.location = '/flobots/'+tokenId+'/'+window.user.addr;
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.cancelFlobotSale = async function(flobotId, saleId){
    console.log('flobot sale cancel start');
    window.dispatch('loading-start');
    try{
        let txId = await flobotSaleCancelTx(saleId);

        console.log('flobot sale cancel finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flobot sale cancel sealed');
                    //console.log(transaction);
                    window.dispatch('flobot-sale-cancelled');
                    window.dispatch('loading-completed');
                    window.getFlobot(window.user.addr, flobotId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.createFlobotSale = async function(flobotId, price){
    console.log('flobot sale create start');
    window.dispatch('loading-start');
    try{
        let txId = null;
        let expiry = Math.floor(Date.now()) + (60 * 60 * 24 * 365); //expires in one year
        if(window.currentWallet == 'dapper') {
            txId = await flobotSaleCreateDapperTx(flobotId, price, 'Flovatar', expiry);
        } else {
            txId = await flobotSaleCreateTx(flobotId, price, 'Flovatar', expiry);
        }
        console.log('flobot sale create finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flobot sale create sealed');
                    //console.log(transaction);
                    window.dispatch('flobot-sale-created');
                    window.dispatch('loading-completed');
                    window.getFlobot(window.user.addr, flobotId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}







window.flovatarAddOptional = function(flovatarId, val, category) {
    if(category == 'accessory'){
        window.flovatarAddAccessory(flovatarId, val);
    } else if(category == 'hat'){
        window.flovatarAddHat(flovatarId, val);
    } else if(category == 'eyeglasses'){
        window.flovatarAddEyeglasses(flovatarId, val);
    } else if(category == 'background'){
        window.flovatarAddBackground(flovatarId, val);
    }
}

window.flovatarRemoveOptional = function(flovatarId, category) {
    if(category == 'accessory'){
        window.flovatarRemoveAccessory(flovatarId);
    } else if(category == 'hat'){
        window.flovatarRemoveHat(flovatarId);
    } else if(category == 'eyeglasses'){
        window.flovatarRemoveEyeglasses(flovatarId);
    } else if(category == 'background'){
        window.flovatarRemoveBackground(flovatarId);
    }
}

window.flovatarAddAccessory = async function(flovatarId, accessory){
    console.log('flovatar add accessory start');
    window.dispatch('loading-start');
    try{
        let txId = await flovatarAddAccessoryTx(flovatarId, parseInt(accessory,10));
        console.log('flovatar add accessory finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar add accessory sealed');
                    //console.log(transaction);
                    window.dispatch('flovatar-accessory-added');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, flovatarId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.flovatarAddEyeglasses = async function(flovatarId, eyeglasses){
    console.log('flovatar add eyeglasses start');
    window.dispatch('loading-start');
    try{
        let txId = await flovatarAddEyeglassesTx(flovatarId, parseInt(eyeglasses,10));
        console.log('flovatar add eyeglasses finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar add eyeglasses sealed');
                    window.dispatch('flovatar-eyeglasses-added');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, flovatarId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.flovatarAddHat = async function(flovatarId, hat){
    console.log('flovatar add hat start');
    window.dispatch('loading-start');
    try{
        let txId = await flovatarAddHatTx(flovatarId, parseInt(hat,10));
        console.log('flovatar add hat finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar add hat sealed');
                    //console.log(transaction);
                    window.dispatch('flovatar-hat-added');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, flovatarId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.flovatarAddBackground = async function(flovatarId, background){
    console.log('flovatar add background start');
    window.dispatch('loading-start');
    try{
        let txId = await flovatarAddBackgroundTx(flovatarId, parseInt(background,10));
        console.log('flovatar add background finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar add background sealed');
                    //console.log(transaction);
                    window.dispatch('flovatar-background-added');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, flovatarId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.flovatarRemoveAccessory = async function(flovatarId){
    console.log('flovatar remove accessory start');
    window.dispatch('loading-start');
    try{
        let txId = await flovatarRemoveAccessoryTx(flovatarId);
        console.log('flovatar remove accessory finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar remove accessory sealed');
                    //console.log(transaction);
                    window.dispatch('flovatar-accessory-added');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, flovatarId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.flovatarRemoveEyeglasses = async function(flovatarId){
    console.log('flovatar remove eyeglasses start');
    window.dispatch('loading-start');
    try{
        let txId = await flovatarRemoveEyeglassesTx(flovatarId);
        console.log('flovatar remove eyeglasses finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar remove eyeglasses sealed');
                    window.dispatch('flovatar-eyeglasses-added');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, flovatarId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.flovatarRemoveHat = async function(flovatarId){
    console.log('flovatar remove hat start');
    window.dispatch('loading-start');
    try{
        let txId = await flovatarRemoveHatTx(flovatarId);
        console.log('flovatar remove hat finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar remove hat sealed');
                    //console.log(transaction);
                    window.dispatch('flovatar-hat-added');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, flovatarId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.flovatarRemoveBackground = async function(flovatarId){
    console.log('flovatar remove background start');
    window.dispatch('loading-start');
    try{
        let txId = await flovatarRemoveBackgroundTx(flovatarId);
        console.log('flovatar add background finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar remove background sealed');
                    //console.log(transaction);
                    window.dispatch('flovatar-background-added');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, flovatarId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.flobotAddBackground = async function(flobotId, background){
    console.log('flobot add background start');
    window.dispatch('loading-start');
    try{
        let txId = await flobotAddBackgroundTx(flobotId, parseInt(background,10));
        console.log('flobot add background finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flobot add background sealed');
                    //console.log(transaction);
                    window.dispatch('flobot-background-added');
                    window.dispatch('loading-completed');
                    window.getFlobot(window.user.addr, flobotId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.flobotRemoveBackground = async function(flobotId){
    console.log('flobot remove background start');
    window.dispatch('loading-start');
    try{
        let txId = await flobotRemoveBackgroundTx(flobotId);
        console.log('flobot add background finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flobot remove background sealed');
                    //console.log(transaction);
                    window.dispatch('flobot-background-added');
                    window.dispatch('loading-completed');
                    window.getFlobot(window.user.addr, flobotId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.flovatarSetName = async function(flovatarId, name){
    console.log('flovatar set name start');
    window.dispatch('loading-start');
    try{
        let txId = await flovatarSetNameTx(flovatarId, name);
        console.log('flovatar set name finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar set name sealed');
                    //console.log(transaction);
                    window.dispatch('flovatar-name-set');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, flovatarId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.flovatarSetPosition = async function(flovatarId, lat, lon){
    console.log('flovatar set position start');
    window.dispatch('loading-start');
    try{
        let txId = await flovatarSetPositionTx(flovatarId, lat, lon);
        console.log('flovatar set position finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar set position sealed');
                    //console.log(transaction);
                    window.dispatch('flovatar-postion-set');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, flovatarId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.flovatarAddStory = async function(flovatarId, text){
    console.log('flovatar add story start');
    window.dispatch('loading-start');
    try{
        let txId = await flovatarAddStoryTx(flovatarId, text);
        console.log('flovatar add story finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar add story sealed');
                    //console.log(transaction);
                    window.dispatch('flovatar-story-added');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, flovatarId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.flovatarUnlock3d = async function(flovatarId){
    console.log('flovatar unlock 3d start');
    window.dispatch('loading-start');
    try{
        let txId = await flovatarUnlock3dTx(flovatarId);
        console.log('flovatar unlock 3d finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar unlock 3d sealed');
                    //console.log(transaction);
                    window.dispatch('flovatar-3d-unlocked');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, flovatarId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}



window.collectibleSetName = async function(collectibleId, name){
    console.log('collectible set name start');
    window.dispatch('loading-start');
    try{
        let txId = await collectibleSetNameTx(collectibleId, name);
        console.log('collectible set name finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('collectible set name sealed');
                    //console.log(transaction);
                    window.dispatch('collectible-name-set');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, collectibleId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.collectibleSetPosition = async function(collectibleId, lat, lon){
    console.log('collectible set position start');
    window.dispatch('loading-start');
    try{
        let txId = await collectibleSetPositionTx(collectibleId, lat, lon);
        console.log('collectible set position finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('collectible set position sealed');
                    //console.log(transaction);
                    window.dispatch('collectible-postion-set');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, collectibleId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.collectibleAddStory = async function(collectibleId, text){
    console.log('collectible add story start');
    window.dispatch('loading-start');
    try{
        let txId = await collectibleAddStoryTx(collectibleId, text);
        console.log('collectible add story finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('collectible add story sealed');
                    //console.log(transaction);
                    window.dispatch('collectible-story-added');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, collectibleId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.flovatarSendGift = async function(flovatarId, address){
    console.log('flovatar send gift start');
    window.dispatch('loading-start');
    try{
        let txId = await flovatarSendGiftTx(flovatarId, address);
        console.log('flovatar send gift finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar gift sent');
                    //console.log(transaction);
                    window.dispatch('flovatar-gift-sent');
                    window.dispatch('loading-completed');
                    window.location = '/flovatars/'+flovatarId+'/'+address;
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.flobotSendGift = async function(flobotId, address){
    console.log('flobot send gift start');
    window.dispatch('loading-start');
    try{
        let txId = await flobotSendGiftTx(flobotId, address);
        console.log('flobot send gift finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flobot gift sent');
                    //console.log(transaction);
                    window.dispatch('flobot-gift-sent');
                    window.dispatch('loading-completed');
                    window.location = '/flobots/'+flobotId+'/'+address;
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.collectibleSendGift = async function(collectibleId, address){
    console.log('collectible send gift start');
    window.dispatch('loading-start');
    try{
        let txId = await collectibleSendGiftTx(collectibleId, address);
        console.log('collectible send gift finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('collectible gift sent');
                    //console.log(transaction);
                    window.dispatch('collectible-gift-sent');
                    window.dispatch('loading-completed');
                    window.location = '/collectibles/'+collectibleId+'/'+address;
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.buyFlovatarSale = async function(saleAddress, tokenId, amount, saleId){
    console.log('flovatar purchase start');
    window.dispatch('loading-start');
    try{
        let txId = null;
        if(window.currentWallet == 'dapper') {
            txId = await flovatarSaleBuyDapperTx(saleAddress, saleId, amount, process.env.MIX_CONTRACT_FLOW);
        } else {
            txId = await flovatarSaleBuyTx(saleAddress, tokenId, amount);
        }
        console.log('flovatar purchase finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar purchase sealed');
                    //console.log(transaction);
                    window.dispatch('flovatar-sale-purchased');
                    window.dispatch('loading-completed');
                    window.location = '/flovatars/'+tokenId+'/'+window.user.addr;
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.cancelFlovatarSale = async function(flovatarId, saleId){
    console.log('flovatar sale cancel start');
    window.dispatch('loading-start');
    try{
        let txId = null;
        if(window.currentWallet == 'dapper') {
            txId = await flovatarSaleCancelDapperTx(saleId);
        } else {
            txId = await flovatarSaleCancelTx(flovatarId);
        }
        console.log('flovatar sale cancel finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar sale cancel sealed');
                    console.log(transaction);
                    window.dispatch('flovatar-sale-cancelled');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, flovatarId);
                    window.getFlovatarSale(window.user.addr, flovatarId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.createFlovatarSale = async function(flovatarId, price){
    console.log('flovatar sale create start');
    window.dispatch('loading-start');
    try{
        let txId = null;
        if(window.currentWallet == 'dapper') {
            let expiry = Math.floor(Date.now()) + (60 * 60 * 24 * 365); //expires in one year
            txId = await flovatarSaleCreateDapperTx(flovatarId, price, 'Flovatar', expiry);
        } else {
            txId = await flovatarSaleCreateTx(flovatarId, price);
        }
        console.log('flovatar sale create finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar sale create sealed');
                    console.log(transaction);
                    window.dispatch('flovatar-sale-created');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, flovatarId);
                    window.getFlovatarSale(window.user.addr, flovatarId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}

window.updateFlovatarSale = async function(flovatarId, price){
    console.log('flovatar sale update start');
    window.dispatch('loading-start');
    try {
        let txId = await flovatarSaleUpdateTx(flovatarId, price);
        console.log('flovatar sale update finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('flovatar sale update sealed');
                    console.log(transaction);
                    window.dispatch('flovatar-sale-updated');
                    window.dispatch('loading-completed');
                    window.getFlovatar(window.user.addr, flovatarId);
                    window.getFlovatarSale(window.user.addr, flovatarId);
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}


window.getFlovatar = async function(address, flovatarId){
    await getFlovatarScript(address, flovatarId).then(function(data){
        //console.log('flovatar loaded');
        //console.log(data);
        window.dispatch('flovatar-loaded', {flovatar: data});
    });
}
window.getFlobot = async function(address, flobotId){
    await getFlobotScript(address, flobotId).then(function(data){
        //console.log('flovatar loaded');
        //console.log(data);
        window.dispatch('flobot-loaded', {flobot: data});
    });
}
window.getFlovatars = async function(address){
    await getFlovatarsScript(address).then(function(data){
        console.log('flovatars loaded');
        //console.log(data);
        window.dispatch('flovatar-loaded', {flovatars: data});
    });
}
window.getFlovatarSale = async function(address, flovatarId){
    await getFlovatarSaleScript(address, flovatarId).then(function(data){
        console.log('flovatar sale loaded');
        //console.log(data);
        window.dispatch('flovatar-sale-loaded', {sale: data});
    });
}
window.getFlovatarSales = async function(address){
    await getFlovatarSalesScript(address).then(function(data){
        console.log('flovatar sales loaded');
        //console.log(data);
        window.dispatch('flovatar-sales-loaded', {sales: data});
    });
}
window.getFlovatarsAndSales = async function(address){
    await getFlovatarsAndSalesScript(address).then(function(data){
        console.log('flovatars and sales loaded');
        //console.log(data);
        window.dispatch('flovatars-and-sales-loaded', {collections: data});
    });
}



window.getTemplate = async function(address, templateId){
    await getTemplateScript(address, templateId).then(function(data){
        console.log('template loaded');
        //console.log(data);
        window.dispatch('template-loaded', {template: data});
    });
}
window.getTemplates = async function(address){
    await getTemplatesScript(address).then(function(data){
        console.log('templates loaded');
        //console.log(data);
        window.dispatch('templates-loaded', {templates: data});
    });
}




window.signDapperMessage = async function(message){
    console.log('sign dapper message start');
    window.dispatch('loading-start');
    try {
        let txId = await signDapperMessageTx(message);
        console.log('sign dapper message finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('sign dapper message sealed');
                    console.log(transaction);
                    window.dispatch('dapper-message-signed');
                    window.dispatch('loading-completed');
                    window.walletLogin();
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}



window.upgradeFlobits = async function(idArr){
    console.log('upgrade flobits start');
    console.log(idArr);
    window.dispatch('loading-start');
    try {
        let txId = await upgradeFlobitsTx(idArr);
        console.log('upgrade flobits finish');
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if (fcl.tx.isSealed(transaction)) {
                if(!window.transError(transaction)){
                    console.log('upgrade flobits sealed');
                    console.log(transaction);
                    window.dispatch('flobits-upgraded');
                    window.dispatch('loading-completed');
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error});
    }
}





