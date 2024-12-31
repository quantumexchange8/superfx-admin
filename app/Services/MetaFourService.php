<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\User as UserModel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\Data\CreateTradingUser;
use App\Services\Data\UpdateTradingUser;
use App\Services\Data\CreateTradingAccount;
use App\Services\Data\UpdateTradingAccount;
use Illuminate\Http\Client\ConnectionException;

class MetaFourService {
    private string $port = "8443";
    private string $login = "10012";
    private string $password = "Test1234.";
    private string $baseURL = "https://superfin-live.currenttech.pro/api";
    private string $demoURL = "https://superfin-demo.currenttech.pro/api";

    // private static string $date = Carbon::now('Asia/Riyadh')->toDateString();
    // private string $token2 = "SuperFin-Live^" . $date . "&SuperGlobal";
    // private string $token = hash('sha256', $token2);
    private string $environmentName = "live";

    private string $token;

    public function __construct()
    {
        $token2 = "SuperFin-Live^" . Carbon::now('Asia/Riyadh')->toDateString() . "&SuperGlobal";
        
        $this->token = hash('sha256', $token2);
    }

    // public function getConnectionStatus()
    // {
    //     try {
    //         return Http::acceptJson()->timeout(10)->get($this->baseURL . "/connect_status")->json();
    //     } catch (ConnectionException $exception) {
    //         // Handle the connection timeout error
    //         // For example, returning an empty array as a default response
    //         return [];
    //     }
    // }

    public function getUser($meta_login)
    {
        $payload = [
            'meta_login' => $meta_login,
        ];
        
        $jsonPayload = json_encode($payload);
    
        if (App::environment('production')) {
            $accountResponse = Http::acceptJson()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                ])
                ->withBody($jsonPayload, 'application/json')
                ->get($this->demoURL . "/getuser");
        } else {
            $accountResponse = Http::withoutVerifying()
                ->acceptJson()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                ])
                ->withBody($jsonPayload, 'application/json')
                ->get($this->demoURL . "/getuser");
        }
        
        return $accountResponse->json();
    }

    public function getUserInfo($meta_login): void
    {
        $data = $this->getUser($meta_login);

        if ($data) {
            (new UpdateTradingUser)->execute($meta_login, $data);
            (new UpdateTradingAccount)->execute($meta_login, $data);
        }
    }

    public function createUser(UserModel $user, $group, $leverage, $mainPassword, $investorPassword)
    {
        $accountResponse = Http::acceptJson()
        ->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])
        ->post($this->demoURL . "/createuser", [
            'master_password' => $mainPassword,
            'investor_password' => $investorPassword,
            'name' => $user->name,
            'group' => $group,
            'leverage' => $leverage,
            'email' => $user->email,
        ]);
        $accountResponse = $accountResponse->json();

        (new CreateTradingAccount)->execute($user, $accountResponse, $group);
        (new CreateTradingUser)->execute($user, $accountResponse, $group);
        return $accountResponse;
    }

    // public function createDeal($meta_login, $amount, $comment, $type)
    // {
    //     $dealResponse = Http::acceptJson()->post($this->baseURL . "/conduct_deal", [
    //         'login' => $meta_login,
    //         'amount' => $amount,
    //         'imtDeal_EnDealAction' => dealType::DEAL_BALANCE,
    //         'comment' => $comment,
    //         'deposit' => $type,
    //     ]);
    //     $dealResponse = $dealResponse->json();
    //     Log::debug($dealResponse);

    //     $this->getUserInfo($meta_login);
    //     // $userData = $this->getMetaUser($meta_login);
    //     // $metaAccountData = $this->getMetaAccount($meta_login);
    //     // (new UpdateTradingAccount)->execute($meta_login, $metaAccountData);
    //     // (new UpdateTradingUser)->execute($meta_login, $userData);
    //     return $dealResponse;
    // }

    // public function createTrade($meta_login, $amount, $comment, $type): Trade
    // {
    //     $response = Http::acceptJson()->post($this->baseURL . "/v2/webserv/traders/$meta_login/changebalance?token=$this->token", [
    //         'login' => $meta_login,
    //         'amount' => $amount * 100, //
    //         'preciseAmount' => $amount, //
    //         'type' => $type,
    //         'comment' => $comment, //
    //         /* 'externalNote' => '', //
    //         'source' => '', //
    //         'externalId' => '', // */
    //     ]);
    //     $response = $response->json();

    //     $trade = new Trade();
    //     $trade->setAmount($amount);
    //     $trade->setComment($comment);
    //     $trade->setType($type);
    //     $trade->setTicket($response['balanceHistoryId']);

    //     $this->getUserInfo($meta_login);
    //     return $trade;
    // }

    // public function disableTrade($meta_login)
    // {
    //     $disableTrade = Http::acceptJson()->patch($this->baseURL . "/disable_trade/{$meta_login}")->json();

    //     $userData = $this->getMetaUser($meta_login);
    //     $metaAccountData = $this->getMetaAccount($meta_login);
    //     (new UpdateTradingAccount)->execute($meta_login, $metaAccountData);
    //     (new UpdateTradingUser)->execute($meta_login, $userData);

    //     return $disableTrade;
    // }

    // public function dealHistory($meta_login, $start_date, $end_date)
    // {
    //     return Http::acceptJson()->get($this->baseURL . "/deal_history/{$meta_login}&{$start_date}&{$end_date}")->json();
    // }

    // public function updateLeverage($meta_login, $leverage)
    // {
    //     $upatedResponse = Http::acceptJson()->patch($this->baseURL . "/update_leverage", [
    //         'login' => $meta_login,
    //         'leverage' => $leverage,
    //     ]);
    //     $upatedResponse = $upatedResponse->json();
    //     $userData = $this->getMetaUser($meta_login);
    //     $metaAccountData = $this->getMetaAccount($meta_login);
    //     (new UpdateTradingAccount)->execute($meta_login, $metaAccountData);
    //     (new UpdateTradingUser)->execute($meta_login, $userData);

    //     return $upatedResponse;
    // }

    // public function changePassword($meta_login, $type, $password)
    // {
    //     $passwordResponse = Http::acceptJson()->patch($this->baseURL . "/change_password", [
    //         'login' => $meta_login,
    //         'type' => $type,
    //         'password' => $password,
    //     ]);
    //     return $passwordResponse->json();
    // }

    // public function userTrade($meta_login)
    // {
    //     return Http::acceptJson()->get($this->baseURL . "/check_position/{$meta_login}")->json();
    // }

    // public function deleteAccount($meta_login)
    // {
    //     $deleteAccount = Http::acceptJson()->delete($this->baseURL . "/delete_tradeacc/{$meta_login}")->json();

    //     return $deleteAccount;
    // }
}

class dealAction
{
    const DEPOSIT = true;
    const WITHDRAW = false;
}

class dealType
{
    const DEAL_BALANCE = 2;
    const DEAL_CREDIT = 3;
    const DEAL_BONUS = 6;
}

class passwordType
{
    const MAIN = false;
    const INVESTOR = true;
}
