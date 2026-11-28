<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleRequestsPerRoute;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * EVM-A (2026-11): ERC-721 tokenURI JSON documents behind the Cadence EVMBridgedMetadata views and the project
 * ERC-721s (/flovatars/json/{id}, /components/json/{id}, /flobots/json/{id}, /collectibles/json/{id},
 * /accessories/json/{id}, /evm/collections/{slug}.json) + the /evm gallery shell. In-memory SQLite.
 */
class EvmMetadataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
            'cache.default' => 'array',
            'app.url' => 'https://testnet.flovatar.com',
            'services.flow.network' => 'testnet',
            'evm.ui' => true,
            'evm.image_cdn' => false,
        ]);
        DB::purge();
        $this->withoutMix();
        $this->withoutMiddleware(ThrottleRequestsPerRoute::class);
        $this->schema();
        $this->seed_();
    }

    private function schema(): void
    {
        Schema::create('flovatars', function (Blueprint $t) {
            $t->increments('id'); $t->string('name')->nullable(); $t->string('series')->nullable(); $t->longText('svg')->nullable();
            $t->unsignedBigInteger('flow_id')->nullable(); $t->string('flow_address')->nullable(); $t->json('flow_metadata')->nullable();
            $t->integer('rare_count')->default(0); $t->integer('epic_count')->default(0); $t->integer('legendary_count')->default(0); $t->timestamps();
        });
        Schema::create('flovatar_templates', function (Blueprint $t) {
            $t->increments('id'); $t->string('name')->nullable(); $t->string('category')->nullable(); $t->string('color')->nullable();
            $t->integer('series')->nullable(); $t->string('rarity')->nullable(); $t->longText('svg')->nullable(); $t->text('description')->nullable();
            $t->unsignedBigInteger('flow_id')->nullable(); $t->json('flow_metadata')->nullable(); $t->timestamps();
        });
        Schema::create('flovatar_components', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedInteger('flovatar_template_id')->nullable(); $t->unsignedBigInteger('flow_id')->nullable();
            $t->string('flow_address')->nullable(); $t->json('flow_metadata')->nullable(); $t->integer('mint')->nullable(); $t->timestamps();
        });
        Schema::create('flobots', function (Blueprint $t) {
            $t->increments('id'); $t->string('name')->nullable(); $t->string('series')->nullable(); $t->longText('svg')->nullable();
            $t->unsignedBigInteger('flow_id')->nullable(); $t->string('rarity')->nullable(); $t->string('flow_address')->nullable(); $t->json('flow_metadata')->nullable(); $t->timestamps();
        });
        Schema::create('dust_series', function (Blueprint $t) {
            $t->increments('id'); $t->string('name')->nullable(); $t->unsignedBigInteger('flow_id')->nullable(); $t->json('flow_metadata')->nullable(); $t->timestamps();
        });
        Schema::create('dust_templates', function (Blueprint $t) {
            $t->increments('id'); $t->string('name')->nullable(); $t->integer('layer')->nullable(); $t->string('rarity')->nullable();
            $t->unsignedBigInteger('flow_id')->nullable(); $t->json('flow_metadata')->nullable(); $t->timestamps();
        });
        Schema::create('dust_collectibles', function (Blueprint $t) {
            $t->increments('id'); $t->string('name')->nullable(); $t->unsignedInteger('dust_series_id')->nullable(); $t->float('rarity_score')->nullable();
            $t->unsignedBigInteger('flow_id')->nullable(); $t->string('flow_address')->nullable(); $t->json('flow_metadata')->nullable(); $t->timestamps();
        });
        Schema::create('dust_accessories', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedInteger('dust_template_id')->nullable(); $t->unsignedBigInteger('flow_id')->nullable();
            $t->string('flow_address')->nullable(); $t->json('flow_metadata')->nullable(); $t->integer('mint')->nullable(); $t->boolean('destroyed')->default(0); $t->timestamps();
        });
    }

    private function seed_(): void
    {
        DB::table('flovatar_templates')->insert([
            ['id' => 1, 'name' => 'Curly Red', 'category' => 'hair', 'rarity' => 'common', 'series' => 1, 'flow_id' => 10],
            ['id' => 2, 'name' => 'Crown', 'category' => 'hat', 'rarity' => 'legendary', 'series' => 1, 'flow_id' => 20],
            ['id' => 3, 'name' => 'Spark', 'category' => 'spark', 'rarity' => 'common', 'series' => 1, 'flow_id' => 30],
            ['id' => 4, 'name' => 'Bolt Arms', 'category' => 'arms', 'rarity' => 'common', 'series' => 2, 'flow_id' => 603],
        ]);
        DB::table('flovatars')->insert(['flow_id' => 1, 'name' => 'Giulia', 'rare_count' => 1, 'svg' => '<svg>big</svg>',
            'flow_metadata' => json_encode(['id' => '1', 'name' => 'Giulia', 'hatId' => '20', 'accessoryId' => null,
                'metadata' => ['mint' => '1', 'series' => '1', 'svg' => '<svg>big</svg>', 'combination' => 'B1H10', 'creatorAddress' => '0x2a0eccae942667be',
                    'components' => ['hair' => '10']]])]);
        DB::table('flovatar_components')->insert(['flow_id' => 7, 'flovatar_template_id' => 3, 'mint' => 5, 'flow_metadata' => json_encode(['id' => 7, 'templateId' => 30])]);
        DB::table('flobots')->insert(['flow_id' => 3, 'rarity' => 'epic', 'flow_metadata' => json_encode(['id' => '3', 'name' => '',
            'metadata' => ['mint' => '3', 'rarity' => 'epic', 'components' => ['arms' => '603']]])]);
        DB::table('dust_series')->insert(['flow_id' => 1, 'name' => 'Psyche Likee', 'flow_metadata' => json_encode(['layers' => ['2' => ['name' => 'Legs']]])]);
        DB::table('dust_templates')->insert(['id' => 1, 'flow_id' => 23, 'name' => 'Big Paws', 'layer' => 2, 'rarity' => 'rare']);
        DB::table('dust_collectibles')->insert(['flow_id' => 4, 'flow_metadata' => json_encode(['id' => '4', 'mint' => '4', 'name' => '', 'series' => '1',
            'layers' => ['2' => ['type' => 'UInt64', 'value' => '23']]])]);
        DB::table('dust_accessories')->insert(['flow_id' => 9, 'dust_template_id' => 1, 'mint' => 2]);
    }

    public function test_flovatar_token_uri_json(): void
    {
        $r = $this->getJson('/flovatars/json/1')->assertOk();
        $r->assertJsonPath('name', 'Giulia · Flovatar #1')
            ->assertJsonPath('image', 'https://testnet.flovatar.com/api/image/1')
            ->assertJsonPath('external_url', 'https://testnet.flovatar.com/flovatars/1');
        $attrs = collect($r->json('attributes'))->pluck('value', 'trait_type');
        $this->assertSame('Curly Red', $attrs['Hair']);
        $this->assertSame('Crown', $attrs['Hat']);
        $this->assertSame('Legendary', $attrs['Hat rarity']);
        $this->assertSame(1, $attrs['Rare boosters']);
        $this->assertStringNotContainsString('<svg', $r->getContent());
        $this->assertSame('*', $r->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_cdn_png_on_mainnet(): void
    {
        config(['evm.image_cdn' => true, 'flovatar.imagesUrl' => 'https://images.flovatar.com']);
        $this->getJson('/flovatars/json/1')->assertJsonPath('image', 'https://images.flovatar.com/flovatar/png/1.png');
    }

    public function test_component_flobot_collectible_accessory(): void
    {
        $this->getJson('/components/json/7')->assertOk()->assertJsonPath('name', 'Spark · Flobit #7')
            ->assertJsonPath('image', 'https://testnet.flovatar.com/api/image/template/30');
        $fb = $this->getJson('/flobots/json/3')->assertOk()->assertJsonPath('name', 'Flobot #3');
        $this->assertSame('Bolt Arms', collect($fb->json('attributes'))->pluck('value', 'trait_type')['Arms']);
        $dc = $this->getJson('/collectibles/json/4')->assertOk()->assertJsonPath('name', 'Psyche Likee #4');
        $this->assertSame('Big Paws', collect($dc->json('attributes'))->pluck('value', 'trait_type')['Legs']);
        $this->getJson('/accessories/json/9')->assertOk()->assertJsonPath('name', 'Big Paws · Likee Accessory #9')
            ->assertJsonPath('image', 'https://testnet.flovatar.com/api/image/collectible/template/23');
    }

    public function test_unknown_items_404_json(): void
    {
        $this->getJson('/flovatars/json/999')->assertNotFound()->assertJsonPath('error', 'not found');
        $this->get('/flovatars/json/abc')->assertNotFound();
    }

    public function test_collection_contract_uri(): void
    {
        $this->getJson('/evm/collections/flovatar.json')->assertOk()
            ->assertJsonPath('name', 'Flovatar')->assertJsonPath('seller_fee_basis_points', 500)
            ->assertJsonPath('cadence_type', config('evm.collections.flovatar.type'));
        $this->getJson('/evm/collections/nope.json')->assertNotFound();
    }

    public function test_gallery_shell(): void
    {
        $this->get('/evm')->assertOk()->assertSee('window.EVM_CFG', false)->assertSee('Connect MetaMask');
        config(['evm.ui' => false]);
        $this->get('/evm')->assertOk()->assertDontSee('window.EVM_CFG', false)->assertSee('not available yet');
    }
}
