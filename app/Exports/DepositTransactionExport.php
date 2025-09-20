<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DepositTransactionExport implements FromCollection, WithHeadings
{
    protected $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $rows = [];

        foreach ($this->data as $obj) {
            $toDisplay = $obj['to_meta_login']
                ?? ($obj['to_wallet_name'] ? trans('public.' . $obj['to_wallet_name']) : '');

            $accountTypeLabel = isset($obj['payment_platform']) && $obj['payment_platform'] === 'bank'
                ? (isset($obj['payment_account_type']) && $obj['payment_account_type'] === 'card'
                    ? trans('public.card')
                    : trans('public.account'))
                : (isset($obj['payment_account_type']) ? trans('public.' . $obj['payment_account_type']) : '');

            $rows[] = [
                isset($obj['created_at']) ? Carbon::parse($obj['created_at'])->format('Y/m/d') : '',
                $obj['name'] ?? '',
                $obj['email'] ?? '',
                $obj['transaction_number'] ?? '',
                $toDisplay,
                strtoupper($obj['trading_platform']) . '-' . $obj['account_type'],
                $obj['transaction_amount'] ?? '',
                isset($obj['status']) ? trans('public.' . $obj['status']) : '',
                $obj['to_wallet_address'] ?? '',
                isset($obj['payment_platform']) ? trans('public.' . $obj['payment_platform']) : '',
                $obj['payment_platform_name'] ?? '',
                $obj['bank_code'] ?? '',
                $accountTypeLabel,
                $obj['payment_account_no'] ?? '',
            ];
        }

        return new Collection($rows);
    }

    public function headings(): array
    {
        return [
            trans('public.date'),
            trans('public.name'),
            trans('public.email'),
            trans('public.id'),
            trans('public.account'),
            trans('public.account_type'),
            trans('public.amount') . ' ($)',
            trans('public.status'),
            trans('public.receiving_address'),
            trans('public.platform'),
            trans('public.bank_name'),
            trans('public.bank_code'),
            trans('public.payment_account_type'),
            trans('public.account_no'),
        ];
    }
}
