<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PayoutTransactionExport implements FromQuery, WithHeadings, WithMapping
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
            $row->execute_date,
            $row->upline_user->name ?? $row->name ?? '',
            $row->upline_user->email ?? $row->email ?? '',
            $row->total_volume ?? 0,
            $row->total_rebate ?? 0,
        ];
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
