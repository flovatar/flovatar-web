<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** FUB v1 (database/migrations/2026_11_27_100001_create_fub_tables.php). */
class FubItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['metadata' => 'array', 'live' => 'boolean', 'suspended' => 'boolean', 'hidden_at' => 'datetime',
        'price' => 'float', 'current_price' => 'float', 'increase' => 'float', 'price_cap' => 'float'];

    protected $hidden = ['svg'];
}
