<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Pack Rewind (testnet) stock + passkey FLOW starting grants. Additive only.
 *  pack_rewind_packs   one row per re-minted testnet pack (Flovatar or Sportvatar), linked to its mainnet original
 *  passkey_flow_grants one row per passkey account that asked for / got the starting FLOW (unique per account)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pack_rewind_packs')) {
            Schema::create('pack_rewind_packs', function (Blueprint $t) {
                $t->id();
                $t->string('collection', 16);            // flovatar | sportvatar
                $t->string('series', 32)->index();       // config/pack_rewind.php series key
                $t->unsignedBigInteger('mainnet_id');    // original mainnet pack id
                $t->unsignedBigInteger('testnet_id');    // re-minted pack id on testnet
                $t->string('name', 64);                  // on-chain pack name
                $t->decimal('price', 16, 8);             // rewind price (FLOW, or ĐUST for the ĐUST pack)
                $t->string('currency', 8)->default('FLOW');
                $t->json('contents');                    // [{"m": mainnet template id, "t": testnet template id}]
                $t->string('source', 48)->default('chain'); // chain | open-tx | creation | regenerated:donor=<id>
                $t->string('status', 16)->default('available')->index(); // available | reserved | sold
                $t->string('reserved_by', 20)->nullable()->index();
                $t->timestamp('reserved_until')->nullable();
                $t->string('sold_to', 20)->nullable()->index();
                $t->string('sold_tx', 64)->nullable();
                $t->timestamp('sold_at')->nullable();
                $t->timestamp('opened_at')->nullable();
                $t->timestamps();
                $t->unique(['collection', 'testnet_id']);
                $t->unique(['collection', 'mainnet_id']);
                $t->index(['series', 'status', 'mainnet_id']);
            });
        }
        if (! Schema::hasTable('passkey_flow_grants')) {
            Schema::create('passkey_flow_grants', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('passkey_account_id')->unique();
                $t->string('address', 20)->index();
                $t->decimal('amount', 16, 8);
                $t->string('ip_bucket', 64)->index();
                $t->string('device_hash', 64)->nullable()->index();
                $t->string('status', 16)->default('pending')->index(); // pending | sent | failed
                $t->string('tx_id', 64)->nullable();
                $t->string('error', 300)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pack_rewind_packs');
        Schema::dropIfExists('passkey_flow_grants');
    }
};
