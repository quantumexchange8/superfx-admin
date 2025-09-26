<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TransferTransactionExport implements FromQuery, WithHeadings, WithMapping
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
            $row->transaction_type == 'transfer_to_account' ? trans('public.rebate_to_account') : trans("public.account_to_account") ?? '',
            // From
            $row?->from_login ? (string) $row?->from_meta_login : ($row?->from_wallet ? trans("public.{$row?->from_wallet->type}") : '-'),
            strtoupper($row?->from_login ? $row->from_login->account_type->trading_platform->slug : '-') ?? '-',
            $row?->from_login?->account_type->account_group ?? '-',

            // To
            $row->to_meta_login ?? '-',
            strtoupper($row?->to_login?->account_type->trading_platform->slug) ?? '-',
            $row?->to_login?->account_type->account_group ?? '-',
            $row->transaction_amount,
            trans("public.$row->status"),
        ];
    }

    public function headings(): array
    {
        return [
            trans('public.date'),
            trans('public.name'),
            trans('public.email'),
            trans('public.id'),
            trans('public.type'),
            trans('public.from'),
            trans('public.trading_platform') . ' [' . trans('public.from') . ']',
            trans('public.account_type') . ' [' . trans('public.from') . ']',
            trans('public.to'),
            trans('public.trading_platform') . ' [' . trans('public.to') . ']',
            trans('public.account_type') . ' [' . trans('public.to') . ']',
            trans('public.amount') . ' ($)',
            trans('public.status'),
        ];
    }
}
