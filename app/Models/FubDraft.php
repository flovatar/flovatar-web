<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** FUB v1 (database/migrations/2026_11_27_100001_create_fub_tables.php). */
class FubDraft extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['settings' => 'array', 'synced_at' => 'datetime', 'published_at' => 'datetime', 'chain_fub_id' => 'integer'];

    public function layers(): HasMany
    {
        return $this->hasMany(FubDraftLayer::class)->orderBy('position');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FubDraftItem::class)->orderBy('position')->orderBy('id');
    }

    public function setting(string $key, $default = null)
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }

    public function isOwnedBy(?string $address): bool
    {
        return $address !== null && strtolower($address) === strtolower($this->owner_address);
    }

    public function editable(): bool
    {
        return in_array($this->status, ['sketch', 'committed'], true);
    }
}
