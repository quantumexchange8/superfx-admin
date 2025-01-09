<?php

namespace App\Console\Commands;

use App\Models\CurrencyConversionRate;
use AshAllenDesign\LaravelExchangeRates\Classes\ExchangeRate;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class UpdateExchangeRateCommand extends Command
{
    protected $signature = 'update:exchange-rate';

    protected $description = 'Update latest exchange rates daily';

    /**
     * @throws ConnectionException
     */
    public function handle(): void
    {
        $currencies = CurrencyConversionRate::whereNot('base_currency', 'USDT')
            ->get();

        foreach ($currencies as $currency) {
            if ($currency->api_key) {
                $response = Http::acceptJson()
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $currency->api_key,
                    ])
                    ->get($currency->api_host)
                    ->json();

                $results = $response['results'];

                foreach ($results as $result) {
                    if ($result['currency'] == $currency->target_currency) {
                        $currency->update([
                            'deposit_rate' => $result['sell'],
                            'withdrawal_rate' => $result['buy_transfer'],
                        ]);

                        break;
                    }
                }
            }
        }
    }
}
