<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pack extends Model
{
    use HasFactory;

    protected $casts = [
        'flow_metadata' => AsCollection::class,
        'assigned_at' => 'datetime',
    ];
    protected $guarded = [];


    public function scopeReadyToSell($query){
        // v1 needs a stored signature (made with account key 0); v2 signs a fresh, recipient-bound
        // authorisation at reservation time (FlovatarPackAuthorizer), so only the on-chain pack is needed.
        if (config('flovatar.packsV2Enabled')) {
            return $query->whereNotNull('flow_id')->whereFlowAddress(config('services.flow.address'));
        }
        return $query->whereNotNull('signature')->whereNotNull('flow_id')->whereFlowAddress(config('services.flow.address'));
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
