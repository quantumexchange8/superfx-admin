<script setup>
import { ref, h, watch, computed } from "vue";
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import Deposit from '@/Pages/Report/GroupTransaction/Partials/Deposit.vue';
import Withdrawal from '@/Pages/Report/GroupTransaction/Partials/Withdrawal.vue';
import { DepositIcon, WithdrawalIcon, NetBalanceIcon } from '@/Components/Icons/solid';
import { usePage } from "@inertiajs/vue3";
import { trans, wTrans } from "laravel-vue-i18n";
import { transactionFormat } from "@/Composables/index.js";

const { formatAmount } = transactionFormat();

// Initialize the form with user data
const user = usePage().props.auth.user;

const groupTotalDeposit = ref(0);
const groupTotalWithdrawal = ref(0);
const groupTotalNetBalance = ref(0);

const tabs = ref([
    { title: wTrans('public.deposit'), component: h(Deposit), type: 'deposit' },
    { title: wTrans('public.withdrawal'), component: h(Withdrawal), type: 'withdrawal' },
]);

const selectedType = ref('deposit');
const activeIndex = ref(tabs.value.findIndex(tab => tab.type === selectedType.value));

// Watch for changes in selectedType and update the activeIndex accordingly
watch(selectedType, (newType) => {
    const index = tabs.value.findIndex(tab => tab.type === newType);
    if (index >= 0) {
        activeIndex.value = index;
    }
});

function updateType(event) {
    const selectedTab = tabs.value[event.index];
    selectedType.value = selectedTab.type;
}

// data overview
const dataOverviews = computed(() => [
    {
        icon: DepositIcon,
        total: groupTotalDeposit.value,
        label: trans('public.group_total_deposit'),
        borderColor: 'border-green',
    },
    {
        icon: WithdrawalIcon,
        total: groupTotalWithdrawal.value,
        label: trans('public.group_total_withdrawal'),
        borderColor: 'border-pink',
    },
    {
        icon: NetBalanceIcon,
        total: groupTotalNetBalance.value,
        label: trans('public.group_total_net_balance'),
        borderColor: 'border-[#FEDC32]',
    },
]);

const handleUpdateGroupTotals = ({ deposit, withdrawal, netBalance }) => {
    groupTotalDeposit.value = deposit;
    groupTotalWithdrawal.value = withdrawal;
    groupTotalNetBalance.value = netBalance;
};
</script>
<template>
    <div class="flex flex-col items-center gap-5 self-stretch">
        <!-- overview data -->
        <div
            class="grid gap-3 md:gap-5 w-full grid-cols-2 md:grid-cols-3"
        >
            <div
                class="flex flex-col justify-center items-center gap-3 px-6 pt-6 pb-4 rounded-2xl border-b-8 shadow-toast"
                :class="[item.borderColor, { 'col-span-2 md:col-span-1': index === 2 }]"
                v-for="(item, index) in dataOverviews"
                :key="index"
            >
                <component :is="item.icon" class="w-12 h-12 grow-0 shrink-0 rounded-full" />
                <div class="flex flex-col items-center gap-1 w-full">
                    <span class="text-gray-500 text-xs md:text-sm">{{ item.label }}</span>
                    <div class="text-gray-950 text-lg md:text-xl font-semibold">
                        $ {{ formatAmount(item.total) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center self-stretch">
            <TabView class="flex flex-col" :activeIndex="activeIndex" @tab-change="updateType">
                <TabPanel v-for="(tab, index) in tabs" :key="index" :header="tab.title" />
            </TabView>
        </div>
        
        <component :is="tabs[activeIndex]?.component" :key="selectedType" :selectedType="selectedType" @updateGroupTotals="handleUpdateGroupTotals" />
    </div>
</template>
