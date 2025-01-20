<script setup>
import InputText from 'primevue/inputtext';
import Button from '@/Components/Button.vue';
import { CalendarIcon } from '@/Components/Icons/outline'
import { ref, onMounted, watch, watchEffect, computed } from "vue";
import {usePage} from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import {FilterMatchMode} from "primevue/api";
import { transactionFormat } from '@/Composables/index.js';
import Empty from '@/Components/Empty.vue';
import Loader from "@/Components/Loader.vue";
import {IconSearch, IconCircleXFilled, IconAdjustments, IconX} from '@tabler/icons-vue';
import Calendar from 'primevue/calendar';

const { formatDate, formatDateTime, formatAmount } = transactionFormat();

// Define props
const props = defineProps({
    selectedType: String,
});

const selectedType = ref(props.selectedType);
const transactions = ref();
const groupTotalDeposit = ref(0);
const groupTotalWithdrawal = ref(0);
const groupTotalNetBalance = ref(0);
const dt = ref();
const loading = ref(false);

// Watch for changes in selectedType and call getResults when it changes
watch(() => props.selectedType, (newType) => {
    selectedType.value = newType
}, { immediate: true });

// Get current date
const today = new Date();

// Define minDate as the start of the current month and maxDate as today
const minDate = ref(new Date(today.getFullYear(), today.getMonth(), 1));
const maxDate = ref(today);

// Reactive variable for selected date range
const selectedDate = ref([minDate.value, maxDate.value]);

const getResults = async (selectedDate = []) => {
    loading.value = true;

    try {
        const [startDate, endDate] = selectedDate;
        let url = `/report/getGroupTransaction?type=${selectedType.value}`;

        // Append date range to the URL if it's not null
        if (startDate && endDate) {
            url += `&startDate=${formatDate(startDate)}&endDate=${formatDate(endDate)}`;
        }

        const response = await axios.get(url);
        transactions.value = response.data.transactions;
        groupTotalDeposit.value = response.data.groupTotalDeposit;
        groupTotalWithdrawal.value = response.data.groupTotalWithdrawal;
        groupTotalNetBalance.value = response.data.groupTotalNetBalance;

    } catch (error) {
        console.error('Error fetching rebate listing data:', error);
    } finally {
        loading.value = false;
    }
};

const exportCSV = () => {
    dt.value.exportCSV();
};

// Clear date selection
const clearDate = () => {
    selectedDate.value = [];
};

// Watch for changes in selectedType
watch(() => props.selectedType, (newType) => {
    selectedType.value = newType;
    getResults(selectedDate.value); // Fetch results based on new type and current date range
});

// Watch for changes in selectedDate
watch(selectedDate, (newDateRange) => {
    if (Array.isArray(newDateRange)) {
        const [startDate, endDate] = newDateRange;
        if (startDate && endDate) {
            getResults([startDate, endDate]);
        } else if (startDate || endDate) {
            getResults([startDate || endDate, endDate || startDate]);
        } else {
            getResults([]);
        }
    } else {
        console.warn('Invalid date range format:', newDateRange);
    }
});

const emit = defineEmits(['updateGroupTotals']);

watch([groupTotalDeposit, groupTotalWithdrawal, groupTotalNetBalance], ([deposit, withdrawal, netBalance]) => {
    emit('updateGroupTotals', {
        deposit,
        withdrawal,
        netBalance
    });
});

onMounted(() => {
    getResults(selectedDate.value);
})

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    name: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
});

const clearFilterGlobal = () => {
    filters.value['global'].value = null;
}
</script>

<template>
    <div class="flex flex-col items-center px-4 py-6 gap-5 self-stretch rounded-2xl border border-gray-200 bg-white shadow-table md:px-6 md:gap-5">
        <DataTable
            v-model:filters="filters"
            :value="transactions"
            :paginator="transactions?.length > 0"
            removableSort
            :rows="10"
            :rowsPerPageOptions="[10, 20, 50, 100]"
            tableStyle="md:min-width: 50rem"
            paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} entries"
            :globalFilterFields="['name']"
            selectionMode="single"
            ref="dt"
            :loading="loading"
            >
            <template #header>
                <div class="flex flex-col md:flex-row gap-3 items-center self-stretch">
                    <div class="relative w-full md:w-60">
                        <div class="absolute top-2/4 -mt-[9px] left-4 text-gray-400">
                            <IconSearch size="20" stroke-width="1.25" />
                        </div>
                        <InputText v-model="filters['global'].value" :placeholder="$t('public.keyword_search')" class="font-normal pl-12 w-full md:w-60" />
                        <div
                            v-if="filters['global'].value !== null"
                            class="absolute top-2/4 -mt-2 right-4 text-gray-300 hover:text-gray-400 select-none cursor-pointer"
                            @click="clearFilterGlobal"
                        >
                            <IconCircleXFilled size="16" />
                        </div>
                    </div>
                    <div class="w-full flex flex-col gap-3 md:flex-row">
                        <div class="relative w-full md:w-[272px]">
                            <Calendar
                                v-model="selectedDate"
                                selectionMode="range"
                                :manualInput="false"
                                :minDate="minDate"
                                :maxDate="maxDate"
                                dateFormat="dd/mm/yy"
                                showIcon
                                iconDisplay="input"
                                placeholder="yyyy/mm/dd - yyyy/mm/dd"
                                class="w-full md:w-[272px]"
                            />
                            <div
                                v-if="selectedDate && selectedDate.length > 0"
                                class="absolute top-2/4 -mt-2.5 right-4 text-gray-400 select-none cursor-pointer bg-white"
                                @click="clearDate"
                            >
                                <IconX size="20" />
                            </div>
                        </div>
                        <div class="w-full flex justify-end">
                            <Button
                                variant="primary-outlined"
                                @click="exportCSV($event)"
                                class="w-full md:w-auto"
                            >
                                {{ $t('public.export') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </template>
            <template #empty><Empty :title="$t('public.empty_group_transaction_title')" :message="$t('public.empty_group_transaction_deposit_message')"/></template>
            <template #loading>
                <div class="flex flex-col gap-2 items-center justify-center">
                    <Loader />
                    <span class="text-sm text-gray-700">{{ $t('public.loading_transactions_caption') }}</span>
                </div>
            </template>
            <template v-if="transactions?.length > 0">
                <Column
                    field="created_at"
                    sortable
                    :header="$t('public.date')"
                    class="hidden md:table-cell"
                >
                    <template #body="slotProps">
                        {{ formatDate(slotProps.data.created_at) }}
                    </template>
                </Column>
                <Column
                    field="name"
                    sortable
                    :header="$t('public.name')"
                    class="hidden md:table-cell"
                >
                    <template #body="slotProps">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                                <DefaultProfilePhoto />
                            </div>
                            <div class="flex flex-col items-start">
                                <div class="font-medium">
                                    {{ slotProps.data.name }}
                                </div>
                                <div class="text-gray-500 text-xs">
                                    {{ slotProps.data.email }}
                                </div>
                            </div>
                        </div>
                    </template>
                </Column>
                <Column
                    field="meta_login"
                    :header="`${$t('public.account')}`"
                    class="hidden md:table-cell"
                >
                    <template #body="slotProps">
                        {{ slotProps.data.meta_login }}
                    </template>
                </Column>
                <Column
                    field="transaction_amount"
                    sortable
                    :header="`${$t('public.amount')}`"
                    class="hidden md:table-cell"
                >
                    <template #body="slotProps">
                        {{ formatAmount(slotProps.data.transaction_amount) }}
                    </template>
                </Column>
                <Column class="md:hidden">
                    <template #body="slotProps">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                                    <DefaultProfilePhoto />
                                </div>
                                <div class="flex flex-col items-start">
                                    <div class="text-sm font-semibold">
                                        {{ slotProps.data.name }}
                                    </div>
                                    <div class="text-gray-500 text-sm">
                                        {{ `${formatDate(slotProps.data.created_at)}&nbsp;|&nbsp;${slotProps.data.meta_login}` }}
                                    </div>
                                </div>
                            </div>
                            <div class="overflow-hidden text-right text-ellipsis font-semibold">
                                $&nbsp;{{ formatAmount(slotProps.data.transaction_amount) }}
                            </div>
                        </div>
                    </template>
                </Column>
            </template>
        </DataTable>
    </div>

</template>
