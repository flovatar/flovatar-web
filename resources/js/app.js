require('./actions');
require('./utils');
require('./headless');

import Alpine from 'alpinejs';
import persist from '@alpinejs/persist'

Alpine.plugin(persist);

require('./app/app');
require('./app/home');
require('./app/marketplace');
require('./app/meme');
require('./app/showroom');
require('./app/showroom-flobot');
require('./app/builder');
require('./app/flobot-builder');
require('./app/collection');
require('./app/sales');
require('./app/flovatar');
require('./app/flobot');
require('./app/component');
require('./app/flobit-type');
require('./app/chart');
require('./app/getstarted');
require('./app/treasurehunting');
require('./app/fub');
require('./app/pack');
require('./app/dust-accessory');
require('./app/dust-collectible');
require('./app/dust-builder');
require('./app/showroom-collectible');
require('./app/asteroid-builder');

window.Alpine = Alpine;

// Feature bundles (passkey sign-in, ĐUST market/swap/quests, TerraKit check) are lazy webpack chunks served by the
// origin only to requests that may see the feature (App\Support\Stealth: mode on, or stealth + stealth gate). The
// layout lists them in window.__FX; without it (every public/static page while a feature is off or stealth) nothing
// is loaded and Alpine starts exactly as before.
const FX = {
    p: () => import(/* webpackChunkName: "p" */ './passkey'),
    m: () => import(/* webpackChunkName: "m" */ './app/dust-market'),
    s: () => import(/* webpackChunkName: "s" */ './app/dust-swap'),
    q: () => import(/* webpackChunkName: "q" */ './quests'),
    t: () => import(/* webpackChunkName: "t" */ './app/terrakit-check'),
    i: () => import(/* webpackChunkName: "i" */ './app/islands'),
    u: () => import(/* webpackChunkName: "u" */ './app/fub-v1'),
    r: () => import(/* webpackChunkName: "r" */ './app/pack-rewind'),
};
const fx = (Array.isArray(window.__FX) ? window.__FX : []).filter((k) => FX[k]);
if (fx.length) {
    // registration order matters (the passkey bundle wraps initApp before the others use it)
    fx.reduce((p, k) => p.then(() => FX[k]()).catch((e) => console.error(e)), Promise.resolve()).then(() => Alpine.start());
} else {
    Alpine.start();
}


