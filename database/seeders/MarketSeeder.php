<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Market;

class MarketSeeder extends Seeder
{
    public function run(): void
    {
        $markets = [
            ['symbol' => 'BTCUSDT', 'base' => 'BTC', 'quote' => 'USDT', 'name' => 'Bitcoin'],
            ['symbol' => 'ETHUSDT', 'base' => 'ETH', 'quote' => 'USDT', 'name' => 'Ethereum'],
            ['symbol' => 'BNBUSDT', 'base' => 'BNB', 'quote' => 'USDT', 'name' => 'BNB'],
            ['symbol' => 'SOLUSDT', 'base' => 'SOL', 'quote' => 'USDT', 'name' => 'Solana'],
            ['symbol' => 'XRPUSDT', 'base' => 'XRP', 'quote' => 'USDT', 'name' => 'XRP'],
            ['symbol' => 'ADAUSDT', 'base' => 'ADA', 'quote' => 'USDT', 'name' => 'Cardano'],
        ];

        foreach ($markets as $market) {
            Market::updateOrCreate(
                ['symbol' => $market['symbol']],
                $market
            );
        }
    }
}
