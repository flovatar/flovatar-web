<?php

namespace App\Models;

use App\Support\Islands;
use Illuminate\Database\Eloquent\Model;

/**
 * Asteroid Island (FlovatarAsteroidIsland.NFT), indexed from chain events (App\Services\Islands\IslandEventProcessor).
 * flow_id = on-chain serial; designation K-334/####. full_svg = the composed scene with premium residents embedded
 * (App\Services\Islands\IslandScene) — the input of the static renderer (ItemRenderer type "island").
 */
class Island extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'layers' => 'array',
        'deed_ids' => 'array',
        'fused' => 'boolean',
        'terraformed_at' => 'datetime',
        'chain_synced_at' => 'datetime',
    ];

    public function residents()
    {
        return $this->hasMany(IslandResident::class, 'island_flow_id', 'flow_id');
    }

    public function getDesignationAttribute(): string
    {
        return Islands::designation((int) $this->flow_id);
    }

    public function getDisplayNameAttribute(): string
    {
        return trim((string) $this->name) !== '' ? (string) $this->name : 'Asteroid '.$this->designation;
    }

    /** Scene + premium residents (embedded data: URIs), exactly what the island page and the renderer show. */
    public function getFullSvgAttribute(): string
    {
        return app(\App\Services\Islands\IslandScene::class)->full($this, false);
    }
}
