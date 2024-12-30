<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { CalendarIcon } from '@/Components/Icons/outline'
import { HandIcon, CoinsIcon, RocketIcon } from '@/Components/Icons/solid';
import { ref, h, watch, computed, onMounted } from "vue";
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import MultiSelect from 'primevue/multiselect';
import IconField from 'primevue/iconfield';
import Vue3Autocounter from 'vue3-autocounter';
import DepositTransactionTable from "@/Pages/Transaction/Partials/DepositTransactionTable.vue";
import WithdrawalTransactionTable from "@/Pages/Transaction/Partials/WithdrawalTransactionTable.vue";
import TransferTransactionTable from "@/Pages/Transaction/Partials/TransferTransactionTable.vue";
import PayoutTransactionTable from "@/Pages/Transaction/Partials/PayoutTransactionTable.vue";
import {wTrans} from "laravel-vue-i18n";

const totalTransaction = ref(999);
const totalTransactionAmount = ref(999);
const maxAmount = ref(999);
const counterDuration = ref(10);
const months = ref([]);

const getTransactionMonths = async () => {
    try {
        const monthsResponse = await axios.get('/transaction/getTransactionMonths');
        months.value = monthsResponse.data;
    } catch (error) {
        console.error('Error transaction months:', error);
    }
};

onMounted(() => {
    getTransactionMonths();
    // Extract the type from the URL directly
    const queryString = window.location.search;
    const urlParams = new URLSearchParams(queryString);
    const type = urlParams.get('type');

    // Check if the type is valid and set selectedType
    if (type && tabs.value.some(tab => tab.type === type)) {
        selectedType.value = type;
    }

})

const tabs = ref([
    {   
        title: wTrans('public.deposit'),
        component: h(DepositTransactionTable, 
        { copyToClipboard: copyToClipboard }), 
        type: 'deposit' 
    },
    {   
        title: wTrans('public.withdrawal'),
        component: h(WithdrawalTransactionTable, 
        { copyToClipboard: copyToClipboard }), 
        type: 'withdrawal' 
    },
    {   
        title: wTrans('public.transfer'),
        component: h(TransferTransactionTable), 
        type: 'transfer' 
    },
    {   
        title: wTrans('public.payout'),
        component: h(PayoutTransactionTable), 
        type: 'payout' 
    },
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
    totalTransaction.value = 999;
    totalTransactionAmount.value = 999;
    maxAmount.value = 999;
    counterDuration.value = 10;
}

// data overview
const dataOverviews = computed(() => [
    {
        icon: HandIcon,
        total: totalTransaction.value,
        label: 'total_transaction',
    },
    {
        icon: CoinsIcon,
        total: totalTransactionAmount.value,
        label: selectedType.value !== 'payout' ? 'total_approved_amount' : 'total_payout_amount',
    },
    {
        icon: RocketIcon,
        total: maxAmount.value,
        label: 'maximum_amount',
    },
]);
// Function to get the current month and year as a string
const getCurrentMonthYear = () => {
  const date = new Date();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  return `${month}/${year}`;
};

// Reactive variables
const selectedMonths = ref([getCurrentMonthYear()]);

function copyToClipboard(text) {
    const textToCopy = text;

    const textArea = document.createElement('textarea');
    document.body.appendChild(textArea);

    textArea.value = textToCopy;
    textArea.select();

    try {
        const successful = document.execCommand('copy');
        if (successful) {
            console.log('Copied to clipboard:', textToCopy);
        } else {
            console.error('Unable to copy to clipboard.');
        }
    } catch (err) {
        console.error('Copy to clipboard failed:', err);
    }

    document.body.removeChild(textArea);
}

const handleUpdateTotals = (data) => {
  totalTransaction.value = data.totalTransaction;
  totalTransactionAmount.value = data.totalTransactionAmount;
  maxAmount.value = data.maxAmount;
  counterDuration.value = 1;
};

</script>

<template>
    <AuthenticatedLayout :title="$t('public.transaction')">
        <div class="flex flex-col gap-5 md:gap-8">
            <div class="flex flex-col gap-5 self-stretch md:flex-row md:justify-between md:items-center">
                <TabView class="flex flex-col" :activeIndex="activeIndex" @tab-change="updateType">
                    <TabPanel v-for="(tab, index) in tabs" :key="index" :header="tab.title" />
                </TabView>
                <IconField iconPosition="left" class="relative flex items-center w-full md:w-60">
                    <CalendarIcon class="z-20 w-5 h-5 text-gray-400" />
                    <MultiSelect v-model="selectedMonths" filter :options="months" :placeholder="$t('public.month_placeholder')" :maxSelectedLabels="1" :selectedItemsLabel="`${selectedMonths.length} ${$t('public.months_selected')}`" class="w-full md:w-60">
                        <template #filtericon>{{ $t('public.select_all') }}</template>
                    </MultiSelect>
                </IconField>

            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 w-full gap-5">
                <div
                    v-for="(item, index) in dataOverviews"
                    :key="index"
                    class="flex justify-center items-center py-4 px-6 gap-5 self-stretch rounded-2xl bg-white shadow-toast md:flex-col md:flex-grow md:py-6 md:gap-3"
                >
                    <component :is="item.icon" class="w-12 h-12 grow-0 shrink-0" />
                    <div class="flex flex-col items-center gap-1 flex-grow md:flex-grow-0 md:self-stretch">
                        <div class="self-stretch text-gray-950 text-lg font-semibold md:text-xl md:text-center">
                            <vue3-autocounter ref="counter" :startAmount="0" :endAmount="item.total" :duration="counterDuration" separator="," decimalSeparator="." :autoinit="true" />
                        </div>
                        <span class="self-stretch text-gray-500 text-xs md:text-sm md:text-center">{{ $t('public.' + item.label) }}</span>
                    </div>
                </div>
            </div>
            <div class="flex flex-col items-center py-6 px-4 gap-5 self-stretch rounded-2xl border border-gray-200 bg-white shadow-table md:py-6 md:gap-6">
                <component :is="tabs[activeIndex]?.component" :selectedMonths="selectedMonths" :selectedType="selectedType" @update-totals="handleUpdateTotals" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
