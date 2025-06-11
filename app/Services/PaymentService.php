<?php

namespace App\Services;

use App\Models\CurrencyConversionRate;
use App\Models\PaymentGateway;
use Carbon\Carbon;
use Exception;
use Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Log;
use Str;

class PaymentService
{
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

        if ($transaction->payment_platform === 'bank') {
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

        // Delegate to specific handler
        $response = $this->handleBankPayment($transaction, $paymentGateway, $conversionAmount);
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
        // Login - get token
        $accessToken = $this->getAuthToken($paymentGateway);

        if (!$accessToken) {
            throw new Exception("Unable to retrieve access token from GatewayB");
        }

        $params = [
            'auditNumber' => $transaction->transaction_number,
            'amount' => $conversionAmount,
            'bankId'    => $transaction->bank_code,
            'bankRefNumber'    => $transaction->payment_account_no,
            'bankRefName'    => $transaction->payment_account_name,
            'bankCode'    => $transaction->payment_account_name,
            'content'    => 'Withdraw',
        ];

        $params['signature'] = sha1(json_encode($params) . $paymentGateway->payment_app_key);

        $url = $paymentGateway->payment_url . '/merchant-transaction-service/api/v2.0/transfer_247';

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
        ];

        return Http::withHeaders($headers)->post($url, $params);
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    protected function getAuthToken($paymentGateway)
    {
        $loginUrl = $paymentGateway->payment_url . '/auth-service/api/v1.0/user/login';

        $username = 'm122user1';
        $password = hash('sha256', $username . '2025@SuperFin');
        $requestTime = now('Asia/Ho_Chi_Minh')->format('YmdHis');
        $requestId = (string) Str::uuid();
        $tenant = 'SUPERFINFX';

        $headers = [
            'p-request-id'  => $requestId,
            'p-request-time'=> $requestTime,
            'p-tenant'      => $tenant,
        ];

        $params = [
            'username' => $username,
            'password' => base64_encode($password),
        ];

        $privateKeyPath = public_path('keys/private.pem');

        $signature = $this->createSignature($headers, $params, $privateKeyPath);

        // Then attach signature to your headers
        $headers['p-signature'] = $signature;

        $response = Http::withHeaders($headers)->post($loginUrl, $params);
        $responseData = $response->json();

        Log::info('Login response: ', $responseData);

        if (isset($responseData['code']) && $responseData['code'] === 'SUCCESS') {
            return $responseData['accessToken'];
        }

        Log::error('Failed to get access token', ['response' => $responseData]);
        return null;
    }

    /**
     * @throws Exception
     */
    protected function createSignature(array $headers, array $body, $privateKeyPath)
    {
        // Step 1: Filter and sort headers
        $filteredHeaders = collect($headers)
            ->filter(function ($value, $key) {
                return $key === 'authorization'
                    || $key === 'verification'
                    || Str::startsWith($key, 'p-');
            })
            ->sortKeys();

        // Concatenate header values
        $headerString = $filteredHeaders->implode('');

        // Step 2: Combine with request body
        $bodyString = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stringToSign = $headerString . $bodyString;

        // Step 3: Sign the string with RSA private key (SHA256withRSA)
        $privateKey = file_get_contents($privateKeyPath);

        if (!$privateKey) {
            throw new Exception('Private key file not found or unreadable.');
        }

        Log::info('Private Key : ', (array)$privateKey);
        Log::info('Sign Request Header : ' . $headerString);
        Log::info('Sign Request Body : ' . $bodyString);
        Log::info('String to sign : ' . $stringToSign);
        $signature = '';
        $success = openssl_sign($stringToSign, $signature, $privateKey, 'RSA-SHA256');

        if (!$success) {
            throw new Exception('Failed to generate RSA signature.');
        }

        // Return base64-encoded signature
        return base64_encode($signature);
    }

    protected function handleResponse($response, $paymentGateway)
    {
        $responseData = $response->json();
        Log::debug('Payment Gateway Response:', $responseData);

        $code = $responseData['code'] ?? null;

        $isSuccess = match ($paymentGateway->payment_app_name) {
            'GatewayA' => $code == 200,
            'GatewayB' => $code === 'SUCCESS',
            default   => false,
        };

        return [
            'success' => $isSuccess,
            'message' => $responseData['msg'] ?? 'Payment gateway error',
        ];
    }
}
