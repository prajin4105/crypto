<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\Trade;
use App\Models\Wallet;

class OrderMatchingService
{
    public function match(Order $order)
    {
        DB::transaction(function () use ($order) {

            if (!in_array($order->status, ['open', 'partial'])) {
                return;
            }

            $oppositeType = $order->type === 'buy' ? 'sell' : 'buy';

            $query = Order::where('market_id', $order->market_id)
                ->where('type', $oppositeType)
                ->whereIn('status', ['open', 'partial']);

            if ($order->type === 'buy') {
                $query->where('price', '<=', $order->price)
                      ->orderBy('price', 'asc');
            } else {
                $query->where('price', '>=', $order->price)
                      ->orderBy('price', 'desc');
            }

            $query->orderBy('created_at', 'asc');

            $matches = $query->lockForUpdate()->get();

            foreach ($matches as $match) {
                // Reload order to get fresh remaining_amount
                $order->refresh();
                $match->refresh();

                if (bccomp((string)$order->remaining_amount, '0', 8) <= 0) {
                    break;
                }

                if (bccomp((string)$match->remaining_amount, '0', 8) <= 0) {
                    continue;
                }

                $tradeAmount = bccomp((string)$order->remaining_amount, (string)$match->remaining_amount, 8) <= 0
                    ? $order->remaining_amount
                    : $match->remaining_amount;

                // Price-time priority: Use the price of the order that was placed first
                // If buy order came first, use buy order price. If sell order came first, use sell order price.
                // This ensures price-time priority matching
                if ($order->type === 'buy') {
                    // Buy order matches with sell orders at sell order price (or better)
                    $tradePrice = $match->price; // Match at sell order price (seller's price)
                } else {
                    // Sell order matches with buy orders at buy order price (or better)
                    $tradePrice = $match->price; // Match at buy order price (buyer's price)
                }

                $this->executeTrade($order, $match, $tradePrice, $tradeAmount);
            }
        });
    }

    private function executeTrade(Order $order, Order $match, float $price, float $amount)
    {
        $buyOrder  = $order->type === 'buy' ? $order : $match;
        $sellOrder = $order->type === 'sell' ? $order : $match;

        // Create trade record
        $feeRate = config('trading.taker_fee', 0.001);
        $total   = bcmul((string)$price, (string)$amount, 8);
        $fee     = bcmul($total, (string)$feeRate, 8);

        Trade::create([
            'buy_order_id'  => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'market_id'     => $buyOrder->market_id,
            'price'         => $price,
            'amount'        => $amount,
            'fee_amount'    => (float)$fee,
            'fee_currency'  => $buyOrder->market->quote,
        ]);

        // Update orders - calculate remaining and filled amounts
        $buyOrder->remaining_amount = bcsub((string)$buyOrder->remaining_amount, (string)$amount, 8);
        $sellOrder->remaining_amount = bcsub((string)$sellOrder->remaining_amount, (string)$amount, 8);
        
        // Calculate filled amounts
        $buyFilled = bcsub((string)$buyOrder->amount, (string)$buyOrder->remaining_amount, 8);
        $sellFilled = bcsub((string)$sellOrder->amount, (string)$sellOrder->remaining_amount, 8);
        
        $buyOrder->filled_amount = $buyFilled;
        $sellOrder->filled_amount = $sellFilled;

        // Update status based on remaining amount
        if (bccomp((string)$buyOrder->remaining_amount, '0', 8) == 0) {
            $buyOrder->status = 'filled';
        } elseif (bccomp($buyFilled, '0', 8) > 0) {
            $buyOrder->status = 'partial';
        }
        
        if (bccomp((string)$sellOrder->remaining_amount, '0', 8) == 0) {
            $sellOrder->status = 'filled';
        } elseif (bccomp($sellFilled, '0', 8) > 0) {
            $sellOrder->status = 'partial';
        }

        $buyOrder->save();
        $sellOrder->save();

        // Update wallets + fees
        $this->updateWallets($buyOrder, $sellOrder, $price, $amount, $fee);
    }

    private function updateWallets(
        Order $buy,
        Order $sell,
        float $price,
        float $amount,
        float $fee
    ) {
        $total = bcmul((string)$price, (string)$amount, 8);
        $feeStr = (string)$fee;
        $amountStr = (string)$amount;

        // Buyer wallets
        $buyerQuote = Wallet::where('user_id', $buy->user_id)
            ->where('currency', $buy->market->quote)
            ->lockForUpdate()
            ->first();

        if (!$buyerQuote) {
            $buyerQuote = Wallet::create([
                'user_id' => $buy->user_id,
                'currency' => $buy->market->quote,
                'balance' => 0,
                'locked_balance' => 0,
                'is_active' => true,
            ]);
            $buyerQuote->lockForUpdate();
        }

        $buyerBase = Wallet::where('user_id', $buy->user_id)
            ->where('currency', $buy->market->base)
            ->lockForUpdate()
            ->first();

        if (!$buyerBase) {
            $buyerBase = Wallet::create([
                'user_id' => $buy->user_id,
                'currency' => $buy->market->base,
                'balance' => 0,
                'locked_balance' => 0,
                'is_active' => true,
            ]);
            $buyerBase->lockForUpdate();
        }

        // Seller wallets
        $sellerBase = Wallet::where('user_id', $sell->user_id)
            ->where('currency', $sell->market->base)
            ->lockForUpdate()
            ->first();

        if (!$sellerBase) {
            $sellerBase = Wallet::create([
                'user_id' => $sell->user_id,
                'currency' => $sell->market->base,
                'balance' => 0,
                'locked_balance' => 0,
                'is_active' => true,
            ]);
            $sellerBase->lockForUpdate();
        }

        $sellerQuote = Wallet::where('user_id', $sell->user_id)
            ->where('currency', $sell->market->quote)
            ->lockForUpdate()
            ->first();

        if (!$sellerQuote) {
            $sellerQuote = Wallet::create([
                'user_id' => $sell->user_id,
                'currency' => $sell->market->quote,
                'balance' => 0,
                'locked_balance' => 0,
                'is_active' => true,
            ]);
            $sellerQuote->lockForUpdate();
        }

        /*
         | ============================================
         | BUYER WALLET UPDATES (CRITICAL LOGIC)
         | ============================================
         | 
         | Example: Buyer orders 0.1 BTC @ 40,000 USDT
         |           Matches at 39,000 USDT
         | 
         | Step 1: Locked amount = 40,000 * 0.1 = 4,000 USDT
         | Step 2: Actual cost = 39,000 * 0.1 = 3,900 USDT
         | Step 3: Release locked: 4,000 USDT
         | Step 4: Refund difference: 4,000 - 3,900 = 100 USDT
         | Step 5: Pay fee on 3,900 USDT
         | Step 6: Credit 0.1 BTC
         */
        $buyerOrderPrice = (string)$buy->price;
        $actualTradeCost = $total; // trade_price * amount
        $lockedAmount = bcmul($buyerOrderPrice, $amountStr, 8); // order_price * amount (what was locked)
        
        // Release the FULL locked amount
        $buyerQuote->locked_balance = bcsub((string)$buyerQuote->locked_balance, $lockedAmount, 8);
        
        // Calculate refund (if buyer got better price)
        if (bccomp($actualTradeCost, $lockedAmount, 8) < 0) {
            // Buyer got better price, refund the difference
            $refund = bcsub($lockedAmount, $actualTradeCost, 8);
            $buyerQuote->balance = bcadd((string)$buyerQuote->balance, $refund, 8);
        } elseif (bccomp($actualTradeCost, $lockedAmount, 8) > 0) {
            // This shouldn't happen in normal matching, but handle it
            // Buyer would need to pay more (shouldn't match)
            $additionalCost = bcsub($actualTradeCost, $lockedAmount, 8);
            // This case means matching logic error, but we'll handle it
            $buyerQuote->balance = bcsub((string)$buyerQuote->balance, $additionalCost, 8);
        }
        // If equal, no refund needed
        
        // Deduct fee from buyer's balance
        $buyerQuote->balance = bcsub((string)$buyerQuote->balance, $feeStr, 8);
        
        // Credit BTC to buyer
        $buyerBase->balance = bcadd((string)$buyerBase->balance, $amountStr, 8);

        /*
         | ============================================
         | SELLER WALLET UPDATES
         | ============================================
         | 
         | Example: Seller orders 0.1 BTC @ 40,000 USDT
         |           Matches at 40,000 USDT
         | 
         | Step 1: Locked amount = 0.1 BTC
         | Step 2: Release locked: 0.1 BTC
         | Step 3: Receive USDT = 40,000 * 0.1 = 4,000 USDT
         | Step 4: Pay fee on 4,000 USDT
         | Step 5: Net receive = 4,000 - fee
         */
        // Release locked BTC
        $sellerBase->locked_balance = bcsub((string)$sellerBase->locked_balance, $amountStr, 8);
        
        // Seller receives USDT minus fee
        $sellerProceeds = bcsub($total, $feeStr, 8);
        $sellerQuote->balance = bcadd((string)$sellerQuote->balance, $sellerProceeds, 8);

        $buyerQuote->save();
        $buyerBase->save();
        $sellerBase->save();
        $sellerQuote->save();

        // Platform fee wallet
        $this->collectFee($fee, $buy->market->quote);
    }

    private function collectFee(float $fee, string $currency)
    {
        if (bccomp((string)$fee, '0', 8) <= 0) {
            return;
        }

        $platformWallet = Wallet::where('user_id', 1)
            ->where('currency', $currency)
            ->lockForUpdate()
            ->first();

        if (!$platformWallet) {
            $platformWallet = Wallet::create([
                'user_id' => 1, // system/admin user
                'currency' => $currency,
                'balance' => 0,
                'locked_balance' => 0,
                'is_active' => true,
            ]);
            $platformWallet->lockForUpdate();
        }

        $platformWallet->balance = bcadd((string)$platformWallet->balance, (string)$fee, 8);
        $platformWallet->save();
    }
}
