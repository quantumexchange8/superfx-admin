<?php

namespace App\Services;

use Auth;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function getPaymentUrl($payment_gateway, $transaction)
    {
        $payment_app_name = $payment_gateway->payment_app_name;

        switch ($payment_app_name) {
            case 'payme-bank':
                $params = [
                    'partner_id' => $payment_gateway->payment_app_number,
                    'timestamp' => Carbon::now()->timestamp,
                    'random' => Str::random(14),
                    'partner_order_code' => $transaction->transaction_number,
                    'amount' => $transaction->conversion_amount,
                    'payee_bank_code' => $transaction->bank_code,
                    'payee_bank_account_type' => $transaction->payment_account_type,
                    'payee_bank_account_no' => $transaction->payment_account_no,
                    'payee_bank_account_name' => $transaction->payment_account_name,
                    '',
                    '',
                    $payment_gateway->payment_app_key,
                ];

                $hashedCode = md5(implode(':', $params));
                $params['sign'] = $hashedCode;

                $baseUrl = $payment_gateway->payment_url . '/gateway/bnb/transferATM.do';

                $payment = $this->buildAndRequestUrl($baseUrl, $params);
                break;

            case 'zpay':
                $params = [
                    'merchantCode'  => $payment_gateway->payment_app_number,
                    'merchantKey' => $payment_gateway->payment_app_key,
                    'currency' => 'VND',
                    'amount' => $transaction->conversion_amount,
                    'bankName' => $transaction->conversion_amount,
                    'accountNumber' => $transaction->payment_account_no,
                    'accountName' => $transaction->payment_account_name,
                    'description' => 'payout',
                    'merchantRefNo' => $transaction->transaction_number,
                    'callbackUrl' => route('zpay_payout_callback'),
                    'bankType' => 'BANK_QR',
                    'remark' => 'Deposit'
                ];

                $scaled_amount = $params['amount'] * pow(10, 2);

                $rawString = "{$params['merchantCode']}&{$params['merchantKey']}&{$params['currency']}&{$params['merchantRefNo']}&{$params['callbackUrl']}&$scaled_amount";

                $signature = strtoupper(hash('sha256', $rawString));

                $params['signature'] = $signature;

                $response = Http::asForm()
                    ->post("$payment_gateway->payment_url/createPayoutV2", $params);

                $responseData = $response->json();

                if (isset($responseData['status']) && $responseData['status'] == 200) {
                    // success → get the URL from data
                    $responseData['code'] = $responseData['status'];
                    $payment = $responseData;

                    if ($payment) {
                        break;
                    }
                }

                Log::info('ZPay response status: ' . $responseData['status']);
                Log::info('ZPay response message: ' . $responseData['message']);

                $transaction->update([
                    'status' => 'failed',
                    'approved_at' => now()
                ]);

                $errorMsg = $responseData['message']
                    ?? 'Unknown gateway error';

                throw new Exception($errorMsg);

            default:
                $params = [
                    'partner_id' => $payment_gateway->payment_app_number,
                    'timestamp' => Carbon::now()->timestamp,
                    'random' => Str::random(14),
                    'partner_order_code' => $transaction->transaction_number,
                    'channel_code' => $transaction->payment_account_type,
                    'address' => $transaction->payment_account_no,
                    'amount' => $transaction->conversion_amount,
                    'notify_url' => route('transactionCallback'),
                    '',
                    '',
                    $payment_gateway->payment_app_key,
                ];

                $hashedCode = md5(implode(':', $params));
                $params['sign'] = $hashedCode;

                $baseUrl = $payment_gateway->payment_url . '/gateway/usdt/transfer.do';

                $payment = $this->buildAndRequestUrl($baseUrl, $params);
                break;
        }

        return $payment;
    }

    /**
     * @throws ConnectionException
     */
    private function buildAndRequestUrl($baseUrl, $params)
    {
        $response = Http::post($baseUrl, $params);
        return $response->json();
    }
}
