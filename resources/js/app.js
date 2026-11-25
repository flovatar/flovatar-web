require('./actions');
require('./utils');
require('./headless');
require('./passkey');

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
require('./app/dust-market');

window.Alpine = Alpine;

Alpine.start();


