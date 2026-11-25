<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FlovaChat was retired in 2026-11. The table is kept for data retention only;
 * nothing in the app reads or writes it any more.
 */
class GptThread extends Model
{
    use HasFactory;


    protected $casts = [
        'metadata' => AsCollection::class
    ];
    protected $guarded = [];

    public function assistant()
    {
        return $this->belongsTo(GptAssistant::class);
    }
}
