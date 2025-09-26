<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DepositTransactionExport implements FromQuery, WithHeadings, WithMapping
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function map($row): array
    {
        return [
            $row->created_at,
            $row->user->name ?? '',
            $row->user->email ?? '',
            $row->transaction_number ?? '',
            $row->to_meta_login ?? '',
            strtoupper($row->to_login->account_type->trading_platform->slug) ?? '',
            $row->to_login->account_type->account_group ?? '',
            $row->transaction_amount,
            trans("public.$row->status"),
            $row->payment_gateway->name ?? '',
            $row->payment_platform_name ?? '',
            $row->bank_code ?? '',
            strtoupper($row->payment_account_type) ?? '',
            $row->to_wallet_address ?? '',
        ];
    }

    public function headings(): array
    {
        return [
            trans('public.date'),
            trans('public.name'),
            trans('public.email'),
            trans('public.id'),
            trans('public.account'),
            trans('public.trading_platform'),
            trans('public.account_type'),
            trans('public.amount') . ' ($)',
            trans('public.status'),
            trans('public.payment_platform'),
            trans('public.bank_name'),
            trans('public.bank_code'),
            trans('public.payment_account_type'),
            trans('public.to'),
        ];
    }
}
