<script setup>
import {IconAlertCircleFilled} from '@tabler/icons-vue';
import { ref, computed, watchEffect } from 'vue';
import Empty from '@/Components/Empty.vue';
import { generalFormat, transactionFormat } from "@/Composables/index.js";
import { usePage } from "@inertiajs/vue3";
import MemberAccountActions from "@/Pages/Member/Listing/Partials/MemberAccountActions.vue";

const props = defineProps({
    user_id: Number
})

const { formatAmount } = transactionFormat();
const { formatRgbaColor } = generalFormat()
const tradingAccounts = ref();

const getTradingAccounts = async () => {
    try {
        const response = await axios.get(`/member/getTradingAccounts?id=${props.user_id}`);

        tradingAccounts.value = response.data.tradingAccounts;
        // console.log(tradingAccounts);
    } catch (error) {
        console.error('Error get trading accounts:', error);
    }
};
getTradingAccounts();

// Function to check if an account is inactive for 90 days
function isInactive(date) {
  const updatedAtDate = new Date(date);
  const currentDate = new Date();

  // Get only the date part (remove time)
  const updatedAtDay = new Date(updatedAtDate.getFullYear(), updatedAtDate.getMonth(), updatedAtDate.getDate());
  const currentDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), currentDate.getDate());

  // Calculate the difference in days by direct subtraction
  const diffDays = (currentDay - updatedAtDay) / (1000 * 60 * 60 * 24);

  // Determine if inactive (more than 90 days)
  return diffDays > 90;
}

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getTradingAccounts();
    }
});

</script>

<template>
    <div v-if="tradingAccounts?.length <= 0">
        <Empty message="No Trading Account Yet" />
    </div>
    <div v-else class="grid md:grid-cols-2 gap-5">
        <div
            v-for="tradingAccount in tradingAccounts" :key="tradingAccount.id"
            class="flex flex-col min-w-[300px] items-center px-5 py-4 gap-3 rounded-2xl border-l-8 bg-white shadow-toast"
            :style="{'borderColor': `#${tradingAccount.account_type_color}`}"
        >
            <div class="flex justify-between items-center self-stretch">
                <div class="flex items-center gap-4">
                    <div class="text-gray-950 text-lg font-semibold">#{{ tradingAccount.meta_login }}</div>
                    <div
                        class="flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded"
                        :style="{
                            backgroundColor: formatRgbaColor(tradingAccount.account_type_color, 0.15),
                            color: `#${tradingAccount.account_type_color}`,
                        }"
                    >
                        {{ $t('public.' + tradingAccount.account_type) }}
                    </div>
                    <div v-if="isInactive(tradingAccount.updated_at)" class="text-error-500">
                        <IconAlertCircleFilled :size="20" stroke-width="1.25" />
                    </div>
                </div>
                <MemberAccountActions
                    :account="tradingAccount"
                />
            </div>
            <div class="grid grid-cols-2 gap-2 self-stretch">
                <div class="w-full flex items-center gap-1 flex-grow">
                    <span class="text-gray-500 text-xs w-16">{{ $t('public.balance') }}:</span>
                    <span class="text-gray-950 text-xs font-medium">$ {{ formatAmount(tradingAccount.balance) }}</span>
                </div>
                <div class="w-full flex items-center gap-1 flex-grow">
                    <span class="text-gray-500 text-xs w-16">{{ $t('public.equity') }}:</span>
                    <span class="text-gray-950 text-xs font-medium">$ {{ formatAmount(tradingAccount.equity) }}</span>
                </div>
                <div class="w-full flex items-center gap-1 flex-grow">
                    <span class="text-gray-500 text-xs w-16">{{ tradingAccount.account_type === 'premium_account' ? $t('public.pamm') : $t('public.credit') }}:</span>
                    <div class="text-gray-950 text-xs font-medium">
                        <span v-if="tradingAccount.account_type === 'premium_account'">{{ tradingAccount.asset_master_name ?? '-' }}</span>
                        <span v-else>$ {{ formatAmount(tradingAccount.credit) }}</span>
                    </div>
                </div>
                <div class="w-full flex items-center gap-1 flex-grow">
                    <span class="text-gray-500 text-xs w-16">{{ tradingAccount.account_type === 'premium_account' ? $t('public.mature_in') : $t('public.leverage') }}:</span>
                    <div class="text-gray-950 text-xs font-medium">
                        <span v-if="tradingAccount.account_type === 'premium_account'">{{ tradingAccount.asset_master_name ? tradingAccount.remaining_days + ' ' + $t('public.days') : '-' }}</span>
                        <span v-else>{{ `1:${tradingAccount.leverage}` }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
