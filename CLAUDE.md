# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Flovatar is a Laravel-based web application integrated with the Flow blockchain, combining NFT management (Flovatars, Flobots, Dust Collectibles, Asteroids) with a sophisticated frontend using FCL (Flow Client Library) and Laravel Mix for asset compilation.

## Technology Stack

- **Backend**: Laravel 10 (PHP 7.3+/8.0+) with Jetstream/Livewire
- **Frontend**: Laravel Mix, AlpineJS, Three.js, GSAP, jQuery
- **Blockchain**: Flow blockchain with Cadence smart contracts
- **Flow Integration**:
  - FCL (Flow Client Library) for frontend blockchain interactions
  - Flow PHP SDK (`mayvenstudios/flow-php-sdk`) for backend blockchain operations
  - Flow Go SDK in `/go` directory for advanced blockchain operations
- **Database**: MySQL/PostgreSQL (configured via `.env`)
- **Queue**: Redis with Laravel Queues
- **Testing**: PHPUnit

## Architecture

### Laravel Backend

The application follows Laravel conventions with specific patterns:

- **Controllers**: Organized by entity type (Flovatar, Flobot, DustCollectible, Asteroid)
  - `/app/Http/Controllers/` - Main controllers
  - `/app/Http/Controllers/Api/` - API endpoints for frontend consumption
  - Image generation controllers (e.g., `FlovatarImageController`, `FlobotImageController`)
  - Builder controllers for NFT creation workflows
  - Showroom controllers for displaying collections

- **Models**: Eloquent models in `/app/Models/`
  - Core entities: `Flovatar`, `Flobot`, `DustCollectible`, `FlovatarComponent`
  - Templates: `FlovatarTemplate`, `DustTemplate`, `AsteroidTemplate`
  - Sales/Marketplace: `Sale`, `SaleOffer`, `Pack`
  - Support: `Event`, `Sync`, `GameScore`, `Meme`

- **Console Commands**: Extensive CLI commands in `/app/Console/Commands/` for:
  - Event syncing: `SyncEvents`, `ProcessEvents`, `ProcessGraffleEvents`
  - Minting: `MintPack`, `MintComponents`, `MintDustTemplates`
  - Image processing: `RefreshFlovatarImages`, `RefreshFlobotImages`, `RefreshCollectibleImages`
  - Blockchain operations: `SendDust`, `SendFlow`, `SendFlobits`
  - Template management: `ImportTemplate`, `ImportDustTemplate`, `ImportFlobotTemplate`

### Flow Blockchain Integration

- **Cadence Contracts**: Located in `/cadence/contracts/`
  - Core contracts: `Flovatar.cdc`, `Flobot.cdc`, `FlovatarDustCollectible.cdc`
  - Component system: `FlovatarComponent.cdc`, `FlovatarComponentTemplate.cdc`
  - Marketplace: `FlovatarMarketplace.cdc`, `FlovatarPack.cdc`
  - Token: `FlovatarDustToken.cdc`, `FlovatarInbox.cdc`

- **Flow Configuration**: `/cadence/flow.json`
  - Network definitions: emulator, testnet, mainnet
  - Contract deployments and aliases
  - Account configurations for different networks

- **Scripts**: `/cadence/scripts/*.cdc` - Read-only blockchain queries
  - Ownership checks, collection queries, template retrieval

- **Frontend Transactions**: `/resources/js/flow/*.tx.js` - FCL transaction builders
  - Minting, sales, claims, marketplace operations
  - Structured as importable JavaScript modules

### Frontend Architecture

- **Entry Point**: `/resources/js/app.js`
- **FCL Bootstrap**: `/resources/js/bootstrap.js` - Configures Flow Client Library
  - Network switching (emulator/testnet/mainnet)
  - Contract address mapping via environment variables
  - Access node and wallet discovery configuration

- **Flow Actions**: `/resources/js/actions.js` - Frontend blockchain interaction layer
- **Utilities**: `/resources/js/utils.js` - Helper functions
- **3D/Graphics**:
  - `/resources/js/shaders/` - GLSL shaders for WebGL
  - `/resources/js/classes/` - Three.js scene management

- **Styles**: `/resources/scss/app.scss` compiled via Laravel Mix

## Common Development Commands

### Laravel Commands

```bash
# Standard Laravel development (no Sail detected)
php artisan serve              # Start development server
php artisan migrate            # Run migrations
php artisan db:seed            # Seed database
php artisan queue:work         # Process queue jobs

# Testing
php artisan test               # Run all tests
vendor/bin/phpunit tests/Unit  # Run unit tests only
vendor/bin/phpunit tests/Feature # Run feature tests only

# Cache clearing
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

### Asset Compilation

```bash
npm run dev                    # Compile assets for development
npm run watch                  # Watch files and recompile on changes
npm run hot                    # Hot module replacement
npm run production             # Compile and minify for production
```

### Flow Blockchain Commands

```bash
# From /cadence directory
flow emulator start            # Start local Flow emulator
flow project deploy --network emulator    # Deploy contracts to emulator
flow project deploy --network testnet     # Deploy contracts to testnet

# Execute scripts (read-only queries)
flow scripts execute ./scripts/get_flovatar.cdc --network emulator
flow scripts execute ./scripts/check_availability.cdc --network testnet

# Custom Laravel commands for Flow operations
php artisan sync:events                    # Sync blockchain events to database
php artisan sync:templates                 # Sync contract templates
php artisan mint:pack {packId} {network}   # Mint NFT pack
php artisan send:dust {address} {amount}   # Send DUST tokens
```

### Go SDK (Advanced)

```bash
# From /go directory
go run main.go                 # Run Go blockchain client
go test ./...                  # Run Go tests
```

## Environment Configuration

Key environment variables for Flow integration (see `.env.example`):

- `MIX_FLOW_NETWORK` - Current network (emulator/testnet/mainnet)
- `MIX_CONTRACT_FLOW` - Main Flovatar contract address
- `MIX_CONTRACT_FUNGIBLE` - FungibleToken contract address
- `MIX_CONTRACT_NON_FUNGIBLE` - NonFungibleToken contract address
- `MIX_METADATA_VIEWS` - MetadataViews contract address

## Development Workflow

1. **Start Flow Emulator** (for local development):
   ```bash
   cd cadence && flow emulator start
   ```

2. **Deploy Contracts** (emulator only):
   ```bash
   flow project deploy --network emulator
   ```

3. **Start Laravel Backend**:
   ```bash
   php artisan serve
   npm run watch
   ```

4. **Sync Blockchain Data**:
   ```bash
   php artisan sync:events
   php artisan sync:templates
   ```

## Team-wide Development Standards

- MCP servers standardized across development environments
- Git workflow and commit message standards enforced
- Follow official Flow documentation patterns
- Use incremental, checkpoint-based development
- Test on emulator before testnet deployment
- Implement proper resource handling with @ and & syntax
- Follow MetadataViews standards for NFT projects

## Key Integration Points

- **FCL Configuration**: All frontend Flow interactions configured in `resources/js/bootstrap.js`
- **Contract Addresses**: Managed via environment variables and injected into FCL config
- **Event Processing**: Blockchain events synced to database via console commands (typically scheduled)
- **Image Generation**: Dynamic SVG/PNG generation for NFTs via dedicated image controllers
- **Marketplace**: Custom marketplace logic combining Laravel backend + Flow smart contracts

## MCP Servers

- Use flow-mcp for reading blockchain data, managing accounts, checking balances, and interacting with native contracts

## Testing Notes

- PHPUnit configured in `phpunit.xml`
- Test suites: Unit (`tests/Unit`) and Feature (`tests/Feature`)
- Test environment uses array cache/session drivers
- Database connection configurable for testing (can use SQLite in-memory)

## Code Style & Patterns

- Follow Laravel conventions and best practices
- Use Eloquent relationships for database queries
- Leverage Laravel's service container for dependency injection
- Console commands should be idempotent where possible
- Frontend: ES6+ JavaScript, modular transaction files
- Cadence: Follow Flow/Cadence best practices for resource-oriented programming
