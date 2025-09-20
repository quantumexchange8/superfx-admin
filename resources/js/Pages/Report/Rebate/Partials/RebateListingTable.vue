<script setup>
import InputText from 'primevue/inputtext';
import Button from '@/Components/Button.vue';
import {onMounted, ref, watch} from "vue";
import Dialog from 'primevue/dialog';
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import {generalFormat, transactionFormat} from '@/Composables/index.js';
import Empty from '@/Components/Empty.vue';
import Loader from "@/Components/Loader.vue";
import {IconAdjustments, IconCircleXFilled, IconSearch, IconX} from '@tabler/icons-vue';
import Calendar from 'primevue/calendar';
import OverlayPanel from 'primevue/overlaypanel';
import debounce from "lodash/debounce.js";
import Dropdown from "primevue/dropdown";
import Badge from '@/Components/Badge.vue';
import Tag from "primevue/tag";

// Define props
const props = defineProps({
    selectedGroup: String,
    uplines: Array,
});

const { formatDate, formatDateTime, formatAmount } = transactionFormat();
const { formatRgbaColor } = generalFormat();

const isLoading = ref(false);
const exportStatus = ref(false);
const visible = ref(false);
const rebateListing = ref();
const uplines = ref()
const selectedUplines = ref();
const dt = ref();
const expandedRows = ref({});
const selectedGroup = ref('dollar')
const totalRecords = ref(0);
const first = ref(0);
const rows = ref(10);
const page = ref(0);
const sortField = ref(null);
const sortOrder = ref(null);  // (1 for ascending, -1 for descending)

// Get current date
const today = new Date();

// Define minDate as the start of the current month and maxDate as today
const minDate = ref(new Date(today.getFullYear(), today.getMonth(), 1));
const maxDate = ref(today);

// Reactive variable for selected date range
const selectedDate = ref([minDate.value, maxDate.value]);

// Clear date selection
const clearDate = () => {
    selectedDate.value = null;
    filters.value['start_date'] = null;
    filters.value['end_date'] = null;
};

watch(selectedDate, (newDateRange) => {
    if (Array.isArray(newDateRange)) {
        const [startDate, endDate] = newDateRange;
        filters.value['start_date'] = startDate;
        filters.value['end_date'] = endDate;

        if (startDate !== null && endDate !== null) {
            loadLazyData();
        }
    }
    else {
        // console.warn('Invalid date range format:', newDateRange);
    }
})

watch(() => props.selectedGroup, (newGroup) => {
    // Whenever uplines change, update the local ref
    filters.value['group'] = newGroup;
  }
);

const filters = ref({
    global: null,
    start_date: null,
    end_date: null,
    group: null,
    upline_id: [],
});

// Watch for changes in props.uplines
watch(() => props.uplines, (newUplines) => {
    // Whenever uplines change, update the local ref
    uplines.value = newUplines;
  }, { immediate: true }
);

watch(selectedUplines, (newUplineId) => {
    if (newUplineId) {
        // Update only if it's not empty
        filters.value['upline_id'] = newUplineId.value;
    }
});

const clearFilterGlobal = () => {
    filters.value['global'] = null;
}

const clearFilter = () => {
    filters.value = {
        global: null,
        start_date: minDate.value,
        end_date: maxDate.value,
        group: props.selectedGroup,
        upline_id: [],
    };

    selectedDate.value = [minDate.value, maxDate.value];
    selectedUplines.value = null;
};

// Watch for changes on the entire 'filters' object and debounce the API call
watch(filters, debounce(() => {
    // Count active filters, excluding null, undefined, empty strings, and empty arrays
    filterCount.value = Object.entries(filters.value).filter(([key, filter]) => {
        // If both start_date and end_date have values, count them as 1 (treat as a pair)
        if ((key === 'start_date' || key === 'end_date') && filters.value.start_date && filters.value.end_date) {
            return key === 'start_date'; // Count once for the pair (count start_date only)
        }

        if (key === 'group') return false; // exclude 'group'

        // For other filters, count them if they are not null, undefined, or empty
        if (Array.isArray(filter)) {
            return filter.length > 0;  // Check if the array is not empty
        }

        return filter !== null && filter !== '';  // Check if the value is not null or empty string
    }).length;

    page.value = 0; // Reset to first page when filters change
    loadLazyData(); // Call loadLazyData function to fetch the data
}, 1000), { deep: true });

const lazyParams = ref({});

const loadLazyData = (event) => {
    isLoading.value = true;

    lazyParams.value = { ...lazyParams.value, first: event?.first || first.value };
    lazyParams.value.filters = filters.value;

    try {
        setTimeout(async () => {
            const params = {
                page: JSON.stringify(event?.page + 1),
                sortField: event?.sortField,
                sortOrder: event?.sortOrder,
                include: [],
                lazyEvent: JSON.stringify(lazyParams.value),
            };

            const url = route('report.getRebateListing', params);
            const response = await fetch(url);

            const results = await response.json();
            rebateListing.value = results?.data?.data;
            totalRecords.value = results?.data?.total;
            isLoading.value = false;

            emit('update-filters', filters.value);
        }, 100);
    } catch (e) {
        rebateListing.value = [];
        totalRecords.value = 0;
        isLoading.value = false;
    }
};

const onPage = (event) => {
    lazyParams.value = event;
    loadLazyData(event);
};

const onSort = (event) => {
    lazyParams.value = event;
    loadLazyData(event);
};

const onFilter = (event) => {
    lazyParams.value.fitlers = filters.value;
    loadLazyData(event);
};

// Optimized exportRebateSummary function
const exportRebateSummary = async () => {
    exportStatus.value = true;
    isLoading.value = true;

    lazyParams.value = { ...lazyParams.value, first: event?.first || first.value };
    lazyParams.value.filters = filters.value;

    const params = {
        page: JSON.stringify(event?.page + 1),
        sortField: event?.sortField,
        sortOrder: event?.sortOrder,
        include: [],
        lazyEvent: JSON.stringify(lazyParams.value),
        exportStatus: true,
    };
    const url = route('report.getRebateListing', params);

    try {
        window.location.href = url;
    } catch (e) {
        console.error('Error occured during export:', e);
    } finally {
        isLoading.value = false;
        exportStatus.value = false;
    }
};

// Define emits
const emit = defineEmits(['update-filters']);

const op = ref();
const filterCount = ref(0);
const toggle = (event) => {
    op.value.toggle(event);
}

onMounted(() => {
    // Ensure filters are populated before fetching data
    if (Array.isArray(selectedDate.value)) {
        const [startDate, endDate] = selectedDate.value;
        if (startDate && endDate) {
            filters.value.start_date = startDate;
            filters.value.end_date = endDate;
        }
    }

    if (selectedGroup.value) {
        filters.value['group'] = selectedGroup.value;
    }

    lazyParams.value = {
        first: dt.value.first,
        rows: dt.value.rows,
        sortField: null,
        sortOrder: null,
        filters: filters.value
    };
    // loadLazyData();
});

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
            :value="rebateListing"
            lazy
            :paginator="rebateListing?.length > 0"
            removableSort
            :totalRecords="totalRecords"
            :first="first"
            :rows="10"
            :rowsPerPageOptions="[10, 20, 50, 100]"
            tableStyle="md:min-width: 50rem"
            paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} entries"
            :globalFilterFields="['name', 'email']"
            ref="dt"
            dataKey="id"
            selectionMode="single"
            @row-click="(event) => openDialog(event.data)"
            :loading="isLoading"
            @page="onPage($event)"
            @sort="onSort($event)"
            @filter="onFilter($event)"
        >
            <template #header>
                <div class="flex flex-col md:flex-row gap-3 items-center self-stretch pb-3 md:pb-5">
                    <div class="relative w-full md:w-60">
                        <div class="absolute top-2/4 -mt-[9px] left-4 text-gray-400">
                            <IconSearch size="20" stroke-width="1.25" />
                        </div>
                        <InputText v-model="filters['global']" :placeholder="$t('public.keyword_search')" class="font-normal pl-12 w-full md:w-60" />
                        <div
                            v-if="filters['global'] !== null && filters['global'] !== ''"
                            class="absolute top-2/4 -mt-2 right-4 text-gray-300 hover:text-gray-400 select-none cursor-pointer"
                            @click="clearFilterGlobal"
                        >
                            <IconCircleXFilled size="16" />
                        </div>
                    </div>
                    <div class="w-full flex flex-col gap-3 md:flex-row">
                        <div class="w-full md:w-[272px]">
                            <Button
                                variant="gray-outlined"
                                @click="toggle"
                                size="sm"
                                class="flex gap-3 items-center justify-center py-3 w-full md:w-[130px]"
                            >
                                <IconAdjustments size="20" color="#0C111D" stroke-width="1.25" />
                                <div class="text-sm text-gray-950 font-medium">
                                    {{ $t('public.filter') }}
                                </div>
                                <Badge class="w-5 h-5 text-xs text-white" variant="numberbadge">
                                    {{ filterCount }}
                                </Badge>
                            </Button>
                        </div>
                        <div class="w-full flex justify-end">
                            <Button
                                variant="primary-outlined"
                                @click="exportRebateSummary()"
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
                    field="id_number"
                    sortable
                    :header="`${$t('public.id')}`"
                    class="hidden md:table-cell"
                >
                    <template #body="slotProps">
                        {{ slotProps.data.id_number }}
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
                    field="account_type"
                    :header="`${$t('public.account_type')}`"
                    class="hidden text-nowrap md:table-cell"
                >
                    <template #body="slotProps">
                        <div class="flex items-center gap-2">
                            <Tag
                                :severity="slotProps.data.trading_platform === 'mt4' ? 'secondary' : 'info'"
                                class="uppercase"
                                :value="slotProps.data.trading_platform"
                            />
                            <div
                                class="flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded"
                                :style="{
                                        backgroundColor: formatRgbaColor(slotProps.data.color, 0.15),
                                        color: `#${slotProps.data.color}`,
                                    }"
                            >
                                {{ $t(`public.${slotProps.data.slug}`) }}
                            </div>
                        </div>
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
                <span class="self-stretch text-gray-950 text-sm font-medium flex-grow">{{ `${formatDate(selectedDate ? selectedDate[0] : '2025/01/01')}&nbsp;-&nbsp;${formatDate(selectedDate ? selectedDate[1] : today)}` }}</span>
            </div>
            <div class="min-w-[100px] flex gap-1 flex-grow items-center self-stretch">
                <span class="self-stretch text-gray-500 text-xs font-medium w-[88px] md:w-[140px]">{{ $t('public.account') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium flex-grow">{{ data.meta_login }}</span>
            </div>
            <div class="min-w-[100px] flex gap-1 flex-grow items-center self-stretch">
                <span class="self-stretch text-gray-500 text-xs font-medium w-[88px] md:w-[140px]">{{ $t('public.account_type') }}</span>
                <div class="flex items-center gap-2">
                    <Tag
                        :severity="data.trading_platform === 'mt4' ? 'secondary' : 'info'"
                        class="uppercase"
                        :value="data.trading_platform"
                    />
                    <div
                        class="flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded"
                        :style="{
                                        backgroundColor: formatRgbaColor(data.color, 0.15),
                                        color: `#${data.color}`,
                                    }"
                    >
                        {{ $t(`public.${data.slug}`) }}
                    </div>
                </div>
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

    <OverlayPanel ref="op">
        <div class="flex flex-col gap-5 w-72 py-5 px-4">
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_date') }}
                </div>
                <div class="flex flex-col relative gap-1 self-stretch">
                    <Calendar
                        v-model="selectedDate"
                        selectionMode="range"
                        :manualInput="false"
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
            </div>

            <!-- Filter Upline-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_upline') }}
                </div>
                <Dropdown
                    v-model="selectedUplines"
                    :options="uplines"
                    :placeholder="$t('public.filter_upline')"
                    filter
                    :filterFields="['name', 'email', 'id_number']"
                    class="w-full md:w-64 font-normal"
                >
                    <template #option="{option}">
                        <div class="flex flex-col">
                            <span>{{ option.name }}</span>
                            <span class="text-xs text-gray-400 max-w-52 truncate">{{ option.email }}</span>
                        </div>
                    </template>
                    <template #value>
                        <div v-if="selectedUplines">
                            <span>{{ selectedUplines.name }}</span>
                        </div>
                        <span v-else class="text-gray-400">
                            {{ $t('public.filter_upline') }}
                        </span>
                    </template>
                </Dropdown>
            </div>

            <div class="flex w-full">
                <Button
                    type="button"
                    variant="primary-outlined"
                    class="flex justify-center w-full"
                    @click="clearFilter()"
                >
                    {{ $t('public.clear_all') }}
                </Button>
            </div>
        </div>
    </OverlayPanel>
</template>
