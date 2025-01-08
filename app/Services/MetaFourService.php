<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\TradingUser;
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
    
        $tradingUser = TradingUser::where('meta_login', $meta_login)->first();

        if ($tradingUser && $tradingUser->category === 'live') {
            $url = $this->baseURL;
        } else {
            $url = $this->demoURL;
        }
    
        $accountResponse = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload, 'application/json')
            ->get($url . "/getuser");
        
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

    public function createUser(UserModel $user, $group, $leverage, $mainPassword, $investorPassword, $type)
    {
        $payload = [
            'master_password' => $mainPassword,
            'investor_password' => $investorPassword,
            'name' => $user->name,
            'group' => $group,
            'leverage' => $leverage,
            'email' => $user->email,
        ];

        $jsonPayload = json_encode($payload);

        if ($type && $type === 'live') {
            $url = $this->baseURL;
        } else {
            $url = $this->demoURL;
        }

        $accountResponse = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload, 'application/json')
            ->post($url . "/createuser");

        (new CreateTradingAccount)->execute($user, $accountResponse, $group);
        (new CreateTradingUser)->execute($user, $accountResponse, $group);
        return $accountResponse;
    }

    public function createTrade($meta_login, $amount, $comment, $type)
    {
        $payload = [
            'meta_login' => $meta_login,
            'amount' => $amount,
            'comment' => $comment,
            'type' => $type,
        ];
    
        // Add expiration date for credit type only
        if ($type === 'credit') {
            $payload['expiration_date'] = "2030-12-31";  // Set expiration date for credit transactions
        } else {
            $payload['expiration_date'] = '';  // Empty expiration date for balance transactions
        }
    
        $jsonPayload = json_encode($payload);
    
        $tradingUser = TradingUser::where('meta_login', $meta_login)->first();

        if ($tradingUser && $tradingUser->category === 'live') {
            $url = $this->baseURL;
        } else {
            $url = $this->demoURL;
        }
    
        $accountResponse = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload, 'application/json')
            ->post($url . "/transaction");
    
        // Return the JSON response from the API
        return $accountResponse->json();
    }
    
    public function updateLeverage($meta_login, $leverage)
    {
        $payload = [
            'meta_login' => $meta_login,
            'leverage' => $leverage,
        ];

        $jsonPayload = json_encode($payload);
    
        $tradingUser = TradingUser::where('meta_login', $meta_login)->first();

        if ($tradingUser && $tradingUser->category === 'live') {
            $url = $this->baseURL;
        } else {
            $url = $this->demoURL;
        }
    
        $accountResponse = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload, 'application/json')
            ->patch($url . "/updateleverage");
    
        // Return the JSON response from the API
        return $accountResponse->json();
    }


    public function updateAccountGroup($meta_login, $group)
    {
        $payload = [
            'meta_login' => $meta_login,
            'group' => $group,
        ];

        $jsonPayload = json_encode($payload);
    
        $tradingUser = TradingUser::where('meta_login', $meta_login)->first();

        if ($tradingUser && $tradingUser->category === 'live') {
            $url = $this->baseURL;
        } else {
            $url = $this->demoURL;
        }
    
        $accountResponse = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload, 'application/json')
            ->patch($url . "/updategroup");
    
        // Return the JSON response from the API
        return $accountResponse->json();
    }

    public function updateMasterPassword($meta_login, $password)
    {
        $payload = [
            'meta_login' => $meta_login,
            'password' => $password,
        ];

        $jsonPayload = json_encode($payload);
    
        $tradingUser = TradingUser::where('meta_login', $meta_login)->first();

        if ($tradingUser && $tradingUser->category === 'live') {
            $url = $this->baseURL;
        } else {
            $url = $this->demoURL;
        }
    
        $accountResponse = Http::
        acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload, 'application/json')
            ->patch($url . "/changemasterpassword");
    
        // Return the JSON response from the API
        return $accountResponse->json();
    }

    public function updateInvestorPassword($meta_login, $password)
    {
        $payload = [
            'meta_login' => $meta_login,
            'password' => $password,
        ];

        $jsonPayload = json_encode($payload);
    
        $tradingUser = TradingUser::where('meta_login', $meta_login)->first();

        if ($tradingUser && $tradingUser->category === 'live') {
            $url = $this->baseURL;
        } else {
            $url = $this->demoURL;
        }
    
        $accountResponse = Http::
        acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload, 'application/json')
            ->patch($url . "/changeinvestorpassword");
    
        // Return the JSON response from the API
        return $accountResponse->json();
    }
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

class ChangeTraderBalanceType
{
    const DEPOSIT = "balance";
    const WITHDRAW = "balance";
    const CREDIT_IN = "credit";
    const CREDIT_OUT = "credit";
}
