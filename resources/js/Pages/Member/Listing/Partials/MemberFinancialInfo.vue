<script setup>
import { IconCoins } from '@tabler/icons-vue';
import { LockedIcon, DepositIcon, WithdrawalIcon } from '@/Components/Icons/solid';
import {computed, h, ref, watchEffect} from "vue";
import Empty from '@/Components/Empty.vue';
import WalletAdjustment from '@/Pages/Member/Listing/Partials/WalletAdjustment.vue'
import {wTrans} from "laravel-vue-i18n";
import {transactionFormat} from "@/Composables/index.js";
import {usePage} from "@inertiajs/vue3";

const props = defineProps({
    user_id: Number
})

const totalDeposit = ref(0);
const totalWithdrawal = ref(0);
const transactionHistory = ref([]);
const rebateWallet = ref(null);
const { formatAmount, formatDateTime } = transactionFormat();

const getFinancialData = async () => {
    try {
        const response = await axios.get(`/member/getFinancialInfoData?id=${props.user_id}`);

        totalDeposit.value = response.data.totalDeposit;
        totalWithdrawal.value = response.data.totalWithdrawal;
        transactionHistory.value = response.data.transactionHistory;
        rebateWallet.value = response.data.rebateWallet;
    } catch (error) {
        console.error('Error get info:', error);
    }
};

getFinancialData();

const overviewData = computed(() =>  [
    {
        label: wTrans('public.total_deposit'),
        value: totalDeposit.value,
        icon: h(DepositIcon),
    },
    {
        label: wTrans('public.total_withdrawal'),
        value: totalWithdrawal.value,
        icon: h(WithdrawalIcon),
    },
]);

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getFinancialData();
    }
});
</script>

<template>
    <div class="flex flex-col xl:flex-row items-start gap-5 self-stretch">
        <div class="flex flex-col md:flex-row xl:flex-col gap-5 w-full xl:max-w-[438px]">
            <!-- Overview -->
            <div class="flex flex-row md:flex-col xl:flex-row items-center gap-5 self-stretch w-full">
                <div
                    v-for="overview in overviewData"
                    class="py-5 px-6 flex flex-col md:flex-row xl:flex-col gap-5 items-start w-full bg-white shadow-toast rounded-2xl"
                >
                    <component :is="overview.icon" class="w-[42px] h-[42px]" />
                    <div class="flex flex-col items-start gap-1 self-stretch">
                        <div class="text-gray-500 text-xs max-w-[90px] md:max-w-full truncate">{{ overview.label }}</div>
                        <div class="md:text-lg text-gray-950 font-semibold truncate">
                            $ {{ formatAmount(overview.value) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Wallet -->
            <div class="w-full xl:w-[438px] h-[200px] self-stretch relative">
                <div class="flex flex-col justify-center items-start px-6 py-5 gap-5 rounded-2xl bg-logo relative z-0 h-[200px]">
                    <div class="flex justify-between items-start self-stretch">
                        <div class="w-10 h-10 p-2.5 flex justify-center items-center rounded-lg bg-[#FAFAFF] text-logo">
                            <IconCoins size="20" stroke-width="1.25" />
                        </div>
                        <WalletAdjustment type="rebate" :rebateWallet="rebateWallet" />
                    </div>
                    <div class="flex flex-col justify-center items-start px-5 py-4 gap-2 self-stretch bg-white bg-opacity-30">
                        <div class="text-gray-100 text-xs font-medium">{{ $t('public.rebate_balance') }}</div>
                        <div class="text-white text-xxl font-semibold">$ {{ formatAmount(rebateWallet ? rebateWallet.balance : 0) }}</div>
                    </div>
                </div>
                <!-- Locked Section -->
                <div
                    v-if="!rebateWallet"
                    class="absolute inset-0 flex flex-col justify-center items-center rounded-2xl shadow-input backdrop-blur-sm z-10"
                >
                    <LockedIcon class="w-[100px] h-[100px] flex-shrink-0" />
                    <div class="text-gray-950 text-center text-sm font-semibold">{{ $t('public.rebate_lock_desc') }}</div>
                </div>
            </div>
        </div>

        <!-- Transaction History -->
        <div class="bg-white flex flex-col p-4 md:py-6 md:px-8 gap-3 w-full self-stretch shadow-toast rounded-2xl max-h-[360px] md:max-h-[372px]">
            <div class="flex py-2 items-center self-stretch">
                <div class="text-gray-950 text-sm font-bold">{{ $t('public.recent_transaction' )}}</div>
            </div>
            <div v-if="transactionHistory?.length <= 0" class="flex flex-col items-center flex-1 self-stretch">
                <Empty :message="$t('public.no_transaction_yet')" />
            </div>
            <div v-else class="flex flex-col items-center flex-1 self-stretch overflow-auto" style="-ms-overflow-style: none; scrollbar-width: none;">
                <div
                    v-for="(transaction, index) in transactionHistory"
                    :key="index"
                    class="flex py-2 items-center gap-5 self-stretch border-b border-gray-200"
                    :class="{ 'border-transparent': index === transactionHistory.length - 1 }"
                >
                    <div class="flex flex-col items-start justify-center gap-1 w-full">
                        <span class="text-gray-950 text-sm font-semibold">{{ transaction.transaction_type === 'deposit' || transaction.transaction_type === 'balance_in' ? transaction.to_meta_login : transaction.from_meta_login }}</span>
                        <span class="text-gray-500 text-xs"> {{ formatDateTime(transaction.approved_at) }}</span>
                    </div>
                    <div class="w-[120px] truncate text-right font-semibold"
                         :class="{
                            'text-success-500': transaction.transaction_type === 'deposit' || transaction.transaction_type === 'balance_in',
                            'text-error-500': transaction.transaction_type === 'withdrawal' || transaction.transaction_type === 'balance_out',
                        }"
                    >
                        $ {{ formatAmount(transaction.transaction_type === 'deposit' ? transaction.transaction_amount : transaction.amount) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
