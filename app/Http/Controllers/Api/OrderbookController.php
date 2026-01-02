<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderbookController extends Controller
{
    public function show(Request $request, string $symbol)
    {
        $market = Market::where('symbol', $symbol)
            ->where('is_active', true)
            ->firstOrFail();

        $limit = min((int)$request->get('limit', 20), 100); // Max 100 orders per side

        // Buy orders (bids) - highest price first
        $bids = Order::where('market_id', $market->id)
            ->where('type', 'buy')
            ->whereIn('status', ['open', 'partial'])
            ->orderByDesc('price')
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->groupBy('price')
            ->map(function ($orders) {
                $totalAmount = $orders->sum('remaining_amount');
                return [
                    'price' => (float)$orders->first()->price,
                    'amount' => (float)$totalAmount,
                ];
            })
            ->values();

        // Sell orders (asks) - lowest price first
        $asks = Order::where('market_id', $market->id)
            ->where('type', 'sell')
            ->whereIn('status', ['open', 'partial'])
            ->orderBy('price')
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->groupBy('price')
            ->map(function ($orders) {
                $totalAmount = $orders->sum('remaining_amount');
                return [
                    'price' => (float)$orders->first()->price,
                    'amount' => (float)$totalAmount,
                ];
            })
            ->values();

        return response()->json([
            'symbol' => $symbol,
            'bids' => $bids,
            'asks' => $asks,
        ]);
    }
}

