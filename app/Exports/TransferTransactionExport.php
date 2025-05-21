<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TransferTransactionExport implements FromCollection, WithHeadings
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
            // For 'from' field
            if (isset($obj['transaction_type']) && $obj['transaction_type'] === 'transfer_to_account') {
                // Check wallet name, else fallback to meta_login
                if (!empty($obj['from_wallet_name'])) {
                    $fromDisplay = trans('public.' . $obj['from_wallet_name']);
                } else {
                    $fromDisplay = $obj['from_meta_login'] ?? '';
                }
            } else {
                // For other types: just use from_meta_login directly
                $fromDisplay = $obj['from_meta_login'] ?? '';
            }

            // For 'to' field
            if (isset($obj['transaction_type']) && $obj['transaction_type'] === 'transfer_to_account') {
                if (!empty($obj['to_wallet_name'])) {
                    $toDisplay = trans('public.' . $obj['to_wallet_name']);
                } else {
                    $toDisplay = $obj['to_meta_login'] ?? '';
                }
            } else {
                // For other types: just use to_meta_login directly
                $toDisplay = $obj['to_meta_login'] ?? '';
            }

            $rows[] = [
                isset($obj['created_at']) ? \Carbon\Carbon::parse($obj['created_at'])->format('Y/m/d') : '',
                $obj['name'] ?? '',
                $obj['email'] ?? '',
                $obj['transaction_number'] ?? '',
                $obj['transaction_type'] ?? '',
                $fromDisplay,
                $toDisplay,
                $obj['transaction_amount'] ?? '',
                isset($obj['status']) ? trans('public.' . $obj['status']) : '',
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
            trans('public.type'),
            trans('public.from'),
            trans('public.to'),
            trans('public.amount') . ' ($)',
            trans('public.status'),
        ];
    }
}
