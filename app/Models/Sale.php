<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $casts = [
        'completed_at' => 'datetime'
    ];

    protected $guarded = [];

    /**
     * Hide sales where a blocklisted address (2026-11-16 incident) is buyer or seller.
     */
    public function scopeNotBlocked($query)
    {
        $addresses = \App\Support\Blocklist::addresses();
        if (count($addresses) > 0) {
            $query->where(function ($q) use ($addresses) {
                $q->whereNull('sales.flow_buyer_address')->orWhereNotIn('sales.flow_buyer_address', $addresses);
            })->where(function ($q) use ($addresses) {
                $q->whereNull('sales.flow_seller_address')->orWhereNotIn('sales.flow_seller_address', $addresses);
            });
        }
        return $query;
    }

    public function item()
    {
        return $this->morphTo();
    }

    /**
     * ĐUST marketplace: hide ĐUST rows from legacy (FLOW/DUC) views and, while DUST_MARKET_ENABLED is off, from everything.
     */
    public function scopeNotDust($query){
        $table = $this->getTable();
        return $query->where(function ($q) use ($table) {
            $q->whereNull($table.'.currency')->orWhere($table.'.currency', '!=', \App\Support\DustMarket::CURRENCY);
        });
    }

    /** Public lists: currency=all|flow|dust (non-Dapper tab). DUST only when the ĐUST market is enabled. */
    public function scopeCurrencyFilter($query, $currency, $dapper = false){
        if(!\App\Support\DustMarket::enabled()){
            return $this->scopeNotDust($query);
        }
        $currency = strtolower((string) $currency);
        if($dapper){
            return $this->scopeNotDust($query);
        }
        if($currency === 'flow'){
            return $this->scopeNotDust($query);
        }
        if($currency === 'dust'){
            return $query->where($this->getTable().'.currency', \App\Support\DustMarket::CURRENCY);
        }
        return $query;
    }

    /** Public lists that show every currency: ĐUST rows only while DUST_MARKET_ENABLED is on. */
    public function scopeMarketVisible($query){
        return \App\Support\DustMarket::enabled() ? $query : $this->scopeNotDust($query);
    }

    /** Currency code for the API/UI (legacy rows without the column: is_dapper → DUC, else FLOW). */
    public function getCurrencyCodeAttribute(){
        return \App\Support\DustMarket::currencyOf($this);
    }
}
