<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
