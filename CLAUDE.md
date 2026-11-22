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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- This project upgraded from Laravel 10 without migrating to the new streamlined Laravel file structure.
- This is perfectly fine and recommended by Laravel. Follow the existing structure from Laravel 10. We do not need to migrate to the new Laravel structure unless the user explicitly requests it.

## Laravel 10 Structure

- Middleware typically lives in `app/Http/Middleware/` and service providers in `app/Providers/`.
- There is no `bootstrap/app.php` application configuration in a Laravel 10 structure:
    - Middleware registration happens in `app/Http/Kernel.php`
    - Exception handling is in `app/Exceptions/Handler.php`
    - Console commands and schedule register in `app/Console/Kernel.php`
    - Rate limits likely exist in `RouteServiceProvider` or `app/Http/Kernel.php`

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
