<?php

namespace App\Jobs;

use App\Models\AccountType;
use App\Models\RebateAllocation;
use App\Models\User;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncRebateAllocationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = null;

    public function __construct()
    {
        $this->queue = 'sync_rebate_allocation';
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        // 1. Get all IB user IDs
        $ibUserIds = User::where('role', 'ib')->pluck('id')->toArray();

        // 2. Get all account type IDs
        $accountTypeIds = AccountType::pluck('id')->toArray();

        // 3. Symbol group IDs
        $symbolGroupIds = range(1, 6);

        // 4. Get all existing allocations in one query
        $existing = RebateAllocation::whereIn('user_id', $ibUserIds)
            ->get(['user_id', 'account_type_id', 'symbol_group_id'])
            ->map(fn ($item) => "{$item->user_id}-{$item->account_type_id}-{$item->symbol_group_id}")
            ->flip(); // keys become lookup array

        // 5. Build missing records
        $missingRecords = [];
        $now = now();
        $editedBy = auth()->id() ?? 1; // or system user

        foreach ($ibUserIds as $userId) {
            foreach ($accountTypeIds as $accountTypeId) {
                foreach ($symbolGroupIds as $symbolGroupId) {
                    $key = "{$userId}-{$accountTypeId}-{$symbolGroupId}";

                    if (! isset($existing[$key])) {
                        $missingRecords[] = [
                            'user_id'         => $userId,
                            'account_type_id' => $accountTypeId,
                            'symbol_group_id' => $symbolGroupId,
                            'amount'          => 0,
                            'edited_by'       => $editedBy,
                            'created_at'      => $now,
                            'updated_at'      => $now,
                        ];
                    }
                }
            }
        }

        // 6. Bulk insert
        if (! empty($missingRecords)) {
            // optional: wrap in a transaction for safety
            DB::transaction(function () use ($missingRecords) {
                // chunk to avoid hitting DB limit
                foreach (array_chunk($missingRecords, 1000) as $chunk) {
                    RebateAllocation::insert($chunk);
                }
            });
        }
    }
}
