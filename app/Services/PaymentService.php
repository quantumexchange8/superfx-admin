<?php

namespace App\Services;

use App\Models\CurrencyConversionRate;
use App\Models\PaymentGateway;
use Carbon\Carbon;
use Exception;
use Http;
use Illuminate\Http\Client\ConnectionException;
use Log;
use Str;

class PaymentService
{
    private string $tenant = 'SUPERFINFX';
    private string $username = 'm122user1';
    private string $password = '2025@SuperFin';
    private string $passcode = '668899';

    /**
     * @throws Exception
     */
    public function processTransaction($transaction)
    {
        $environment = in_array(app()->environment(), ['local', 'staging']) ? 'staging' : 'production';

        $paymentGateway = PaymentGateway::where([
            'id' => $transaction->payment_gateway_id,
            'platform' => $transaction->payment_platform,
            'environment' => $environment,
        ])->first();

        if (!$paymentGateway) {
            return back()->with('toast', [
                'title' => 'Payment gateway not found.',
                'type' => 'error',
            ]);
        }

        // Common param building
        $conversionAmount = $transaction->transaction_amount;
        $conversionRate = null;

        if ($transaction->payment_platform == 'bank') {
            $conversionRate = CurrencyConversionRate::firstWhere('base_currency', 'VND');
            if ($conversionRate) {
                $conversionAmount = round($conversionAmount * $conversionRate->withdrawal_rate);
            }
        } else {
            $transaction->update([
                'from_currency' => 'USD',
                'to_currency' => 'USD',
            ]);
        }

        $transaction->update([
            'conversion_rate' => $conversionRate?->withdrawal_rate,
            'conversion_amount' => $conversionAmount,
        ]);

        $response = $this->handleBankPayment($transaction, $paymentGateway, $conversionAmount);
        // Delegate to specific handler
//        if ($transaction->payment_platform == 'bank') {
//            $response = $this->handleBankPayment($transaction, $paymentGateway, $conversionAmount);
//        } else {
//            $response = $this->handleCryptoPayment($transaction, $paymentGateway, $conversionAmount);
//        }
        return $this->handleResponse($response, $paymentGateway);
    }

    /**
     * @throws Exception
     */
    protected function handleBankPayment($transaction, $paymentGateway, $conversionAmount)
    {
        // You can switch based on payment_app_name to different method
        return match ($paymentGateway->payment_app_name) {
            'payme-bank' => $this->processGatewayA($transaction, $paymentGateway, $conversionAmount),
            'payment-hot' => $this->processGatewayB($transaction, $paymentGateway, $conversionAmount),
            default => throw new Exception("Unsupported bank gateway: " . $paymentGateway->payment_app_name),
        };
    }

    protected function handleCryptoPayment($transaction, $paymentGateway, $conversionAmount)
    {
//        $params = array_merge($params, [
//            'channel_code' => $transaction->payment_account_type,
//            'address' => $transaction->payment_account_no,
//        ]);
//
//        $data = [
//            $params['partner_id'],
//            $params['timestamp'],
//            $params['random'],
//            $params['partner_order_code'],
//            $params['channel_code'],
//            $params['address'],
//            $params['amount'],
//            $params['notify_url'],
//            '',
//            '',
//            $payment_gateway->payment_app_key,
//        ];
//
//        $baseUrl = $payment_gateway->payment_url . '/gateway/usdt/transfer.do';
    }

    protected function processGatewayA($transaction, $paymentGateway, $conversionAmount)
    {
        $params = [
            'partner_id' => $paymentGateway->payment_app_number,
            'timestamp' => Carbon::now()->timestamp,
            'random' => Str::random(14),
            'partner_order_code' => $transaction->transaction_number,
            'amount' => $conversionAmount,
            'notify_url' => route('transactionCallback'),
            'payee_bank_code' => $transaction->bank_code,
            'payee_bank_account_type' => $transaction->payment_account_type,
            'payee_bank_account_no' => $transaction->payment_account_no,
            'payee_bank_account_name' => $transaction->payment_account_name,
        ];

        $data = [
            $params['partner_id'],
            $params['timestamp'],
            $params['random'],
            $params['partner_order_code'],
            $params['amount'],
            $params['payee_bank_code'],
            $params['payee_bank_account_type'],
            $params['payee_bank_account_no'],
            $params['payee_bank_account_name'],
            '',
            '',
            $paymentGateway->payment_app_key,
        ];

        $params['sign'] = md5(implode(':', $data));

        $url = $paymentGateway->payment_url . '/gateway/bnb/transferATM.do';
        return Http::post($url, $params);
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    protected function processGatewayB($transaction, $paymentGateway, $conversionAmount)
    {
        $requestTime = now('Asia/Ho_Chi_Minh')->format('YmdHis');

        // Login - get token
        $accessToken = $this->getAuthToken($paymentGateway, $requestTime);

        if (!$accessToken) {
            throw new Exception("Unable to retrieve access token from Payment Hot");
        }

        // Implore Transfer - get verified key
        $verifiedKey = $this->getVerifiedKey($paymentGateway, $requestTime, $accessToken);

        $params = [
            'audit' => $transaction->transaction_number,
            'amount' => $conversionAmount,
            'bankId'    => $transaction->bank_code,
            'bankRefNumber'    => $transaction->payment_account_no,
            'bankRefName'    => $transaction->payment_account_name,
            'bankCode'    => $transaction->bank_bin_code,
            'content'    => 'Withdraw',
        ];

        $url = $paymentGateway->payout_url . '/merchant-transaction-service/api/v2.0/transfer_247';

        $headers = [
            'p-request-id'  => (string) Str::uuid(),
            'p-request-time'=> now('Asia/Ho_Chi_Minh')->format('YmdHis'),
            'p-tenant'      => $this->tenant,
            'Authorization' => 'Bearer ' . $accessToken,
            'verification' => $verifiedKey,
        ];

        $privateKeyPath = storage_path('app/keys/private.pem');

        $signature = $this->createSignature($headers, $params, $privateKeyPath);
        $headers['p-signature'] = $signature;

        Log::info('Transfer 24/7 header: ', $headers);
        Log::info('Transfer 24/7 body: ', $params);

        return Http::withHeaders($headers)->post($url, $params);
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    protected function getAuthToken($paymentGateway, $requestTime)
    {
        $loginUrl = $paymentGateway->payout_url . '/auth-service/api/v1.0/user/login';

        $hash_password = hash('sha256', $this->username . $this->password);

        $headers = [
            'p-request-id'  => (string) Str::uuid(),
            'p-request-time'=> $requestTime,
            'p-tenant'      => $this->tenant,
        ];

        $params = [
            'username' => $this->username,
            'password' => base64_encode($hash_password),
        ];

        $privateKeyPath = storage_path('app/keys/private.pem');

        $signature = $this->createSignature($headers, $params, $privateKeyPath);

        // Then attach signature to your headers
        $headers['p-signature'] = $signature;

        $response = Http::withHeaders($headers)->post($loginUrl, $params);
        $responseData = $response->json();

        Log::info('Login response: ', $responseData);

        if (isset($responseData['code']) && $responseData['code'] === 'SUCCESS') {
            return $responseData['data']['accessToken'];
        }

        Log::error('Failed to get access token', ['response' => $responseData]);
        return null;
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    protected function getVerifiedKey($paymentGateway, $requestTime, $accessToken)
    {
        $implore_url = $paymentGateway->payout_url . '/auth-service/api/v1.0/implore-auth';

        $headers = [
            'p-request-id'  => (string) Str::uuid(),
            'p-request-time'=> $requestTime,
            'p-tenant'      => $this->tenant,
            'Authorization' => 'Bearer ' . $accessToken,
        ];

        $params = [
            'authValue' => base64_encode(hash('sha256', $this->username . $this->passcode)),
            'phone' => $this->username,
            'api' => '/merchant-transaction-service/api/v2.0/transfer_247',
            'authMode' => 'PASSCODE',
        ];

        $privateKeyPath = storage_path('app/keys/private.pem');

        $signature = $this->createSignature($headers, $params, $privateKeyPath);

        // Then attach signature to your headers
        $headers['p-signature'] = $signature;

        Log::info('Implore Header: ', $headers);
        Log::info('Implore Body: ', $params);

        $curlCommand = "curl -X POST '$implore_url'";

// Append headers
        foreach ($headers as $key => $value) {
            $curlCommand .= " -H '$key: $value'";
        }

// Append JSON body
        $curlCommand .= " -d '" . json_encode($params) . "'";

// Log it
        Log::info("Outgoing HTTP request (curl style): " . $curlCommand);
        // Ensure Content-Type is application/json
        $headers['Content-Type'] = 'application/json';

// Log outgoing request
        Log::info('Sending HTTP POST to ' . $implore_url);
        Log::info('Request headers: ', $headers);
        Log::info('Request body: ', $params);

// Send POST request with JSON body
        $response = Http::withHeaders($headers)->post($implore_url, $params);

// Log response status + body
        Log::info('Implore response status: ' . $response->status());
        Log::info('Implore response body: ' . $response->body());

// Try to decode response JSON
        $responseData = $response->json();
        Log::info('Implore response data: ', $responseData);

        if (isset($responseData['code']) && $responseData['code'] === 'SUCCESS') {
            return $responseData['data']['verifiedKey'];
        }

        Log::error('Failed to get verified key', ['response' => $responseData]);
        return null;
    }

    /**
     * @throws Exception
     */
    protected function createSignature(array $headers, array $body, $privateKeyPath)
    {
        // Step 1: Filter headers
        $filteredHeaders = collect($headers)->filter(function ($value, $key) {
            return $key == 'Authorization'
                || $key == 'verification'
                || Str::startsWith($key, 'p-');
        });

        // Step 2: Sort headers with custom order
        $sortedHeaders = $filteredHeaders->sortKeysUsing(function ($a, $b) {
            $priority = [
                'Authorization' => 1,
                'verification'  => 2,
            ];

            $aPriority = $priority[$a] ?? 3;
            $bPriority = $priority[$b] ?? 3;

            // If both are p- prefixed headers, sort alphabetically
            if ($aPriority == 3 && $bPriority == 3) {
                return strcmp($a, $b);
            }

            // Sort by defined priority
            return $aPriority <=> $bPriority;
        });

        // Log the sorted headers before hashing
        Log::info('Sorted headers for hashing:', $sortedHeaders->toArray());

        // Concatenate header values
        $headerString = $sortedHeaders->implode('');

        // Step 2: Combine with request body
        $bodyString = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stringToSign = $headerString . $bodyString;

        // Log the string before hashing
        Log::info('string for RSA hashing: ' . $stringToSign);

        // Step 3: Sign the string with RSA private key (SHA256withRSA)
        $privateKey = file_get_contents($privateKeyPath);

        if (!$privateKey) {
            throw new Exception('Private key file not found or unreadable.');
        }

        $signature = '';
        $success = openssl_sign($stringToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (!$success) {
            throw new Exception('Failed to generate RSA signature.');
        }

        // Return base64-encoded signature
        return base64_encode($signature);
    }

    protected function handleResponse($response, $paymentGateway)
    {
        Log::debug('Payment hot IPN Response:', $response);
        $responseData = $response->json();
        Log::debug('Payment Gateway Response:', $responseData);

        $code = $responseData['code'] ?? null;

        $isSuccess = match ($paymentGateway->payment_app_name) {
            'payme-bank' => $code == 200,
            'payment-hot' => $code == 'SUCCESS',
            default   => false,
        };

        return [
            'success' => $isSuccess,
            'message' => $responseData['msg'] ?? 'Payment gateway error',
        ];
    }
}
