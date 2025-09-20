<?php

namespace App\Exports;

use Illuminate\Support\Collection;
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
            trans('public.product'),
            trans('public.ticket'),
            trans('public.open_time'),
            trans('public.closed_time'),
            trans('public.open_price') . ' ($)',
            trans('public.close_price') . ' ($)',
            trans('public.type'),
            trans('public.name'),
            trans('public.email'),
            trans('public.id'),
            trans('public.upline_name'),
            trans('public.upline_email'),
            trans('public.upline_id'),
            trans('public.profit') . ' ($)',
            trans('public.account'),
            trans('public.account_type'),
            trans('public.volume') . ' (Ł)',
            trans('public.rebate') . ' ($)',
            trans('public.status'),
        ];
    }

    public function collection(): Collection
    {
        $data = [];

        foreach ($this->rebate_history as $history) {
            $data[] = [
                $history->created_at->format('Y/m/d'),
                $history->symbol,
                $history->deal_id,
                $history->open_time,
                $history->closed_time,
                $history->trade_open_price ?? 0,
                $history->trade_close_price ?? 0,
                trans('public.' . $history->t_type),
                $history->downline->name ?? '',
                $history->downline->email ?? '',
                $history->downline->id_number ?? '',
                $history->upline->name ?? '',
                $history->upline->email ?? '',
                $history->upline->id_number ?? '',
                $history->trade_profit ?? 0,
                $history->meta_login,
                strtoupper($history->of_account_type->trading_platform->slug) . '-' . $history->of_account_type->name,
                $history->volume ?? 0,
                $history->revenue ?? 0,
                trans('public.completed'),
            ];
        }

        return collect($data);
    }
}
