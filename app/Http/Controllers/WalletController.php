<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function deposit(Request $request)
    {
        $request->validate([
            'currency' => 'required|string|in:BTC,ETH,USDT,INR',
            'amount'   => 'required|numeric|min:0.00000001',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($request, $user) {
            $wallet = Wallet::firstOrCreate(
                [
                    'user_id'  => $user->id,
                    'currency' => $request->currency,
                ],
                [
                    'balance' => 0,
                    'locked_balance' => 0,
                ]
            );

            $wallet->balance = bcadd((string)$wallet->balance, (string)$request->amount, 8);
            $wallet->save();
        });

        return response()->json([
            'message' => 'Deposit successful'
        ]);
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'currency' => 'required|string|in:BTC,ETH,USDT,INR',
            'amount'   => 'required|numeric|min:0.00000001',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($request, $user) {
            $wallet = Wallet::where('user_id', $user->id)
                ->where('currency', $request->currency)
                ->lockForUpdate()
                ->firstOrFail();

            if (bccomp((string)$wallet->balance, (string)$request->amount, 8) < 0) {
                abort(422, 'Insufficient balance');
            }

            $wallet->balance = bcsub((string)$wallet->balance, (string)$request->amount, 8);
            $wallet->save();
        });

        return response()->json([
            'message' => 'Withdrawal successful'
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $wallets = Wallet::where('user_id', $user->id)
            ->orderBy('currency')
            ->get()
            ->map(function ($wallet) {
                return [
                    'id' => $wallet->id,
                    'currency' => $wallet->currency,
                    'balance' => (float)$wallet->balance,
                    'locked_balance' => (float)$wallet->locked_balance,
                    'available' => (float)bcsub((string)$wallet->balance, (string)$wallet->locked_balance, 8),
                ];
            });

        return response()->json($wallets);
    }
}
