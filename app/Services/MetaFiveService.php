<?php

namespace App\Services;

use App\Enums\MetaService;
use App\Services\TradingPlatform\TradingPlatformInterface;
use Carbon\Carbon;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Throwable;
use App\Models\User as UserModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\Data\CreateTradingUser;
use App\Services\Data\UpdateTradingUser;
use App\Services\Data\CreateTradingAccount;
use App\Services\Data\UpdateTradingAccount;
use Illuminate\Http\Client\ConnectionException;

class MetaFiveService implements TradingPlatformInterface
{
    //private string $baseURL = "http://192.168.0.224:5000/api";
    private string $baseURL = "https://superfin-mt5.currenttech.pro/api";
    private string $token;

    public function __construct()
    {
        $token2 = "SuperFin-Live^" . Carbon::now('Asia/Riyadh')->toDateString() . "&SuperGlobal";

        $this->token = hash('sha256', $token2);
    }

    public function getConnectionStatus()
    {
        try {
            $connection = Http::acceptJson()->timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                ])
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
    public function getUser($metaLogin): array
    {
        return Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->post($this->baseURL . "/getuser", [
                'login' => $metaLogin
            ])
            ->json();
    }

    /**
     * @throws ConnectionException
     */
    public function getAccount($metaLogin): array
    {
        return Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->post($this->baseURL . "/getaccount", [
                'login' => $metaLogin
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

        $userData = $this->getUser($meta_login);
        if (!$userData) {
            return;
        }

        $metaAccountData = $this->getAccount($meta_login);
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
    public function createUser(UserModel $user, $group, $leverage): PromiseInterface|Response
    {
        $payload = [
            'name' => $user->name,
            'group' => $group->account_group,
            'leverage' => $leverage,
            'email' => $user->email,
        ];

        $jsonPayload = json_encode($payload);

        $accountResponse = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload)
            ->post($this->baseURL . "/create_user");

        (new CreateTradingAccount)->execute($user, $accountResponse, $group);
        (new CreateTradingUser)->execute($user, $accountResponse, $group);

        return $accountResponse;
    }

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public function createDeal($meta_login, $amount, $comment, $type, $expire_date): array
    {
        $payload = [
            'login' => $meta_login,
            'imtDeal_EnDealAction' => $type == 'balance' ? MetaService::DEAL_BALANCE : MetaService::DEAL_CREDIT,
            'amount' => (float) $amount,
            'expiration_date' => $expire_date,
            'comment' => $comment,
        ];

        $jsonPayload = json_encode($payload);

        $dealResponse = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload)
            ->post($this->baseURL . "/transaction");

        $dealResponse = $dealResponse->json();

        if (isset($dealResponse['requestStatus']) && $dealResponse['requestStatus'] == 'success') {

            $this->getUserInfo($meta_login);

            return [
                'ticket' => $dealResponse['deal_id'],
            ];
        }

        return [
            'status' => 'fail',
            'message' => trans('public.toast_connection_error')
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

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public function updateLeverage($meta_login, $leverage): void
    {
        Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->patch($this->baseURL . "/update_leverage", [
                'login' => $meta_login,
                'leverage' => $leverage,
            ]);

        $this->getUserInfo($meta_login);
    }

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public function changeMasterPassword($meta_login, $password): void
    {
        Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->patch($this->baseURL . "/changemasterpassword", [
                'login' => $meta_login,
                'password' => $password,
            ]);

        $this->getUserInfo($meta_login);
    }

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public function changeInvestorPassword($meta_login, $password): void
    {
        Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->patch($this->baseURL . "/changeinvestorpassword", [
                'login' => $meta_login,
                'password' => $password,
            ]);

        $this->getUserInfo($meta_login);
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
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                ])
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
