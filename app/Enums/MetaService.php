<?php

namespace App\Enums;

enum MetaService: string
{
    // Deal Action
    const BALANCE = 'balance';
    const CREDIT = 'credit';

    // Deal Type
    const DEAL_BALANCE = 2;
    const DEAL_CREDIT = 3;
    const DEAL_BONUS = 6;

    // Password Type
    const MAIN_PASSWORD = false;
    const INVESTOR_PASSWORD = true;

    // Fund Type
    const REAL_FUND = 'real_fund';
    const DEMO_FUND = 'demo_fund';
}
