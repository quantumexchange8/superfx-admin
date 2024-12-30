<script setup>
import {ref} from "vue";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import {transactionFormat} from "@/Composables/index.js";
import Loader from "@/Components/Loader.vue";
import Dialog from "primevue/dialog";
import Empty from "@/Components/Empty.vue";

const props = defineProps({
    user_id: Number
})

const adjustmentHistories = ref([]);
const { formatAmount, formatDateTime } = transactionFormat();
const loading = ref(false);
const visible = ref(false);
const selected_row = ref();

const getAdjustmentHistoryData = async () => {
    loading.value = true;

    try {
        const response = await axios.get(`/member/getAdjustmentHistoryData?id=${props.user_id}`);
        adjustmentHistories.value = response.data;
    } catch (error) {
        console.error('Error fetching adjustment history data:', error);
    } finally {
        loading.value = false;
    }
}

getAdjustmentHistoryData();

const rowClicked = (data) => {
    visible.value = true;
    selected_row.value = data;
}
</script>

<template>
    <div v-if="adjustmentHistories?.length <= 0" class="flex flex-col items-center flex-1 self-stretch">
        <Empty :message="$t('public.no_history_yet')" />
    </div>

    <!-- data table -->
    <div v-else class="p-6 flex flex-col items-center justify-center self-stretch gap-6 bg-white shadow-toast rounded-2xl max-h-[360px]">
        <DataTable
            :value="adjustmentHistories"
            removableSort
            :loading="loading"
            @row-click="rowClicked($event.data)"
            selectionMode="single"
            class="hidden md:block"
            scrollable
            scrollHeight="300px"
        >
            <template #loading>
                <div class="flex flex-col gap-2 items-center justify-center">
                    <Loader />
                    <span class="text-sm text-gray-700">{{ $t('public.loading_users_caption') }}</span>
                </div>
            </template>
            <Column field="created_at" sortable style="width: 25%" headerClass="hidden md:table-cell">
                <template #header>
                    <span class="hidden md:block">{{ $t('public.date') }}</span>
                </template>
                <template #body="slotProps">
                    <div class="py-1">
                        {{ formatDateTime(slotProps.data.created_at) }}
                    </div>
                </template>
            </Column>
            <Column field="transaction_type" style="width: 25%" headerClass="hidden md:table-cell">
                <template #header>
                    <span class="hidden md:block">{{ $t('public.type') }}</span>
                </template>
                <template #body="slotProps">
                    <div class="py-1">
                        {{ $t(`public.${slotProps.data.transaction_type}`) }}
                    </div>
                </template>
            </Column>
            <Column field="account_no" style="width: 25%" headerClass="hidden md:table-cell">
                <template #header>
                    <span class="hidden md:block">{{ $t('public.account') }}</span>
                </template>
                <template #body="slotProps">
                    <div class="py-1">
                        <span v-if="slotProps.data.transaction_type === 'balance_in' || slotProps.data.transaction_type === 'credit_in'">{{ slotProps.data.to_meta_login }}</span>
                        <span v-else-if="slotProps.data.transaction_type === 'balance_out' || slotProps.data.transaction_type === 'credit_out'">{{ slotProps.data.from_meta_login }}</span>
                        <span v-else>-</span>
                    </div>
                </template>
            </Column>
            <Column field="amount" sortable style="width: 25%" headerClass="hidden md:table-cell">
                <template #header>
                    <span class="hidden md:block">{{ $t('public.amount') }} ($)</span>
                </template>
                <template #body="slotProps">
                    <div class="py-1">
                        {{ formatAmount(slotProps.data.transaction_amount) }}
                    </div>
                </template>
            </Column>
        </DataTable>

        <div class="flex flex-col items-center self-stretch overflow-auto md:hidden" style="-ms-overflow-style: none; scrollbar-width: none;">
            <div
                v-for="(history, index) in adjustmentHistories"
                :key="index"
                class="flex py-2 items-center gap-5 self-stretch border-b border-gray-200"
                :class="{ 'border-transparent': index === history.length - 1 }"
                @click="rowClicked(history)"
            >
                <div class="flex flex-col items-start justify-center gap-1 w-full">
                    <div class="flex gap-2 items-center">
                        <span class="text-gray-950 text-sm font-semibold">{{ $t(`public.${history.transaction_type}`) }}</span>
                        <span v-if="history.transaction_type === 'balance_in' || history.transaction_type === 'credit_in'">{{ history.to_meta_login }}</span>
                        <span v-else-if="history.transaction_type === 'balance_out' || history.transaction_type === 'credit_out'">{{ history.from_meta_login }}</span>
                    </div>
                    <span class="text-gray-500 text-xs"> {{ formatDateTime(history.created_at) }}</span>
                </div>
                <div class="w-[120px] truncate text-right font-semibold"
                     :class="{
                            'text-success-500': history.transaction_type === 'deposit',
                            'text-error-500': history.transaction_type === 'withdrawal'
                        }"
                >
                    $ {{ formatAmount(history.transaction_type === 'deposit' ? history.transaction_amount : history.amount) }}
                </div>
            </div>
        </div>

        <Dialog
            v-model:visible="visible"
            modal
            :header="$t('public.details')"
            class="dialog-xs md:dialog-sm"
        >
            <div class="flex flex-col items-center gap-3 self-stretch">
                <div class="flex items-center gap-1 self-stretch">
                    <div class="self-stretch text-gray-500 text-xs font-medium w-[120px]">
                        {{ $t('public.date') }}
                    </div>
                    <div class="text-gray-950 text-sm font-medium">
                        {{ formatDateTime(selected_row.created_at) }}
                    </div>
                </div>
                <div class="flex items-center gap-1 self-stretch">
                    <div class="self-stretch text-gray-500 text-xs font-medium w-[120px]">
                        {{ $t('public.adjustment_type') }}
                    </div>
                    <div class="text-gray-950 text-sm font-medium">
                        {{ $t(`public.${selected_row.transaction_type}`) }}
                    </div>
                </div>
                <div class="flex items-center gap-1 self-stretch">
                    <div class="self-stretch text-gray-500 text-xs font-medium w-[120px]">
                        {{ $t('public.account') }}
                    </div>
                    <div class="text-gray-950 text-sm font-medium">
                        <span v-if="selected_row.transaction_type === 'balance_in' || selected_row.transaction_type === 'credit_in'">{{ selected_row.to_meta_login }}</span>
                        <span v-else-if="selected_row.transaction_type === 'balance_out' || selected_row.transaction_type === 'credit_out'">{{ selected_row.from_meta_login }}</span>
                        <span v-else>-</span>
                    </div>
                </div>
                <div class="flex items-center gap-1 self-stretch">
                    <div class="self-stretch text-gray-500 text-xs font-medium w-[120px]">
                        {{ $t('public.amount') }}
                    </div>
                    <div class="text-gray-950 text-sm font-medium">
                        $ {{ formatAmount(selected_row.transaction_amount) }}
                    </div>
                </div>
                <div class="flex items-center gap-1 self-stretch">
                    <div class="self-stretch text-gray-500 text-xs font-medium w-[120px]">
                        {{ $t('public.remarks') }}
                    </div>
                    <div class="text-gray-950 text-sm font-medium">
                        {{ selected_row.remarks }}
                    </div>
                </div>
            </div>
        </Dialog>
    </div>
</template>
