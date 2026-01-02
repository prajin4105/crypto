<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BalanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Get all wallets
        $wallets = Wallet::where('user_id', $user->id)
            ->get()
            ->keyBy('currency');

        // Get open orders with market info
        $openOrders = Order::with('market')
            ->where('user_id', $user->id)
            ->whereIn('status', ['open', 'partial'])
            ->get();

        // Get market prices for conversion
        $markets = \App\Models\Market::where('is_active', true)
            ->where('quote', 'USDT')
            ->get()
            ->keyBy('base');

        // Calculate total balance in USDT
        $totalBalance = 0;
        $walletsData = [];

        foreach ($wallets as $wallet) {
            $balance = (float)$wallet->balance;
            $locked = (float)$wallet->locked_balance;
            $available = $balance - $locked;

            // Convert to USDT
            $usdtValue = 0;
            if ($wallet->currency === 'USDT') {
                $usdtValue = $balance;
            } elseif ($markets->has($wallet->currency)) {
                // For other currencies, we'll use order price as fallback
                // Frontend will update with live prices via WebSocket
                $market = $markets->get($wallet->currency);
                // Default to 0, frontend will calculate with live prices
                $usdtValue = 0;
            }

            $walletsData[$wallet->currency] = [
                'currency' => $wallet->currency,
                'balance' => $balance,
                'locked_balance' => $locked,
                'available' => $available,
                'usdt_value' => $usdtValue,
            ];

            $totalBalance += $usdtValue;
        }

        // Calculate unrealized P/L from open orders
        $unrealizedPL = 0;
        $ordersData = [];

        foreach ($openOrders as $order) {
            $market = $order->market;
            $remaining = (float)$order->remaining_amount;
            $orderPrice = (float)$order->price;

            // This will be updated by frontend with current market price
            $currentPrice = $orderPrice; // Default to order price if no current price

            $ordersData[] = [
                'id' => $order->id,
                'symbol' => $market->symbol,
                'type' => $order->type,
                'price' => $orderPrice,
                'remaining_amount' => $remaining,
                'market_price' => $currentPrice, // Will be updated by frontend
            ];
        }

        return response()->json([
            'wallets' => array_values($walletsData),
            'open_orders' => $ordersData,
            'total_balance' => $totalBalance,
            'unrealized_pl' => $unrealizedPL, // Calculated on frontend with live prices
        ]);
    }
}

