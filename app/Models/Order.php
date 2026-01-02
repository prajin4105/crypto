<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'market_id',
        'type',              // buy | sell
        'price',
        'amount',
        'remaining_amount',
        'filled_amount',     // amount - remaining_amount (for quick queries)
        'status',            // open | partial | filled | cancelled
    ];

    protected $casts = [
        'price' => 'decimal:8',
        'amount' => 'decimal:8',
        'remaining_amount' => 'decimal:8',
        'filled_amount' => 'decimal:8',
    ];

    public function trades()
    {
        return Trade::where('buy_order_id', $this->id)
            ->orWhere('sell_order_id', $this->id);
    }

    public function buyTrades()
    {
        return $this->hasMany(Trade::class, 'buy_order_id');
    }

    public function sellTrades()
    {
        return $this->hasMany(Trade::class, 'sell_order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function market()
    {
        return $this->belongsTo(Market::class);
    }
}
