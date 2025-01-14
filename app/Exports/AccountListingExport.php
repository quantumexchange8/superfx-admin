<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class AccountListingExport implements FromCollection, WithHeadings
{
    protected $accounts;

    public function __construct($query)
    {
        // Eager load the necessary relationships and select specific columns
        $this->accounts = $query->select([
            'id',
            'user_id',
            'meta_login',
            'balance',
            'credit',
            'created_at',
        ])
        ->with([
            'users:id,name,email',  // Only load necessary columns from users
            'trading_account:id,meta_login,equity'  // Only load necessary columns from trading_account
        ])
        ->where('acc_status', 'active')
        ->get();
    }

    public function collection()
    {
        $data = [];

        // Loop through each account to gather the necessary data
        foreach ($this->accounts as $account) {
            // Prepare the formatted data for export
            $data[] = [
                'name' => $account->users->name ?? '',
                'email' => $account->users->email ?? '',
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
        return ['Name', 'Email', 'Account', 'Balance', 'Equity', 'Credit', 'Joined Date'];
    }
}
