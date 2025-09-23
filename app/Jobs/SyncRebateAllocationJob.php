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
        // 1. Get IB users
        $ibUserIds = User::where('role', 'ib')->pluck('id')->toArray();

        // 2. Get account types with account_group + trading_platform_id
        $accountTypes   = AccountType::select(['id', 'account_group', 'trading_platform_id'])->get();
        $accountTypeIds = $accountTypes->pluck('id')->toArray();

        // 3. Symbol group IDs
        $symbolGroupIds = range(1, 6);

        // 4. Get all existing allocations as a lightweight lookup array
        $existing = []; // [user_id][account_type_id][symbol_group_id] => amount

        // Use cursor() to stream
        RebateAllocation::whereIn('user_id', $ibUserIds)
            ->select(['user_id', 'account_type_id', 'symbol_group_id', 'amount'])
            ->cursor()
            ->each(function ($item) use (&$existing) {
                $existing[$item->user_id][$item->account_type_id][$item->symbol_group_id] = $item->amount;
            });

        // 4b. Map account_type_id to model (group + platform)
        $accountTypeMap = $accountTypes->keyBy('id'); // id => model

        $now      = now();
        $editedBy = auth()->id() ?? 1;

        DB::transaction(function () use (
            $ibUserIds,
            $accountTypeIds,
            $symbolGroupIds,
            $existing,
            $accountTypes,
            $accountTypeMap,
            $now,
            $editedBy
        ) {
            $chunk = [];

            foreach ($ibUserIds as $userId) {
                foreach ($accountTypeIds as $accountTypeId) {
                    /** @var AccountType $acctType */
                    $acctType = $accountTypeMap[$accountTypeId];

                    foreach ($symbolGroupIds as $symbolGroupId) {
                        // Skip if already exists
                        if (isset($existing[$userId][$accountTypeId][$symbolGroupId])) {
                            continue;
                        }

                        // Try to find an existing allocation from another platform but same account_group
                        $sameGroupOtherTypes = $accountTypes->where('account_group', $acctType->account_group)
                            ->where('trading_platform_id', '!=', $acctType->trading_platform_id);

                        $amount = 0;
                        foreach ($sameGroupOtherTypes as $otherType) {
                            if (isset($existing[$userId][$otherType->id][$symbolGroupId])) {
                                $amount = $existing[$userId][$otherType->id][$symbolGroupId];
                                break; // take the first found
                            }
                        }

                        $chunk[] = [
                            'user_id'         => $userId,
                            'account_type_id' => $accountTypeId,
                            'symbol_group_id' => $symbolGroupId,
                            'amount'          => $amount,
                            'edited_by'       => $editedBy,
                            'created_at'      => $now,
                            'updated_at'      => $now,
                        ];

                        // Insert every 500 rows to keep memory low
                        if (count($chunk) === 500) {
                            RebateAllocation::insert($chunk);
                            $chunk = [];
                        }
                    }
                }
            }

            // Insert any remaining records
            if (! empty($chunk)) {
                RebateAllocation::insert($chunk);
            }
        });
    }
}
