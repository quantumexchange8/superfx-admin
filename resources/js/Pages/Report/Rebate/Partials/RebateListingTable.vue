<script setup>
import InputText from 'primevue/inputtext';
import Button from '@/Components/Button.vue';
import { ref, watch } from "vue";
import Dialog from 'primevue/dialog';
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import {FilterMatchMode} from "primevue/api";
import { transactionFormat } from '@/Composables/index.js';
import Empty from '@/Components/Empty.vue';
import Loader from "@/Components/Loader.vue";
import {IconSearch, IconCircleXFilled, IconX} from '@tabler/icons-vue';
import Calendar from 'primevue/calendar';

const { formatDate, formatDateTime, formatAmount } = transactionFormat();

const visible = ref(false);
const rebateListing = ref();
const dt = ref();
const loading = ref(false);
const expandedRows = ref({});

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
        let response;
        const [startDate, endDate] = selectedDate;
        let url = `/report/getRebateListing`;

        // Append date range to the URL if it's not null
        if (startDate && endDate) {
            url += `?startDate=${formatDate(startDate)}&endDate=${formatDate(endDate)}`;
        }

        response = await axios.get(url);
        rebateListing.value = response.data.rebateListing;

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

// Define emits
const emit = defineEmits(['update-date']);

// Watch for changes in selectedDate
watch(selectedDate, (newDateRange) => {
    // Implement logic to handle changes in the selected date range
    if (Array.isArray(newDateRange)) {
        const [startDate, endDate] = newDateRange;

        // Check if both dates are valid
        if (startDate && endDate) {
            // Both dates are valid, call function with the full range
            getResults([startDate, endDate]);
            emit('update-date', [startDate, endDate]);
        } else if (startDate || endDate) {
            // Only one date is provided
            // Use the same date for both start and end if one is null
            getResults([startDate || endDate, endDate || startDate]);
            emit('update-date', [startDate || endDate, endDate || startDate]);
        } else if (!(startDate && endDate)) {
            getResults([]);
            emit('update-date', []);
        }
    } else {
        // Handle unexpected formats or types
        console.warn('Invalid date range format:', newDateRange);
    }
}, { immediate: true });

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    name: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
});

const clearFilterGlobal = () => {
    filters.value['global'].value = null;
}

// dialog
const data = ref({});
const openDialog = (rowData) => {
    visible.value = true;
    data.value = rowData;
};

</script>

<template>
    <div class="flex flex-col items-center px-4 py-6 gap-5 self-stretch rounded-2xl border border-gray-200 bg-white shadow-table md:px-6 md:gap-5">
        <DataTable
            v-model:filters="filters"
            :value="rebateListing"
            :paginator="rebateListing?.length > 0"
            removableSort
            :rows="10"
            :rowsPerPageOptions="[10, 20, 50, 100]"
            tableStyle="md:min-width: 50rem"
            paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} entries"
            :globalFilterFields="['name']"
            ref="dt"
            selectionMode="single"
            @row-click="(event) => openDialog(event.data)"
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
            <template #empty><Empty :title="$t('public.empty_rebate_record_title')" :message="$t('public.empty_rebate_record_message')"/></template>
            <template #loading>
                <div class="flex flex-col gap-2 items-center justify-center">
                    <Loader />
                    <span class="text-sm text-gray-700">{{ $t('public.loading_rebate_record_caption') }}</span>
                </div>
            </template>
            <template v-if="rebateListing?.length > 0">
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
                    field="volume"
                    sortable
                    :header="`${$t('public.volume')}&nbsp;(Ł)`"
                    class="hidden md:table-cell"
                >
                    <template #body="slotProps">
                        {{ formatAmount(slotProps.data.volume) }}
                    </template>
                </Column>
                <Column
                    field="rebate"
                    sortable
                    :header="`${$t('public.rebate')}&nbsp;($)`"
                    class="hidden md:table-cell"
                >
                    <template #body="slotProps">
                        {{ formatAmount(slotProps.data.rebate) }}
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
                                    <div class="text-gray-500 text-xs">
                                        {{ `${slotProps.data.meta_login}&nbsp;|&nbsp;${formatAmount(slotProps.data.volume)}&nbsp;Ł` }}
                                    </div>
                                </div>
                            </div>
                            <div class="overflow-hidden text-right text-ellipsis font-semibold">
                                $&nbsp;{{ formatAmount(slotProps.data.rebate) }}
                            </div>
                        </div>
                    </template>
                </Column>
            </template>
        </DataTable>
    </div>

    <Dialog v-model:visible="visible" modal :header="$t('public.rebate_details')" class="dialog-xs md:dialog-md">
        <div class="flex flex-col justify-center items-start pb-4 gap-3 self-stretch border-b border-gray-200 md:flex-row md:pt-4 md:justify-between">
            <!-- below md -->
            <span class="md:hidden self-stretch text-gray-950 text-xl font-semibold">$&nbsp;{{ formatAmount(data.rebate) }}</span>
            <div class="flex items-center gap-3 self-stretch">
                <div class="w-9 h-9 rounded-full overflow-hidden grow-0 shrink-0">
                    <DefaultProfilePhoto />
                </div>
                <div class="flex flex-col items-start max-w-60 md:max-w-[300px]">
                    <span class="self-stretch overflow-hidden text-gray-950 text-ellipsis text-sm font-medium">{{ data.name }}</span>
                    <span class="self-stretch overflow-hidden text-gray-500 text-ellipsis text-xs">{{ data.email }}</span>
                </div>
            </div>
            <!-- above md -->
            <span class="hidden md:block w-[180px] text-gray-950 text-right text-xl font-semibold">$&nbsp;{{ formatAmount(data.rebate) }}</span>
        </div>

        <div class="flex flex-col justify-center items-center py-4 gap-3 self-stretch border-b border-gray-200 md:border-none">
            <div class="min-w-[100px] flex gap-1 flex-grow items-center self-stretch">
                <span class="self-stretch text-gray-500 text-xs font-medium w-[88px] md:w-[140px]">{{ $t('public.rebate_date') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium flex-grow">{{ `${formatDate(selectedDate[0] ?? '2024/01/01')}&nbsp;-&nbsp;${formatDate(selectedDate[1] ?? today)}` }}</span>
            </div>
            <div class="min-w-[100px] flex gap-1 flex-grow items-center self-stretch">
                <span class="self-stretch text-gray-500 text-xs font-medium w-[88px] md:w-[140px]">{{ $t('public.account') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium flex-grow">{{ data.meta_login }}</span>
            </div>
            <div class="min-w-[100px] flex gap-1 flex-grow items-center self-stretch">
                <span class="self-stretch text-gray-500 text-xs font-medium w-[88px] md:w-[140px]">{{ $t('public.total_trade_volume') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium flex-grow">{{ formatAmount(data.volume) }}&nbsp;Ł</span>
            </div>
        </div>

        <div class="flex flex-col items-start pt-2 self-stretch md:pt-0">
            <DataTable
                v-model:expandedRows="expandedRows"
                :value="data.summary"
                dataKey="execute_at"
                removableSort
                :pt="{
                    column: {
                        headercell: ({ context, props }) => ({
                            class: [
                                'font-semibold',
                                'text-xs',
                                'uppercase',
                                'box-border',

                                // Position
                                { 'sticky z-20 border-b': props.frozen || props.frozen === '' },

                                { relative: context.resizable },

                                // Shape
                                { 'first:border-l border-y border-r': context?.showGridlines },
                                'border-0 border-b border-solid',

                                // Spacing
                                context?.size === 'small' ? 'py-[0.375rem] px-2' : context?.size === 'large' ? 'py-[0.9375rem] px-5' : 'p-3',

                                // Color
                                (props.sortable === '' || props.sortable) && context.sorted ? 'bg-primary-100 text-primary-500' : 'bg-gray-100 text-gray-950',
                                'border-gray-200 ',

                                // States
                                { 'hover:bg-gray-100': (props.sortable === '' || props.sortable) && !context?.sorted },
                                'focus-visible:outline-none focus-visible:outline-offset-0 focus-visible:ring-1 focus-visible:ring-inset focus-visible:ring-primary-500 dark:focus-visible:ring-primary-400',

                                // Transition
                                { 'transition duration-200': props.sortable === '' || props.sortable },

                                // Misc
                                { 'cursor-pointer': props.sortable === '' || props.sortable },
                                {
                                    'overflow-hidden space-nowrap border-y bg-clip-padding': context.resizable // Resizable
                                },

                                'hidden md:table-cell',
                            ]
                        }),
                        bodycell: ({ props, context, state, parent }) => ({
                            class: [
                                // Font
                                'text-sm font-semibold md:font-normal',

                                // Alignment
                                'text-left',

                                // Spacing
                                { 'py-2 px-3': context?.size !== 'large' && context?.size !== 'small' && !state['d_editing'] },

                                // Border
                                'border-0 border-b border-solid border-gray-200'
                            ]
                        }),
                    },
                }"
            >
                <!-- Row Expansion Column -->
                <Column expander class="w-9 md:w-20 text-gray-500" />

                <!-- Summary Columns -->
                <Column sortable field="execute_at" header="Date" />
                <Column field="volume" :header="`${$t('public.total_volume')}&nbsp;(Ł)`" class="text-left hidden md:table-cell">
                    <template #body="slotProps">
                        {{ formatAmount(slotProps.data.volume) }}
                    </template>
                </Column>
                <Column field="rebate" :header="`${$t('public.total_rebate')}&nbsp;($)`" class="text-left hidden md:table-cell"/>
                <Column field="rebate" :header="`${$t('public.total_rebate')}&nbsp;($)`"  class="text-right md:hidden">
                    <template #body="slotProps">
                        {{ `$&nbsp;${slotProps.data.rebate}` }}
                    </template>
                </Column>

                <!-- Row Expansion Content -->
                <template #expansion="slotProps">
                <!-- Display only details for each summary entry -->
                    <DataTable
                        :value="slotProps.data.details"
                        class="pl-9 md:pl-20"
                        unstyled
                        :pt="{
                            column: {
                                headercell: ({ context, props }) => ({
                                    class: [
                                        'font-semibold',
                                        'text-xs',
                                        'uppercase',
                                        'box-border',

                                        // Position
                                        { 'sticky z-20 border-b': props.frozen || props.frozen === '' },

                                        { relative: context.resizable },

                                        // Shape
                                        { 'first:border-l border-y border-r': context?.showGridlines },
                                        'border-0 border-b border-solid',

                                        // Spacing
                                        context?.size === 'small' ? 'py-[0.375rem] px-2' : context?.size === 'large' ? 'py-[0.9375rem] px-5' : 'p-3',

                                        // Color
                                        (props.sortable === '' || props.sortable) && context.sorted ? 'bg-primary-50 text-primary-500' : 'bg-white text-gray-950',
                                        'border-gray-200 ',

                                        // States
                                        { 'hover:bg-gray-100': (props.sortable === '' || props.sortable) && !context?.sorted },
                                        'focus-visible:outline-none focus-visible:outline-offset-0 focus-visible:ring-1 focus-visible:ring-inset focus-visible:ring-primary-500 dark:focus-visible:ring-primary-400',

                                        // Transition
                                        { 'transition duration-200': props.sortable === '' || props.sortable },

                                        // Misc
                                        { 'cursor-pointer': props.sortable === '' || props.sortable },
                                        {
                                            'overflow-hidden space-nowrap border-y bg-clip-padding': context.resizable // Resizable
                                        },

                                        'hidden md:table-cell',
                                    ]
                                }),
                                bodycell: ({ props, context, state, parent }) => ({
                                    class: [
                                        'flex justify-between items-center md:justify-normal md:items-start',

                                        // Spacing
                                        { 'py-1 md:py-2 px-3': context?.size !== 'large' && context?.size !== 'small' && !state['d_editing'] },

                                        // Border
                                        { 'border-0 border-b border-solid border-gray-200': parent.props.rowIndex != slotProps.data.details.length - 1 }
                                    ]
                                }),
                            },
                        }"
                    >
                        <Column field="name" :header="$t('public.product')" class="text-sm text-left hidden md:table-cell">
                            <template #body="slotProps">
                                {{ $t('public.' + slotProps.data.name) }}
                            </template>
                        </Column>
                        <Column field="volume" :header="$t('public.volume')" class="text-sm text-left hidden md:table-cell">
                            <template #body="slotProps">
                                {{ formatAmount(slotProps.data.volume) }}
                            </template>
                        </Column>
                        <Column field="net_rebate" :header="`${$t('public.rebate')}/Ł&nbsp;($)`" class="text-sm text-left hidden md:table-cell">
                            <template #body="slotProps">
                                {{ formatAmount(slotProps.data.net_rebate) }}
                            </template>
                        </Column>
                        <Column field="rebate" :header="`${$t('public.rebate')}&nbsp;($)`" class="text-sm text-left hidden md:table-cell">
                            <template #body="slotProps">
                                {{ formatAmount(slotProps.data.rebate) }}
                            </template>
                        </Column>
                        <Column field="name" class="md:hidden">
                            <template #body="slotProps">
                                <div class="flex flex-col items-start">
                                    <span class="overflow-hidden text-xs text-gray-950 text-right text-ellipsis font-semibold">{{ $t('public.' + slotProps.data.name) }}</span>
                                    <div class="flex items-center gap-2 self-stretch">
                                        <span class="text-gray-700 text-xs">{{ `${formatAmount(slotProps.data.volume)}&nbsp;Ł` }}</span>
                                        <span class="text-gray-700 text-sm">|</span>
                                        <span class="text-gray-700 text-xs">{{ `$&nbsp;${formatAmount(slotProps.data.net_rebate)}` }}</span>
                                    </div>
                                </div>
                                <span class="w-[100px] overflow-hidden text-sm text-gray-950 text-right text-ellipsis">$&nbsp;{{ formatAmount(slotProps.data.rebate) }}</span>
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </DataTable>
        </div>
    </Dialog>
</template>
