<?php

namespace App\Services\TradingPlatform;

interface TradingPlatformInterface
{
    /**
     * Fetch user info by meta login.
     */
    public function getUser($metaLogin): array;

    /**
     * Fetch account info by meta login.
     */
    public function getAccount($metaLogin): array;

    /**
     * Create deal for meta login.
     */
    public function createDeal($meta_login, $amount, $comment, $type, $expire_date): array;

    /**
     * Update leverage for meta login.
     */
    public function updateLeverage($meta_login, $leverage): void;

    /**
     * Update leverage for meta login.
     */
    public function changeMasterPassword($meta_login, $password): void;

    /**
     * Update leverage for meta login.
     */
    public function changeInvestorPassword($meta_login, $password): void;
}
