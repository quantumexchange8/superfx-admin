<script setup>
import { ref, watch } from 'vue';
import { IconCloudDownload, IconX } from '@tabler/icons-vue';
import Button from '@/Components/Button.vue';
import Calendar from 'primevue/calendar';
import Empty from '@/Components/Empty.vue';
import Loader from "@/Components/Loader.vue";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import ColumnGroup from "primevue/columngroup";
import Row from "primevue/row";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import { transactionFormat } from '@/Composables/index.js';

const { formatAmount, formatDate, formatDateTime } = transactionFormat();

const props = defineProps({
    group: Object,
    date: Array,
})

const transactions = ref();
const totalAmount = ref(0);
const totalFee = ref(0);
const totalBalance = ref(0);
const loading = ref(false);

// Get current date
const today = new Date();

// Define minDate and maxDate
const minDate = ref(new Date(today.getFullYear(), today.getMonth(), 1));
const maxDate = ref(today);

// Reactive variable for selected date range
const selectedDate = ref(props.date);

// Clear date selection
const clearDate = () => {
    selectedDate.value = [];
};

// Fetch transactions based on the selected date range and group ID
const getResult = async (selectedDate = []) => {
    loading.value = true;

    try {
        let response;
        const [startDate, endDate] = selectedDate;
        
        // Build URL with query parameters
        let url = `/group/getGroupTransaction?groupId=${props.group.id}`;

        if (startDate && endDate) {
            url += `&startDate=${formatDate(startDate)}&endDate=${formatDate(endDate)}`;
        }

        // Fetch data from the API
        response = await axios.get(url);
        transactions.value = response.data.transactions;
        totalAmount.value = response.data.totalAmount;
        totalFee.value = response.data.totalFee;
        totalBalance.value = response.data.totalBalance;
    } catch (error) {
        console.error('Error fetching group transactions:', error);
    } finally {
        loading.value = false;
    }
};

// Watch for changes in the selectedDate prop
watch(() => props.date, (newDate) => {
    selectedDate.value = newDate;
    getResult(selectedDate.value);
}, { immediate: true });

watch(selectedDate, (newDateRange) => {
    if (Array.isArray(newDateRange)) {
        const [startDate, endDate] = newDateRange;

        if (startDate && endDate) {
            getResult([startDate, endDate]);
        } else if (startDate || endDate) {
            getResult([startDate || endDate, endDate || startDate]);
        } else {
            getResult([]);
        }
    } else {
        console.warn('Invalid date range format:', newDateRange);
    }
});

</script>

<template>
    <div class="flex flex-col items-center gap-5 flex-grow self-stretch">
        <DataTable
            :value="transactions"
            removableSort
            scrollable
            scrollHeight="600px"
            tableStyle="lg:min-width: 50rem"
            ref="dt"
            :loading="loading"
            >
            <template #header>
                <div class="flex flex-col md:flex-row gap-3 items-center self-stretch mb-5">
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
            <template #empty><Empty :message="$t('public.no_transaction_caption')"/></template>
            <template #loading>
                <div class="flex flex-col gap-2 items-center justify-center">
                    <Loader />
                    <span class="text-sm text-gray-700">{{ $t('public.loading_transactions_caption') }}</span>
                </div>
            </template>
            <Column
                field="approved_at"
                sortable
                :header="$t('public.date')"
                class="hidden md:table-cell"
            >
                <template #body="slotProps">
                    {{ formatDate(slotProps.data.approved_at)}}
                </template>
            </Column>
            <Column
                field="name"
                sortable
                :header="$t('public.member')"
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
                            <div class="md:max-w-[140px] lg:max-w-[220px] w-full text-gray-500 text-xs truncate">
                                {{ slotProps.data.email }}
                            </div>
                        </div>
                    </div>
                </template>
            </Column>
            <Column
                field="amount"
                sortable
                :header="`${$t('public.amount')}&nbsp;($)`"
                class="hidden md:table-cell"
            >
                <template #body="slotProps">
                    <div class="text-sm"
                        :class="{
                            'text-success-500': slotProps.data.transaction_type === 'deposit',
                            'text-red-500': slotProps.data.transaction_type === 'withdrawal'
                        }"
                    >
                        {{ formatAmount(slotProps.data.amount) }}
                    </div>
                </template>
            </Column>
            <Column
                field="transaction_charges"
                sortable
                :header="`${$t('public.fee')}&nbsp;($) `"
                class="hidden md:table-cell"
            >
                <template #body="slotProps">
                    {{ slotProps.data.transaction_type === 'withdrawal' ? '-' : formatAmount(slotProps.data.transaction_charges) }}
                </template>
            </Column>
            <Column
                field="transaction_amount"
                sortable
                :header="`${$t('public.balance')}&nbsp;($)`"
                class="hidden md:table-cell"
            >
                <template #body="slotProps">
                    <div class="text-sm"
                        :class="{
                            'text-success-500': slotProps.data.transaction_type === 'deposit',
                            'text-red-500': slotProps.data.transaction_type === 'withdrawal'
                        }"
                    >
                        {{ formatAmount(slotProps.data.transaction_amount) }}
                    </div>
                </template>
            </Column>
            <Column class="md:hidden">
                <template #body="slotProps">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                                <DefaultProfilePhoto />
                            </div>
                            <div class="flex flex-col items-start gap-1">
                                <div class="text-sm font-semibold text-gray-950 truncate max-w-[120px]">
                                    {{ slotProps.data.name }}
                                </div>
                                <div class="text-gray-500 text-xs">
                                    {{ formatDate(slotProps.data.approved_at) }}
                                </div>
                            </div>
                        </div>
                        <div 
                            class="w-[100px] overflow-hidden text-right text-ellipsis font-semibold"
                            :class="{
                                'text-success-500': slotProps.data.transaction_type === 'deposit',
                                'text-red-500': slotProps.data.transaction_type === 'withdrawal'
                            }"
                        >
                            $&nbsp;{{ formatAmount(slotProps.data.transaction_amount) }}
                        </div>
                    </div>
                </template>
            </Column>
            <ColumnGroup type="footer" v-if="transactions?.length > 0" class="sticky">
                <Row>
                    <Column class="hidden md:table-cell" :footer="$t('public.total') + ':'" :colspan="2" footerStyle="text-align:right" />
                    <Column class="hidden md:table-cell" :footer="formatAmount(totalAmount)" />
                    <Column class="hidden md:table-cell" :footer="formatAmount(totalFee)" />
                    <Column class="hidden md:table-cell" :footer="formatAmount(totalBalance)" />
                </Row>
            </ColumnGroup>
        </DataTable>
    </div>

</template>