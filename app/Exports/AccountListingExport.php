<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Facades\DB;

class AccountListingExport implements FromCollection, WithHeadings
{
    protected $accounts;

    public function __construct($query)
    {
        // Re-fetch the necessary columns for export (7 columns)
        $this->accounts = $query->select([
            'id',
            'user_id',
            'meta_login',
            'balance',
            'credit',
            'created_at',
        ])
        ->where('acc_status', 'active')
        ->get();
    }

    public function collection()
    {
        $data = [];

        // Loop through each account to gather the necessary data
        foreach ($this->accounts as $account) {
            // Load the related data for the user and trading account
            $account->load(['users', 'trading_account']);

            // Prepare the formatted data for export
            $data[] = [
                'name' => optional($account->users)->name ,
                'email' => optional($account->users)->email,
                'account' => $account->meta_login,
                'balance' => $account->balance ? $account->balance : '0',
                'equity' => $account->trading_account->equity ? $account->trading_account->equity : '0',
                'credit' => $account->credit ? $account->credit : '0',
                'joined_date' => $account->created_at ? $account->created_at->format('Y-m-d') : '',
            ];
        }

        return collect($data);
    }

    public function headings(): array
    {
        return ['Name', 'Email', 'Account', 'Balance', 'Equity', 'Credit', 'Joined Date']; // The 7 columns
    }
}
