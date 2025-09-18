<?php

namespace App\Services;

use Throwable;
use App\Models\User as UserModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Services\Data\CreateTradingUser;
use App\Services\Data\UpdateTradingUser;
use App\Services\Data\CreateTradingAccount;
use App\Services\Data\UpdateTradingAccount;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Client\ConnectionException;

class MetaFiveService
{
    //private string $baseURL = "http://192.168.0.224:5000/api";
    private string $baseURL = "https://superfin-mt5.currenttech.pro/api";

    public function getConnectionStatus()
    {
        try {
            $connection = Http::acceptJson()->timeout(10)
                ->get($this->baseURL . "/connect_status")
                ->json();

            if ($connection['requestStatus'] == 'success') {
                return $connection['mtConenctionStatus'];
            } else {
                return [
                    'status' => 'fail',
                    'message' => trans('public.toast_connection_error')
                ];
            }
        } catch (ConnectionException $exception) {
            // Handle the connection timeout error
            // For example, returning an empty array as a default response
            Log::error($exception->getMessage());
            return [
                'status' => 'fail',
                'message' => trans('public.toast_connection_error')
            ];
        }
    }

    /**
     * @throws ConnectionException
     */
    public function getMetaUser($meta_login)
    {
        return Http::acceptJson()
            ->post($this->baseURL . "/getuser", [
                'login' => $meta_login
            ])
            ->json();
    }

    /**
     * @throws ConnectionException
     */
    public function getMetaAccount($meta_login)
    {
        return Http::acceptJson()
            ->post($this->baseURL . "/getaccount", [
                'login' => $meta_login
            ])
            ->json();
    }

    /**
     * @throws Throwable
     */
    public function getUserInfo($meta_login): void
    {
        // Stop if no connection
        if ($this->getConnectionStatus() !== 0) {
            return;
        }

        $userData = $this->getMetaUser($meta_login);
        if (!$userData) {
            return;
        }
        $userData['type'] = 'live';

        $metaAccountData = $this->getMetaAccount($meta_login);
        if (!$metaAccountData) {
            return;
        }

        (new UpdateTradingUser)->execute($meta_login, $userData);
        (new UpdateTradingAccount)->execute($meta_login, $metaAccountData);
    }

    /**
     * @throws ConnectionException
     * @throws Throwable
     */
    public function createUser(UserModel $user, $master_name, $account_type, $leverage)
    {
        if ($account_type->type == 'virtual') {
            $data = [
                'name' => $master_name,
                'login' => RunningNumberService::getID('virtual_account'),
                'leverage' => $leverage,
                'account_type_id' => $account_type->id
            ];
        } else {
            $accountResponse = Http::acceptJson()->post($this->baseURL . "/create_user", [
                'name' => $master_name,
                'group' => $account_type->account_group,
                'leverage' => $leverage,
                'email' => $user->email,
            ]);
            $data = $accountResponse->json();
            $data['account_type_id'] = $account_type->id;
        }

        (new CreateTradingAccount)->execute($user, $data);
        (new CreateTradingUser)->execute($user, $data);

        // Only disable trade for non-virtual accounts
        if ($account_type->type !== 'virtual' && !$account_type->allow_trade) {
            $this->disableTrade($data['login']);
        }

        return $data;
    }

    /**
     * @throws ConnectionException
     * @throws Throwable
     */
    public function createDeal($trading_account, $name, $amount, $comment, $type, $account_type, $deal_type, $transactionNumber = null): array
    {
        if ($account_type->type == 'virtual') {
            $dealResponse = [
                'deal_Id' => null,
                'conduct_Deal' => [
                    'comment' => $comment
                ]
            ];

            $currencyDigits = $trading_account->currency_digits ?? 2;
            $multiplier = pow(10, $currencyDigits);

            $newBalance = $trading_account->balance / $multiplier;
            $newCredit  = ($trading_account->credit ?? 0) / $multiplier;

            $amountDisplay = $amount;

            $sign = $type ? 1 : -1;

            switch ($deal_type) {
                case MetaService::DEAL_BALANCE:
                    $newBalance += ($sign * $amountDisplay);
                    break;

                case MetaService::DEAL_CREDIT:
                    $newCredit += ($sign * $amountDisplay);
                    break;

                case MetaService::DEAL_BONUS:
                    // If bonus affects balance or credit, modify as needed
                    break;

                default:
                    throw ValidationException::withMessages(['deal_type' => trans('public.invalid_type')]);
            }

            $userData = [
                'group'    => $account_type->account_group,
                'name'     => $name,
                'company'  => null,
                'leverage' => $trading_account->margin_leverage,
                'balance'  => $newBalance,
                'credit'   => $newCredit,
                'rights'   => 5,
                'type'     => $account_type->type,
            ];

            $metaAccountData = [
                'balance'        => $newBalance,
                'credit'         => $newCredit,
                'currencyDigits' => $currencyDigits,
                'marginLeverage' => $trading_account->margin_leverage,
                'equity'         => $newBalance,
                'floating'       => $trading_account->floating,
            ];
        } else {
            // Check connection before anything else
            if ($this->getConnectionStatus() !== 0) {
                return [
                    'success' => false,
                    'message' => trans('public.toast_connection_error'),
                ];
            }

            $dealResponse = Http::acceptJson()->post($this->baseURL . "/conduct_deal", [
                'login' => $trading_account->meta_login,
                'amount' => abs($amount),
                'imtDeal_EnDealAction' => $deal_type,
                'comment' => $comment,
                'deposit' => $type,
            ]);

            $dealResponse = $dealResponse->json();
            $userData = $this->getMetaUser($trading_account->meta_login);
            $userData['type'] = 'live';
            $metaAccountData = $this->getMetaAccount($trading_account->meta_login);
        }

        // Deal succeeded — create transaction record here
        if ($type) {
            $transaction = $trading_account->depositFloat(abs($amount), [], true, $transactionNumber, $deal_type);
        } else {
            $transaction = $trading_account->withdrawFloat(abs($amount), [], true, $transactionNumber, $deal_type);
        }

        // Update transaction metadata
        $transaction->update([
            'ticket' => $dealResponse['deal_Id'] ?? null,
            'remarks' => $dealResponse['conduct_Deal']['comment'] ?? null,
        ]);

        (new UpdateTradingUser)->execute($trading_account->meta_login, $userData);
        (new UpdateTradingAccount)->execute($trading_account->meta_login, $metaAccountData);

        return [
            'success' => true,
            'transaction' => $transaction,
        ];
    }

    public function disableTrade($meta_login)
    {
        $disableTrade = Http::acceptJson()->patch($this->baseURL . "/disable_trade/$meta_login")->json();

        $userData = $this->getMetaUser($meta_login);
        $userData['type'] = 'live';
        $metaAccountData = $this->getMetaAccount($meta_login);
        (new UpdateTradingAccount)->execute($meta_login, $metaAccountData);
        (new UpdateTradingUser)->execute($meta_login, $userData);

        return $disableTrade;
    }

    public function dealHistory($meta_login, $start_date, $end_date)
    {
        return Http::acceptJson()->get($this->baseURL . "/deal_history/{$meta_login}&{$start_date}&{$end_date}")->json();
    }

    public function updateLeverage($trading_account, $leverage, $account_type)
    {
        if ($account_type->type == 'virtual') {
            $updatedResponse = [
                'login' => $trading_account->meta_login,
                'leverage' => $leverage,
            ];

            $userData = [
                'group' => $account_type->account_group,
                'name' => Auth::user()->full_name,
                'company' => null,
                'leverage' => $leverage,
                'balance' => $trading_account->balance,
                'credit' => $trading_account->credit ?? 0,
                'rights' => 5,
                'type' => $account_type->type,
            ];

            $metaAccountData = [
                'balance' => $trading_account->balance,
                'currencyDigits' => 2,
                'credit' => $trading_account->credit ?? 0,
                'marginLeverage' => $leverage,
                'equity' => $trading_account->equity,
                'floating' => $trading_account->floating,
            ];
        } else {
            $updatedResponse = Http::acceptJson()->patch($this->baseURL . "/update_leverage", [
                'login' => $trading_account->meta_login,
                'leverage' => $leverage,
            ]);
            $updatedResponse = $updatedResponse->json();
            $userData = $this->getMetaUser($trading_account->meta_login);
            $metaAccountData = $this->getMetaAccount($trading_account->meta_login);
        }
        (new UpdateTradingUser)->execute($trading_account->meta_login, $userData);
        (new UpdateTradingAccount)->execute($trading_account->meta_login, $metaAccountData);

        return $updatedResponse;
    }

    public function changePassword($meta_login, $type, $password)
    {
        $passwordResponse = Http::acceptJson()->patch($this->baseURL . "/change_password", [
            'login' => $meta_login,
            'type' => $type,
            'password' => $password,
        ]);
        return $passwordResponse->json();
    }

    public function userTrade($meta_login)
    {
        return Http::acceptJson()->get($this->baseURL . "/check_position/{$meta_login}")->json();
    }

    public function getGroups()
    {
        $connection = $this->getConnectionStatus();

        if ($connection != 0) {
            return $connection;
        }

        try {
            $response = Http::acceptJson()
                ->get($this->baseURL . "/getgroup");

            $groups = $response->json();

            if (isset($groups['requestStatus']) && $groups['requestStatus'] == 'success') {

                return [
                    'status' => 'success',
                    'groups' => $groups['groups'] ?? []
                ];
            }

            return [
                'status' => 'fail',
                'message' => trans('public.toast_connection_error')
            ];
        } catch (Throwable $exception) {
            Log::error('Error fetching groups: ' . $exception->getMessage());

            return [
                'status' => 'fail',
                'message' => trans('public.toast_connection_error')
            ];
        }
    }
}
