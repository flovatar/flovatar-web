<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Support\EventsArchiveState;

class Event extends Model
{
    use HasFactory;

    protected $casts = [
        'flow_metadata' => AsCollection::class,
        'synced' => 'boolean',
        'graffle' => 'boolean',
        'event_date' => 'datetime'
    ];
    protected $guarded = [];

    /** Set true only for deliberate re-imports below the archive watermark. */
    public static bool $allowBelowArchiveWatermark = false;

    protected static function booted(): void
    {
        // Legacy writers (Graffle webhook, SyncEvents job) must not re-create events in a block
        // range that was archived to R2 and pruned (they would be applied a second time).
        static::creating(function (Event $event) {
            $watermark = static::$allowBelowArchiveWatermark ? 0 : EventsArchiveState::watermark();
            if ($watermark > 0 && (int) $event->block_id <= $watermark) {
                Log::warning('events: refused insert at/below archive watermark', ['block_id' => $event->block_id, 'type' => $event->type, 'watermark' => $watermark]);

                return false;
            }
        });
    }
}
