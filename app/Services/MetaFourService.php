<?php

namespace App\Services;

use App\Services\Data\UpdateAccountBalance;
use Carbon\Carbon;
use App\Models\Setting;
use App\Models\TradingUser;
use App\Models\User as UserModel;
use Illuminate\Support\Facades\Http;
use App\Services\Data\CreateTradingUser;
use App\Services\Data\UpdateTradingUser;
use App\Services\Data\CreateTradingAccount;
use App\Services\Data\UpdateTradingAccount;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

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

    /**
     * @throws ConnectionException
     */
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

        $accountResponse = Http::withoutVerifying()
            ->acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload)
            ->post($url . "/getuser");

        return $accountResponse->json();
    }

    /**
     * @throws ConnectionException
     * @throws Throwable
     */
    public function getAccountEquity($meta_login)
    {
        $payload = [
            'meta_login' => $meta_login,
        ];

        $jsonPayload = json_encode($payload);

        $accountResponse = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload)
            ->post($this->baseURL . "/getequity");

        $data = $accountResponse->json();
        (new UpdateAccountBalance())->execute($meta_login, $data);

        return $data;
    }

    /**
     * @throws ConnectionException
     * @throws Throwable
     */
    public function getUserInfo($meta_login): void
    {
        $data = $this->getUser($meta_login);
        $this->getAccountEquity($meta_login);

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

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public function createTrade($meta_login, $amount, $comment, $type)
    {
        // Fetch the expiration date from the Setting model
        $setting = Setting::where('slug', 'credit_in_expired_date')->first();

        // Check if the setting exists
        if (!$setting) {
            // Handle the error if the setting is not found
            throw new \Exception("Expiration date setting not found.");
        }

        // Assuming $setting->value is a string like "90", representing the number of days
        $expirationDate = Carbon::now()->addDays(value: (int) $setting->value)->toDateString();

        $payload = [
            'meta_login' => $meta_login,
            'amount' => (float) $amount,
            'comment' => $comment,
            'type' => $type,
        ];

        // Add expiration date for credit type only
        if ($type === 'credit') {
            $payload['expiration_date'] = $expirationDate;  // Use the fetched expiration date
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

        $this->getUserInfo($meta_login);

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

        $accountResponse = Http::acceptJson()
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

        $accountResponse = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload, 'application/json')
            ->patch($url . "/changeinvestorpassword");

        // Return the JSON response from the API
        return $accountResponse->json();
    }

    public function getUserByGroup($group, $type)
    {
        $payload = [
            'group' => $group,
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
            ->get($url . "/getuserbygroup");

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
