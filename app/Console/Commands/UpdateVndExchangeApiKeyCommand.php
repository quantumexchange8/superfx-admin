<?php

namespace App\Console\Commands;

use App\Models\CurrencyConversionRate;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class UpdateVndExchangeApiKeyCommand extends Command
{
    protected $signature = 'update:vnd-exchange-api-key';

    protected $description = 'Command description';

    /**
     * @throws ConnectionException
     */
    public function handle(): void
    {
        $currency_conversion_rates = CurrencyConversionRate::where('base_currency', 'VND')
            ->get();

        foreach ($currency_conversion_rates as $currency_conversion_rate) {
            $response = Http::acceptJson()->get('https://api.vnappmob.com/api/request_api_key?scope=exchange_rate');

            if ($response->status() === 200) {
                $responseData = $response->json();
                $currency_conversion_rate->update([
                    'api_key' => $responseData['results']
                ]);
            }
        }
    }
}
