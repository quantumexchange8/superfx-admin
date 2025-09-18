<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class AccountListingExport implements FromCollection, WithHeadings
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function collection(): Collection
    {
        $filteredData = $this->query->select([
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

        $data = array();
        foreach ($filteredData as $account) {
            // Prepare the formatted data for export
            $data[] = [
                'name' => optional($account->users)->name ?? '',
                'email' => optional($account->users)->email ?? '',
                'referrer_name' => optional(optional($account->users)->upline)->name ?? '',
                'referrer_email' => optional(optional($account->users)->upline)->email ?? '',
                'account' => $account->meta_login ?? '',
                'balance' => $account->balance ?? '0',
                'equity' => optional($account->trading_account)->equity ?? '0',
                'credit' => $account->credit ?? '0',
                'joined_date' => $account->created_at ? $account->created_at->format('Y-m-d') : '',
            ];
        }

        return collect($data);
    }

    public function headings(): array
    {
        return [
            trans('public.name'),
            trans('public.email'),
            trans('public.referrer_name'),
            trans('public.referrer_email'),
            trans('public.account'),
            trans('public.balance'),
            trans('public.equity'),
            trans('public.credit'),
            trans('public.join_date'),
        ];

    }
}
