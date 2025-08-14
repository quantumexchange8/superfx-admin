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
            'users:id,name,email,upline_id',
            'users.upline:id,name,email',
            'trading_account:id,meta_login,equity',
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
                'referrer_name' => optional($account->users->upline)->name ?? '',
                'referrer_email' => optional($account->users->upline)->email ?? '',            
            ];
        }

        return collect($data);
    }

    public function headings(): array
    {
        return [
            trans('public.name'),
            trans('public.email'),
            trans('public.account'),
            trans('public.balance'),
            trans('public.equity'),
            trans('public.credit'),
            trans('public.join_date'),
            trans('public.referrer_name'),
            trans('public.referrer_email'),
        ];

    }
}
