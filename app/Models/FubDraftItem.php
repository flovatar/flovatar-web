<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** FUB v1 (database/migrations/2026_11_27_100001_create_fub_tables.php). */
class FubDraftItem extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['svg_original'];

    protected $casts = ['report' => 'array', 'large' => 'boolean', 'ai_assisted' => 'boolean', 'price' => 'float', 'increase' => 'float', 'price_cap' => 'float'];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(FubDraft::class, 'fub_draft_id');
    }

    /** Namespace used by the sanitiser for this item's ids/classes. */
    public function ns(): string
    {
        return 'u'.$this->id;
    }
}
