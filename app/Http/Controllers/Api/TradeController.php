<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Models\Trade;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    public function index(Request $request, string $symbol)
    {
        $market = Market::where('symbol', $symbol)
            ->where('is_active', true)
            ->firstOrFail();

        $limit = min((int)$request->get('limit', 50), 200); // Max 200 trades

        $trades = Trade::where('market_id', $market->id)
            ->with(['buyOrder', 'sellOrder'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($trade) {
                return [
                    'id' => $trade->id,
                    'price' => (float)$trade->price,
                    'amount' => (float)$trade->amount,
                    'side' => $trade->buyOrder->created_at > $trade->sellOrder->created_at ? 'buy' : 'sell',
                    'time' => $trade->created_at->toDateTimeString(),
                ];
            });

        return response()->json($trades);
    }
}

