<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WithdrawalTransactionExport implements FromQuery, WithHeadings, WithMapping
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
            $row->approved_at ?? null,
            trans("public.$row->status"),
            $row->user->name ?? '',
            $row->user->email ?? '',
            $row->transaction_number ?? '',
            $row?->from_login ? (string) $row?->from_meta_login : ($row?->from_wallet ? trans("public.{$row?->from_wallet->type}") : ''),
            strtoupper($row?->from_login?->account_type->trading_platform->slug) ?? '-',
            $row?->from_login?->account_type->account_group ?? '-',
            $row->amount,
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
            trans('public.requested_date'),
            trans('public.approval_date'),
            trans('public.status'),
            trans('public.name'),
            trans('public.email'),
            trans('public.id'),
            trans('public.from'),
            trans('public.trading_platform'),
            trans('public.account_type'),
            trans('public.amount') . ' ($)',
            trans('public.payment_platform'),
            trans('public.bank_name'),
            trans('public.bank_code'),
            trans('public.payment_account_type'),
            trans('public.to'),
        ];
    }
}
