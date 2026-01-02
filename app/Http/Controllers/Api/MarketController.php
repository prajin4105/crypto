<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Market;

class MarketController extends Controller
{
    public function index()
    {
        try {
            $markets = Market::where('is_active', true)
                ->orderBy('symbol')
                ->get([
                    'id',
                    'symbol',
                    'name',
                    'base',
                    'quote',
                ]);

            return response()->json($markets);
        } catch (\Exception $e) {
            \Log::error('Markets endpoint error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch markets',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
