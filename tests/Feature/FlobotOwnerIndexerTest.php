<?php

namespace Tests\Feature;

use App\Jobs\ProcessGraffleEvents;
use App\Models\Event;
use App\Models\Flobot;
use App\Services\Flow\CadenceJson;
use App\Services\Flow\EventIngestor;
use App\Services\Flow\FlowEventTypes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Flobots 608/609 kept flow_address NULL: the Cadence 1.0 Flobot contract never emits Flobot.Deposit, so neither the
 * mint (Flobot.Created) nor any later transfer gave them an owner. The indexer now (a) sets the creator as owner on
 * Created when the Flobot is found in the creator's collection and (b) ingests the standard
 * NonFungibleToken.Deposited event for Flobot NFTs (and nothing else from that global event).
 * Payloads are the real mainnet events of tx 02c647e5… (Flobot #609).
 */
class FlobotOwnerIndexerTest extends TestCase
{
    // real payloads of tx 02c647e5… (block 172165368): event 2 Flobot.Created, event 4 NonFungibleToken.Deposited
    const CREATED = 'eyJ2YWx1ZSI6eyJpZCI6IkEuOTIxZWE0NDlkZmZlYzY4YS5GbG9ib3QuQ3JlYXRlZCIsImZpZWxkcyI6W3sidmFsdWUiOnsidmFsdWUiOiI2MDkiLCJ0eXBlIjoiVUludDY0In0sIm5hbWUiOiJpZCJ9LHsidmFsdWUiOnsidmFsdWUiOnsiaWQiOiJBLjkyMWVhNDQ5ZGZmZWM2OGEuRmxvYm90Lk1ldGFkYXRhIiwiZmllbGRzIjpbeyJ2YWx1ZSI6eyJ2YWx1ZSI6IjYwOSIsInR5cGUiOiJVSW50NjQifSwibmFtZSI6Im1pbnQifSx7InZhbHVlIjp7InZhbHVlIjoiMiIsInR5cGUiOiJVSW50MzIifSwibmFtZSI6InNlcmllcyJ9LHsidmFsdWUiOnsidmFsdWUiOiJCNjQ1SDY4N0E2MDRMNzI5RjY3NyIsInR5cGUiOiJTdHJpbmcifSwibmFtZSI6ImNvbWJpbmF0aW9uIn0seyJ2YWx1ZSI6eyJ2YWx1ZSI6ImNvbW1vbiIsInR5cGUiOiJTdHJpbmcifSwibmFtZSI6InJhcml0eSJ9LHsidmFsdWUiOnsidmFsdWUiOiIweDMzN2JmMTE5NTRhMzI3ZDEiLCJ0eXBlIjoiQWRkcmVzcyJ9LCJuYW1lIjoiY3JlYXRvckFkZHJlc3MifSx7InZhbHVlIjp7InZhbHVlIjpbeyJrZXkiOnsidmFsdWUiOiJhcm1zIiwidHlwZSI6IlN0cmluZyJ9LCJ2YWx1ZSI6eyJ2YWx1ZSI6IjYwNCIsInR5cGUiOiJVSW50NjQifX0seyJrZXkiOnsidmFsdWUiOiJib2R5IiwidHlwZSI6IlN0cmluZyJ9LCJ2YWx1ZSI6eyJ2YWx1ZSI6IjY0NSIsInR5cGUiOiJVSW50NjQifX0seyJrZXkiOnsidmFsdWUiOiJmYWNlIiwidHlwZSI6IlN0cmluZyJ9LCJ2YWx1ZSI6eyJ2YWx1ZSI6IjY3NyIsInR5cGUiOiJVSW50NjQifX0seyJrZXkiOnsidmFsdWUiOiJoZWFkIiwidHlwZSI6IlN0cmluZyJ9LCJ2YWx1ZSI6eyJ2YWx1ZSI6IjY4NyIsInR5cGUiOiJVSW50NjQifX0seyJrZXkiOnsidmFsdWUiOiJsZWdzIiwidHlwZSI6IlN0cmluZyJ9LCJ2YWx1ZSI6eyJ2YWx1ZSI6IjcyOSIsInR5cGUiOiJVSW50NjQifX1dLCJ0eXBlIjoiRGljdGlvbmFyeSJ9LCJuYW1lIjoiY29tcG9uZW50cyJ9XX0sInR5cGUiOiJTdHJ1Y3QifSwibmFtZSI6Im1ldGFkYXRhIn1dfSwidHlwZSI6IkV2ZW50In0K';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'headless.enabled' => false,
            'services.flow.address' => '0x921ea449dffec68a',
            'services.flow.storefrontaddress' => '0x4eb8a10cb9f87357',
            'services.flow.nftaddress' => '0x1d7e57aa55817448',
        ]);
        DB::purge();
        Queue::fake();
        Schema::create('flobots', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name')->nullable();
            $t->string('series')->nullable();
            $t->string('rarity')->nullable();
            $t->longText('svg')->nullable();
            $t->unsignedBigInteger('flow_id')->nullable();
            $t->string('flow_address')->nullable();
            $t->json('flow_metadata')->nullable();
            $t->timestamps();
        });
        Schema::create('sale_offers', function (Blueprint $t) {
            $t->increments('id');
            $t->string('item_type')->nullable();
            $t->unsignedBigInteger('item_id')->nullable();
            $t->unsignedBigInteger('flow_id')->nullable();
            $t->string('flow_address')->nullable();
            $t->timestamps();
        });
        Schema::create('events', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedBigInteger('block_id')->nullable();
            $t->string('transaction_id')->nullable();
            $t->string('type');
            $t->unsignedBigInteger('index')->nullable();
            $t->integer('transaction_index')->nullable();
            $t->integer('event_index')->nullable();
            $t->json('flow_metadata')->nullable();
            $t->boolean('synced')->default(false);
            $t->boolean('graffle')->default(false);
            $t->string('source')->nullable();
            $t->dateTime('event_date')->nullable();
            $t->integer('attempts')->default(0);
            $t->timestamps();
            $t->unique(['transaction_id', 'event_index']);
        });
    }

    const DEPOSITED = 'eyJ2YWx1ZSI6eyJpZCI6IkEuMWQ3ZTU3YWE1NTgxNzQ0OC5Ob25GdW5naWJsZVRva2VuLkRlcG9zaXRlZCIsImZpZWxkcyI6W3sidmFsdWUiOnsidmFsdWUiOiJBLjkyMWVhNDQ5ZGZmZWM2OGEuRmxvYm90Lk5GVCIsInR5cGUiOiJTdHJpbmcifSwibmFtZSI6InR5cGUifSx7InZhbHVlIjp7InZhbHVlIjoiNjA5IiwidHlwZSI6IlVJbnQ2NCJ9LCJuYW1lIjoiaWQifSx7InZhbHVlIjp7InZhbHVlIjoiMTQ5NTMzNTg0MTgxOTQ5IiwidHlwZSI6IlVJbnQ2NCJ9LCJuYW1lIjoidXVpZCJ9LHsidmFsdWUiOnsidmFsdWUiOnsidmFsdWUiOiIweDMzN2JmMTE5NTRhMzI3ZDEiLCJ0eXBlIjoiQWRkcmVzcyJ9LCJ0eXBlIjoiT3B0aW9uYWwifSwibmFtZSI6InRvIn0seyJ2YWx1ZSI6eyJ2YWx1ZSI6IjYwMjUyNDEyOSIsInR5cGUiOiJVSW50NjQifSwibmFtZSI6ImNvbGxlY3Rpb25VVUlEIn1dfSwidHlwZSI6IkV2ZW50In0K';

    private function deposited(string $nftType, ?string $to): array
    {
        $opt = $to === null ? ['value' => null, 'type' => 'Optional'] : ['value' => ['value' => $to, 'type' => 'Address'], 'type' => 'Optional'];

        return ['value' => ['id' => 'A.1d7e57aa55817448.NonFungibleToken.Deposited', 'fields' => [
            ['value' => ['value' => $nftType, 'type' => 'String'], 'name' => 'type'],
            ['value' => ['value' => '609', 'type' => 'UInt64'], 'name' => 'id'],
            ['value' => ['value' => '149533584181949', 'type' => 'UInt64'], 'name' => 'uuid'],
            ['name' => 'to', 'value' => $opt],
            ['value' => ['value' => '1', 'type' => 'UInt64'], 'name' => 'collectionUUID'],
        ]], 'type' => 'Event'];
    }

    public function test_standard_deposited_kept_only_for_flobots_with_an_owner(): void
    {
        $t = 'A.1d7e57aa55817448.NonFungibleToken.Deposited';
        $this->assertContains($t, FlowEventTypes::all());
        $this->assertContains($t, FlowEventTypes::globalTypes());
        $flobot = CadenceJson::decode($this->deposited('A.921ea449dffec68a.Flobot.NFT', '0x337bf11954a327d1'));
        $this->assertSame('0x337bf11954a327d1', $flobot['to']);
        $this->assertSame(609, (int) $flobot['id']);
        $this->assertTrue(FlowEventTypes::keep($t, $flobot));
        $this->assertFalse(FlowEventTypes::keep($t, CadenceJson::decode($this->deposited('A.0b2a3299cc857e29.TopShot.NFT', '0x1'))));
        $this->assertFalse(FlowEventTypes::keep($t, CadenceJson::decode($this->deposited('A.921ea449dffec68a.Flobot.NFT', null))));
        // Flovatar / Flobit transfers keep using their own contract Deposit events
        $this->assertFalse(FlowEventTypes::keep($t, CadenceJson::decode($this->deposited('A.921ea449dffec68a.Flovatar.NFT', '0x1'))));
    }

    public function test_real_mainnet_deposited_payload_is_kept(): void
    {
        $p = CadenceJson::decodePayload(self::DEPOSITED);
        $this->assertSame('A.921ea449dffec68a.Flobot.NFT', $p['type']);
        $this->assertSame('0x337bf11954a327d1', $p['to']);
        $this->assertTrue(FlowEventTypes::keep('A.1d7e57aa55817448.NonFungibleToken.Deposited', $p));
    }

    public function test_ingestor_stores_flobot_deposit_and_drops_foreign_nfts(): void
    {
        $row = fn ($i, $payload) => ['block_height' => 172165368, 'block_timestamp' => '2026-11-24T10:45:44Z',
            'type' => 'A.1d7e57aa55817448.NonFungibleToken.Deposited', 'transaction_id' => 'tx', 'transaction_index' => 0,
            'event_index' => $i, 'payload' => base64_encode(json_encode($payload))];
        [$inserted, $dropped] = (new EventIngestor)->store([
            $row(4, $this->deposited('A.921ea449dffec68a.Flobot.NFT', '0x337bf11954a327d1')),
            $row(5, $this->deposited('A.0b2a3299cc857e29.TopShot.NFT', '0x337bf11954a327d1')),
        ], 'rest', true);
        $this->assertSame([1, 1], [$inserted, $dropped]);
    }

    private function job(Event $e): ProcessGraffleEvents
    {
        // chain reads replaced by the metadata the get_flobot script returns when the creator holds the Flobot
        return new class($e, false) extends ProcessGraffleEvents
        {
            protected function syncFlobotFromFlowAddress($id, $address)
            {
                return Flobot::updateOrCreate(['flow_id' => $id], ['rarity' => 'common', 'series' => 2]);
            }

            protected function updateFlobotTemplatesBurnCount($flobot) {}
        };
    }

    public function test_created_sets_creator_as_owner(): void
    {
        $e = Event::create(['type' => 'A.921ea449dffec68a.Flobot.Created', 'block_id' => 172165368, 'transaction_id' => 'tx', 'event_index' => 2,
            'flow_metadata' => CadenceJson::decodePayload(self::CREATED), 'source' => 'rest']);
        $this->job($e)->handle();

        $this->assertSame('0x337bf11954a327d1', Flobot::whereFlowId(609)->value('flow_address'));
        $this->assertTrue((bool) $e->fresh()->synced);
    }

    public function test_standard_deposited_moves_the_flobot(): void
    {
        Flobot::create(['flow_id' => 609, 'flow_address' => '0x337bf11954a327d1']);
        $e = Event::create(['type' => 'A.1d7e57aa55817448.NonFungibleToken.Deposited', 'block_id' => 172165369, 'transaction_id' => 'tx2', 'event_index' => 4,
            'flow_metadata' => CadenceJson::decode($this->deposited('A.921ea449dffec68a.Flobot.NFT', '0xd2fce0c215c73c39')), 'source' => 'rest']);
        $this->job($e)->handle();

        $this->assertSame('0xd2fce0c215c73c39', Flobot::whereFlowId(609)->value('flow_address'));
        $this->assertTrue((bool) $e->fresh()->synced);
    }

    public function test_owner_redirect_404s_instead_of_looping_when_owner_unknown(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\ThrottleRequestsPerRoute::class);
        Flobot::create(['flow_id' => 610, 'flow_address' => null]);
        Flobot::create(['flow_id' => 611, 'flow_address' => '0x337bf11954a327d1']);
        $this->get('/flobots/610')->assertNotFound();
        $this->get('/flobots/611')->assertRedirect('/flobots/611/0x337bf11954a327d1');
    }
}
