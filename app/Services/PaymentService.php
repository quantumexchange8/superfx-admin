<?php

namespace App\Services;

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
    public function proceedPayout($payment_gateway, $transaction)
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
                    'notify_url' => route('transaction_callback'),
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
                    'amount' => number_format($transaction->conversion_amount, 2, '.', ''),
                    'bankName' => $transaction->bank_code,
                    'accountNumber' => $transaction->payment_account_no,
                    'accountName' => $transaction->payment_account_name,
                    'description' => 'payout',
                    'merchantRefNo' => $transaction->transaction_number,
                    'callbackUrl' => route('zpay_payout_callback'),
                ];

                $scaled_amount = $params['amount'] * pow(10, 2);

                $rawString = "{$params['merchantCode']}&{$params['merchantKey']}&{$params['currency']}&{$params['merchantRefNo']}&{$params['callbackUrl']}&$scaled_amount";

                $signature = strtoupper(hash('sha256', $rawString));

                $params['signature'] = $signature;

                $url = $payment_gateway->payment_url . '/createPayoutV2';

                // Log request details (like a curl command)
                Log::info('Withdraw request', [
                    'url'    => $url,
                    'params' => $params,
                ]);

                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new Exception("Invalid URL: " . $url);
                }

                // Send request
                $response = Http::asForm()->post($url, $params);

                // Log full raw response
                Log::info("ZPay Raw Response: " . $response->body());

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

                $errorMsg = $responseData['message']
                    ?? 'Unknown gateway error';

                throw new Exception($errorMsg);

            case 'pay-superfin':
                $params = [
                    'version'  => "2.0",
                    'merId'  => $payment_gateway->payment_app_number,
                    'seqId' => $transaction->transaction_number,
                    'amount' => (string) $transaction->conversion_amount,
                    'notifyUrl' => route('psp_payout_callback'),
                    'receiveName' => $transaction->payment_account_name,
                    'cardNo' => $transaction->payment_account_no,
                    'bankName' => $transaction->bank_code,
                ];

                // Remove null/empty values
                $filtered = array_filter($params, fn($v) => $v !== null && $v !== '');

                // Sort by key (ASCII order)
                ksort($filtered);

                $stringA = urldecode(http_build_query($filtered));

                $privateKeyPath = storage_path('app/keys/psp_private.pem');
                $privateKey = file_get_contents($privateKeyPath);

                if (!$privateKey) {
                    throw new Exception('Private key file not found or unreadable.');
                }

                $signature = null;
                $success = openssl_sign($stringA, $signature, $privateKey);

                if (!$success) {
                    throw new Exception('Failed to generate RSA signature.');
                }

                // Encode signature (usually Base64 for transmission)
                $params['sign'] = base64_encode($signature);

                $response = Http::asForm()
                    ->post("$payment_gateway->payment_url/ttpaywd", $params);

                $responseData = $response->json();

                Log::info('PSP Pay response code: ' . $responseData['code']);
                Log::info('PSP Pay response msg: ' . $responseData['msg']);

                if (isset($responseData['code']) && $responseData['code'] == "0000") {
                    $responseData['code'] = 200;
                    // success → get the URL from data
                    $payment = $responseData;

                    if ($payment) {
                        break;
                    }
                }

                $transaction->update([
                    'status' => 'failed',
                    'approved_at' => now()
                ]);

                // error case → throw exception with message
                $errorMsg = $responseData['msg']
                    ?? $responseData['message']
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
                    'notify_url' => route('transaction_callback'),
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
