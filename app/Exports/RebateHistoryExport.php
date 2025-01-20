<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Lang;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RebateHistoryExport implements FromCollection, WithHeadings
{
    protected $rebate_history;

    public function __construct(Collection $rebate_history)
    {
        $this->rebate_history = $rebate_history;
    }

    public function headings(): array
    {
        return [
            trans('public.date'),
            trans('public.ticket'),
            trans('public.open_time'),
            trans('public.closed_time'),
            trans('public.open_price') . ' ($)',
            trans('public.close_price') . ' ($)',
            trans('public.type'),
            trans('public.name'),
            trans('public.email'),
            trans('public.id'),
            trans('public.profit') . ' ($)',
            trans('public.account'),
            trans('public.product'),
            trans('public.volume') . ' (Ł)',
            trans('public.rebate') . ' ($)',
            trans('public.status'),
        ];
    }

    public function collection()
    {
        $data = [];

        foreach ($this->rebate_history as $history) {
            $data[] = [
                $history->created_at->format('Y/m/d'),
                $history->deal_id,
                $history->open_time,
                $history->closed_time,
                $history->trade_open_price ?? 0,
                $history->trade_close_price ?? 0,
                trans('public.' . $history->t_type),
                $history->downline->name ?? '',
                $history->downline->email ?? '',
                $history->downline->id_number ?? '',
                $history->trade_profit ?? 0,
                $history->meta_login,
                $history->symbol,
                $history->volume ?? 0,
                $history->revenue ?? 0,
                trans('public.completed'),
            ];
        }

        return collect($data);
    }
}
