<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\Market;
use App\Models\Wallet;
use App\Models\Trade;
use App\Services\OrderMatchingService;
use App\Http\Requests\StoreOrderRequest;

class OrderController extends Controller
{
public function index(Request $request)
{
    $user = $request->user();

        $orders = Order::with(['market', 'buyTrades', 'sellTrades'])
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(function ($order) {
                // Get all trades for this order
                $trades = Trade::where('buy_order_id', $order->id)
                    ->orWhere('sell_order_id', $order->id)
                    ->get();

                // Calculate average fill price and filled amount
                $filledAmount = 0;
                $totalValue = 0;
                $totalFees = 0;

                foreach ($trades as $trade) {
                    $tradeAmount = (float)$trade->amount;
                    $tradePrice = (float)$trade->price;
                    $filledAmount += $tradeAmount;
                    $totalValue += $tradeAmount * $tradePrice;
                    
                    // Add fees (both buyer and seller pay fees)
                    if ($trade->buy_order_id == $order->id || $trade->sell_order_id == $order->id) {
                        $totalFees += (float)($trade->fee_amount ?? 0);
                    }
                }

                $averageFillPrice = $filledAmount > 0 ? $totalValue / $filledAmount : 0;
                $filled = (float)bcsub((string)$order->amount, (string)$order->remaining_amount, 8);

                return [
                    'id'        => $order->id,
                    'symbol'    => $order->market?->symbol ?? 'N/A',
                    'type'      => $order->type,
                    'price'     => (float) $order->price,
                    'amount'    => (float) $order->amount,
                    'filled'    => $filled,
                    'remaining' => (float) $order->remaining_amount,
                    'status'    => $order->status,
                    'created_at'=> $order->created_at->toDateTimeString(),
                    'average_fill_price' => $averageFillPrice,
                    'total_fees' => $totalFees,
                ];
            });

        return response()->json($orders);
}


    public function store(StoreOrderRequest $request)
    {
        $user = $request->user();

        $market = Market::where('symbol', $request->symbol)
            ->where('is_active', true)
            ->firstOrFail();

        return DB::transaction(function () use ($request, $user, $market) {
            if ($request->type === 'buy') {
                $order = $this->handleBuy($user, $market, $request);
            } else {
                $order = $this->handleSell($user, $market, $request);
            }

            // Matching engine call
            app(OrderMatchingService::class)->match($order);

            return response()->json([
                'message' => 'Order placed successfully',
                'order' => [
                    'id' => $order->id,
                    'symbol' => $market->symbol,
                    'type' => $order->type,
                    'price' => (float)$order->price,
                    'amount' => (float)$order->amount,
                    'remaining_amount' => (float)$order->remaining_amount,
                    'status' => $order->status,
                ]
            ], 201);
        });
    }

    private function handleBuy($user, $market, $request)
    {
        $totalCost = bcmul((string)$request->price, (string)$request->amount, 8);

        $wallet = Wallet::where('user_id', $user->id)
            ->where('currency', $market->quote)
            ->lockForUpdate()
            ->first();

        if (!$wallet) {
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'currency' => $market->quote,
                'balance' => 0,
                'locked_balance' => 0,
                'is_active' => true,
            ]);
            $wallet->lockForUpdate();
        }

        if (bccomp((string)$wallet->balance, $totalCost, 8) < 0) {
            abort(422, 'Insufficient balance');
        }

        // Lock USDT
        $wallet->balance = bcsub((string)$wallet->balance, $totalCost, 8);
        $wallet->locked_balance = bcadd((string)$wallet->locked_balance, $totalCost, 8);
        $wallet->save();

        return Order::create([
            'user_id'          => $user->id,
            'market_id'        => $market->id,
            'type'             => 'buy',
            'price'            => $request->price,
            'amount'           => $request->amount,
            'remaining_amount' => $request->amount,
            'filled_amount'    => 0,
            'status'           => 'open',
        ]);
    }

    private function handleSell($user, $market, $request)
    {
        $wallet = Wallet::where('user_id', $user->id)
            ->where('currency', $market->base)
            ->lockForUpdate()
            ->first();

        if (!$wallet) {
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'currency' => $market->base,
                'balance' => 0,
                'locked_balance' => 0,
                'is_active' => true,
            ]);
            $wallet->lockForUpdate();
        }

        if (bccomp((string)$wallet->balance, (string)$request->amount, 8) < 0) {
            abort(422, 'Insufficient balance');
        }

        // Lock base currency
        $wallet->balance = bcsub((string)$wallet->balance, (string)$request->amount, 8);
        $wallet->locked_balance = bcadd((string)$wallet->locked_balance, (string)$request->amount, 8);
        $wallet->save();

        return Order::create([
            'user_id'          => $user->id,
            'market_id'        => $market->id,
            'type'             => 'sell',
            'price'            => $request->price,
            'amount'           => $request->amount,
            'remaining_amount' => $request->amount,
            'filled_amount'    => 0,
            'status'           => 'open',
        ]);
    }
    private function refundSellOrder(Order $order, $market)
    {
        $refund = (string)$order->remaining_amount;

        if (bccomp($refund, '0', 8) <= 0) {
            return;
        }

        $wallet = Wallet::where('user_id', $order->user_id)
            ->where('currency', $market->base)
            ->lockForUpdate()
            ->first();

        if (!$wallet) {
            return; // Wallet should exist, but handle gracefully
        }

        $wallet->locked_balance = bcsub((string)$wallet->locked_balance, $refund, 8);
        $wallet->balance = bcadd((string)$wallet->balance, $refund, 8);
        $wallet->save();
    }

    private function refundBuyOrder(Order $order, $market)
    {
        if (bccomp((string)$order->remaining_amount, '0', 8) <= 0) {
            return;
        }

        $refundUsdt = bcmul((string)$order->remaining_amount, (string)$order->price, 8);

        $wallet = Wallet::where('user_id', $order->user_id)
            ->where('currency', $market->quote)
            ->lockForUpdate()
            ->first();

        if (!$wallet) {
            return; // Wallet should exist, but handle gracefully
        }

        $wallet->locked_balance = bcsub((string)$wallet->locked_balance, $refundUsdt, 8);
        $wallet->balance = bcadd((string)$wallet->balance, $refundUsdt, 8);
        $wallet->save();
    }


    public function cancel(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::where('id', $id)
            ->where('user_id', $user->id)
            ->with('market')
            ->firstOrFail();

        if (!in_array($order->status, ['open', 'partial'])) {
            return response()->json([
                'message' => 'Order cannot be cancelled'
            ], 422);
        }

        return DB::transaction(function () use ($order) {
            $market = $order->market;

            if ($order->type === 'buy') {
                $this->refundBuyOrder($order, $market);
            } else {
                $this->refundSellOrder($order, $market);
            }

            $order->status = 'cancelled';
            $order->save();

            return response()->json([
                'message' => 'Order cancelled successfully',
                'order' => [
                    'id' => $order->id,
                    'symbol' => $order->market?->symbol ?? 'N/A',
                    'type' => $order->type,
                    'status' => $order->status,
                ]
            ]);
        });
    }

}
