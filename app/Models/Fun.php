<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** FUB v1 (database/migrations/2026_11_27_100001_create_fub_tables.php). */
class Fun extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['picks' => 'array', 'minted_at' => 'datetime', 'price' => 'float'];

    public function fub(): BelongsTo
    {
        return $this->belongsTo(Fub::class, 'fub_flow_id', 'flow_id');
    }
}
