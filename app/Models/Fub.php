<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** FUB v1 (database/migrations/2026_11_27_100001_create_fub_tables.php). */
class Fub extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['layers' => 'array', 'data' => 'array', 'suspended' => 'boolean', 'unique_combos' => 'boolean', 'royalty' => 'float',
        'published_at' => 'datetime', 'checks_passed_at' => 'datetime', 'hidden_at' => 'datetime', 'chain_read_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(FubItem::class, 'fub_flow_id', 'flow_id')->orderBy('flow_id');
    }

    public function funs(): HasMany
    {
        return $this->hasMany(Fun::class, 'fub_flow_id', 'flow_id');
    }

    /** Visible by link / on the island (published, not hidden). */
    public function scopeVisible($q)
    {
        return $q->whereNull('hidden_at')->where('status', '!=', 'building');
    }

    /** Directory (Q12 b): checks passed + report-free for the delay since publish. */
    public function scopeListed($q)
    {
        $delay = now()->subHours((int) config('fub.directory_delay_hours', 48));

        return $q->visible()->where('suspended', false)->whereNotNull('checks_passed_at')->where('published_at', '<=', $delay)
            ->whereNotExists(fn ($s) => $s->selectRaw('1')->from('fub_reports')->whereColumn('fub_reports.fub_flow_id', 'fubs.flow_id')->where('fub_reports.status', 'open'));
    }
}
