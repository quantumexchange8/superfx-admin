<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PayoutTransactionExport implements FromCollection, WithHeadings
{
    protected $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $rows = [];

        foreach ($this->data as $item) {
            $rows[] = [
                $item['execute_at'] ?? '',
                $item['name'] ?? '',
                $item['email'] ?? '',
                $item['volume'] ?? 0,
                $item['rebate'] ?? 0,
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
            trans('public.volume') . ' (Ł)',
            trans('public.payout') . ' ($)',
        ];
    }
}
