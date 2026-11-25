<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FlovaChat was retired in 2026-11. The table is kept for data retention only;
 * nothing in the app reads or writes it any more.
 */
class GptAssistant extends Model
{
    use HasFactory;

    protected $casts = [
        'metadata' => AsCollection::class,
        'suggestions' => AsCollection::class,
    ];
    protected $guarded = [];


    public function threads()
    {
        return $this->hasMany(GptThread::class);
    }
    public function flovatar()
    {
        return $this->hasOne(Flovatar::class, 'flow_id', 'flow_id');
    }
}
