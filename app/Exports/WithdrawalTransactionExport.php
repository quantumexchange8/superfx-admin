<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WithdrawalTransactionExport implements FromCollection, WithHeadings
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
            // Format requested_date and approval_date safely
            $requestedDate = $obj['created_at'] ?? null;
            $approvalDate = $obj['approved_at'] ?? null;

            $requestedDateFormatted = $requestedDate instanceof \DateTimeInterface
                ? $requestedDate->format('Y/m/d')
                : ($requestedDate ? date('Y/m/d', strtotime($requestedDate)) : '');

            $approvalDateFormatted = $approvalDate instanceof \DateTimeInterface
                ? $approvalDate->format('Y/m/d')
                : ($approvalDate ? date('Y/m/d', strtotime($approvalDate)) : '');

            // Determine platform translation
            $platform = isset($obj['payment_platform']) ? trans('public.' . $obj['payment_platform']) : '';

            // Determine payment account type translation
            $paymentAccountType = '';
            if (isset($obj['payment_platform'], $obj['payment_account_type'])) {
                if ($obj['payment_platform'] === 'bank') {
                    $paymentAccountType = $obj['payment_account_type'] === 'card'
                        ? trans('public.card')
                        : trans('public.account');
                } else {
                    $paymentAccountType = trans('public.' . $obj['payment_account_type']);
                }
            }

            $rows[] = [
                $requestedDateFormatted,                   // requested_date
                $approvalDateFormatted,                    // approval_date
                $obj['name'] ?? '',                        // name
                $obj['email'] ?? '',                       // email
                $obj['transaction_number'] ?? '',         // id (transaction_number)
                $obj['from_meta_login'] ?? '',             // from
                $obj['transaction_amount'] ?? '',         // amount ($)
                isset($obj['status']) ? trans('public.' . $obj['status']) : '', // status
                $obj['to_wallet_address'] ?? '',          // receiving_address
                $platform,                                // platform
                $obj['payment_platform_name'] ?? '',      // bank_name
                $obj['bank_code'] ?? '',                   // bank_code
                $paymentAccountType,                       // payment_account_type
                $obj['payment_account_no'] ?? '',         // account_no
                $obj['from_wallet_name'] ?? '',           // wallet_name
                $obj['from_wallet_address'] ?? '',        // wallet_address
            ];
        }

        return new Collection($rows);
    }

    public function headings(): array
    {
        return [
            trans('public.requested_date'),
            trans('public.approval_date'),
            trans('public.name'),
            trans('public.email'),
            trans('public.id'),
            trans('public.from'),
            trans('public.amount') . ' ($)',
            trans('public.status'),
            trans('public.receiving_address'),
            trans('public.platform'),
            trans('public.bank_name'),
            trans('public.bank_code'),
            trans('public.payment_account_type'),
            trans('public.account_no'),
            trans('public.wallet_name'),
            trans('public.wallet_address'),
        ];
    }
}
