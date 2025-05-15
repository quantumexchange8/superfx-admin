<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { HandIcon, NetBalanceIcon, CoinsIcon, RocketIcon } from '@/Components/Icons/solid';
import {onMounted, ref, computed, watch, watchEffect} from "vue";
import {generalFormat, transactionFormat} from "@/Composables/index.js";
import { FilterMatchMode } from 'primevue/api';
import debounce from "lodash/debounce.js";
import {usePage} from "@inertiajs/vue3";
import dayjs from "dayjs";
import Button from '@/Components/Button.vue';
import Column from "primevue/column";
import Card from "primevue/card";
import DataTable from "primevue/datatable";
import Tag from "primevue/tag";
import {IconCircleXFilled, IconSearch, IconX, IconAdjustments} from "@tabler/icons-vue";
import InputText from "primevue/inputtext";
import Dropdown from "primevue/dropdown";
import MultiSelect from 'primevue/multiselect';
import Calendar from "primevue/calendar";
import Empty from "@/Components/Empty.vue";
import Loader from "@/Components/Loader.vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import ColumnGroup from "primevue/columngroup";
import Row from "primevue/row";
import OverlayPanel from 'primevue/overlaypanel';
import StatusBadge from '@/Components/StatusBadge.vue';
import Badge from '@/Components/Badge.vue';
import RadioButton from 'primevue/radiobutton';
import Vue3Autocounter from 'vue3-autocounter';

const props = defineProps({
  uplines: Array,
  symbols: Array,
});

const exportStatus = ref(false);
const isLoading = ref(false);
const dt = ref(null);
const openTrades = ref();
const selectedUplines = ref();
const selectedSymbols = ref();
const { formatDate, formatAmount } = transactionFormat();
const { formatRgbaColor } = generalFormat();
const totalRecords = ref(0);
const first = ref(0);
const rows = ref(10);
const page = ref(0);
const sortField = ref(null);
const sortOrder = ref(null);  // (1 for ascending, -1 for descending)

const totalLots = ref();
const totalCommission = ref();
const totalSwap = ref();
const totalProfit = ref();
const counterDuration = ref(10);

// data overview
const dataOverviews = computed(() => [
    {
        icon: HandIcon,
        total: totalLots.value,
        label: 'total_lots',
    },
    {
        icon: NetBalanceIcon,
        total: totalCommission.value,
        label: 'total_commission',
    },
    {
        icon: CoinsIcon,
        total: totalSwap.value,
        label: 'total_swap',
    },
    {
        icon: RocketIcon,
        total: totalProfit.value,
        label: 'total_profit',
    },
]);

const filters = ref({
    global: null,
    start_date: null,
    end_date: null,
    start_close_date: null,
    end_close_date: null,
    upline_id: [],
    symbol: null,
    trade_type: null,
    account_currency: null,
});

const uplines = ref()
const symbols = ref()

// Watch for changes in props
watch(() => [props.uplines, props.symbols], ([newUplines, newSymbols]) => {
    // Sync props to local refs (uplines & symbols)
    uplines.value = newUplines;
    symbols.value = newSymbols;
}, { immediate: true });

// Watch selected values to update filters
watch([selectedUplines, selectedSymbols], ([newUplineId, newSymbol]) => {
    if (newUplineId) {
        filters.value['upline_id'] = newUplineId.value;
    }

    if (newSymbol) {
        filters.value['symbol'] = newSymbol;
    }
});

// Watch for changes on the entire 'filters' object and debounce the API call
watch(filters, debounce(() => {
    // Count active filters, excluding null, undefined, empty strings, and empty arrays
    filterCount.value = Object.entries(filters.value).filter(([key, filter]) => {
        // If both start_date and end_date have values, count them as 1 (treat as a pair)
        if ((key === 'start_date' || key === 'end_date') && filters.value.start_date && filters.value.end_date) {
            return key === 'start_date'; // Count once for the pair (count start_date only)
        }

        // If both start_close_date and end_close_date have values, count them as 1 (treat as a pair)
        if ((key === 'start_close_date' || key === 'end_close_date') && filters.value.start_close_date && filters.value.end_close_date) {
            return key === 'start_close_date'; // Count once for the pair (count start_close_date only)
        }

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

            const url = route('trade_positions.closed_trade', params);
            const response = await fetch(url);

            const results = await response.json();
            openTrades.value = results?.data?.data;
            totalRecords.value = results?.data?.total;

            totalLots.value = results?.totalLots;
            totalCommission.value = results?.totalCommission;
            totalSwap.value = results?.totalSwap;
            totalProfit.value = results?.totalProfit;
            counterDuration.value = 1;

            isLoading.value = false;

        }, 100);
    } catch (error) {
        openTrades.value = [];
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

// Optimized exportClosedTrade function
const exportClosedTrade = async () => {
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
    const url = route('trade_positions.closed_trade', params);

    try {
        window.location.href = url;
    } catch (e) {
        console.error('Error occured during export:', e);
    } finally {
        isLoading.value = false;
        exportStatus.value = false;
    }
};

onMounted(() => {
    // Ensure filters are populated before fetching data
    if (Array.isArray(selectedDate.value)) {
        const [startDate, endDate] = selectedDate.value;
        if (startDate && endDate) {
            filters.value.start_date = startDate;
            filters.value.end_date = endDate;
        }
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

const op = ref();
const filterCount = ref(0);
const toggle = (event) => {
    op.value.toggle(event);
}

const clearFilterGlobal = () => {
    filters.value.global = null;
}

watchEffect(() => {
    if (usePage().props.toast !== null) {
        loadLazyData();
    }
});

// Get current date
const today = new Date();

// Define minDate as the start of the current month and maxDate as today
const minDate = ref(new Date(today.getFullYear(), today.getMonth(), 1));
const maxDate = ref(today);

// Reactive variable for selected date range
const selectedDate = ref([minDate.value, maxDate.value]);
const selectedCloseDate = ref(null);

const clearDate = () => {
    selectedDate.value = null;
    filters.value['start_date'] = null;
    filters.value['end_date'] = null;
}

const clearCloseDate = () => {
    selectedCloseDate.value = null;
    filters.value['start_close_date'] = null;
    filters.value['end_close_date'] = null;
}

// Watch for changes in selectedDate
watch(selectedDate, (newDateRange) => {
    if (Array.isArray(newDateRange)) {
        const [startDate, endDate] = newDateRange;
        filters.value['start_date'] = startDate;
        filters.value['end_date'] = endDate;

        // if (startDate !== null && endDate !== null) {
        //     loadLazyData();
        // }
    }
    else {
        // console.warn('Invalid date range format:', newDateRange);
    }
})

// Watch for changes in selectedCloseDate
watch(selectedCloseDate, (newDateRange) => {
    if (Array.isArray(newDateRange)) {
        const [startCloseDate, endCloseDate] = newDateRange;
        filters.value['start_close_date'] = startCloseDate;
        filters.value['end_close_date'] = endCloseDate;

        // // Check if both start and end close dates are valid
        // if (startCloseDate !== null && endCloseDate !== null) {
        //     loadLazyData();
        // }
    }
    else {
        // console.warn('Invalid date range format:', newDateRange);
    }
});

const clearFilter = () => {
    filters.value = {
        global: '',
        start_date: null,
        end_date: null,
        start_close_date: null,
        end_close_date: null,
        upline_id: [],
        symbol: null,
        trade_type: null,
        account_currency: null,
    };

    selectedDate.value = [minDate.value, maxDate.value];
    selectedCloseDate.value = null;
    selectedUplines.value = null;
    selectedSymbols.value = null;
};

</script>

<template>
    <AuthenticatedLayout :title="$t('public.closed_positions')">
        <div class="flex flex-col gap-5 md:gap-8">
            <div class="grid grid-cols-1 md:grid-cols-2 w-full gap-5">
                <div
                    v-for="(item, index) in dataOverviews"
                    :key="index"
                    class="flex justify-center items-center py-4 px-6 gap-5 self-stretch rounded-2xl bg-white shadow-toast md:flex-col md:flex-grow md:py-6 md:gap-3"
                >
                    <component :is="item.icon" class="w-12 h-12 grow-0 shrink-0" />
                    <div class="flex flex-col items-center gap-1 flex-grow md:flex-grow-0 md:self-stretch">
                        <div class="self-stretch text-gray-950 text-lg font-semibold md:text-xl md:text-center">
                            <vue3-autocounter
                                ref="counter"
                                :startAmount="0"
                                :endAmount="item.total"
                                :duration="counterDuration"
                                separator=","
                                decimalSeparator="."
                                :decimals="item.label === 'total_transaction' ? 0 : (item.label === 'total_payout_amount' ? 3 : 2)"
                                :autoinit="true"
                            />
                        </div>
                        <span class="self-stretch text-gray-500 text-xs md:text-sm md:text-center">{{ $t('public.' + item.label) }}</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col items-center px-4 py-6 gap-5 self-stretch rounded-2xl border border-gray-200 bg-white shadow-table md:px-6 md:gap-5">
                <div
                    class="w-full"
                >
                    <DataTable
                        :value="openTrades"
                        :rowsPerPageOptions="[10, 20, 50, 100]"
                        lazy
                        :paginator="openTrades?.length > 0"
                        removableSort
                        paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
                        :currentPageReportTemplate="$t('public.paginator_caption')"
                        :first="first"
                        :page="page"
                        :rows="10"
                        ref="dt"
                        dataKey="id"
                        :totalRecords="totalRecords"
                        :loading="isLoading"
                        @page="onPage($event)"
                        @sort="onSort($event)"
                        @filter="onFilter($event)"
                        :globalFilterFields="['name', 'email', 'username', 'meta_login', 'id_number', 'trade_deal_id']"
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
                                        <!-- <Calendar
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
                                        </div> -->
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
                                        @click="exportClosedTrade"
                                        class="w-full md:w-auto"
                                    >
                                        {{ $t('public.export') }}
                                    </Button>
                                </div>
                                </div>
                                <div class="flex justify-end self-stretch md:hidden">
                                    <span class="text-gray-500 text-right text-sm font-medium">{{ $t('public.total') }}:</span>
                                    <span class="text-gray-950 text-sm font-semibold ml-2">$ {{ formatAmount(totalProfit) }}</span>
                                </div>
                            </div>
                        </template>
                        <template #empty>
                            <Empty
                                :title="$t('public.empty_closed_trades_title')"
                                :message="$t('public.empty_closed_trades_message')"
                            />
                        </template>
                        <template #loading>
                            <div class="flex flex-col gap-2 items-center justify-center">
                                <Loader />
                                <span class="text-sm text-gray-700">{{ $t('public.loading_closed_trades_caption') }}</span>
                            </div>
                        </template>
                        <template v-if="openTrades?.length > 0">
                            <Column
                                field="created_at"
                                sortable
                                :header="`${$t('public.date')}`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell"
                            >
                                <template #body="slotProps">
                                    {{ dayjs(slotProps.data.created_at).format('YYYY/MM/DD') }}
                                </template>
                            </Column>
                            <Column
                                field="name"
                                :header="$t('public.name')"
                                headerClass="text-nowrap"
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
                                :header="`${$t('public.id')}`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell"
                            >
                                <template #body="slotProps">
                                    {{ slotProps.data.id_number }}
                                </template>
                            </Column>
                            <Column
                                field="trade_deal_id"
                                sortable
                                :header="`${$t('public.ticket')}`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell"
                            >
                                <template #body="slotProps">
                                    {{ slotProps.data.trade_deal_id }}
                                </template>
                            </Column>
                            <Column
                                field="meta_login"
                                :header="`${$t('public.account')}`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell"
                            >
                                <template #body="slotProps">
                                    <span >{{ slotProps.data.meta_login }}</span>
                                </template>
                            </Column>
                            <Column
                                field="group"
                                :header="`${$t('public.group')}`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell"
                            >
                                <template #body="slotProps">
                                    <div class="flex justify-center items-center">
                                        <div
                                            class="flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded"
                                            :style="{
                                                backgroundColor: formatRgbaColor(slotProps.data.account_type_color, 0.15),
                                                color: `#${slotProps.data.account_type_color}`,
                                            }"
                                        >
                                            {{ $t(`public.${slotProps.data.account_type_slug}`) }}
                                        </div>
                                    </div>
                                </template>
                            </Column>
                            <Column
                                field="trade_open_time"
                                sortable
                                :header="`${$t('public.open_time')}`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell min-w-32"
                            >
                                <template #body="slotProps">
                                    {{ slotProps.data.trade_open_time }}
                                </template>
                            </Column>
                            <Column
                                field="trade_type"
                                :header="`${$t('public.type')}`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell"
                            >
                                <template #body="slotProps">
                                    {{ $t(`public.${slotProps.data.trade_type}`) }}
                                </template>
                            </Column>
                            <Column
                                field="trade_lots"
                                sortable
                                :header="`${$t('public.lots')}&nbsp;(Ł)`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell min-w-32"
                            >
                                <template #body="slotProps">
                                    {{ formatAmount(slotProps.data.trade_lots) }}
                                </template>
                            </Column>
                            <Column
                                field="trade_symbol"
                                :header="`${$t('public.symbol')}`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell min-w-32"
                            >
                                <template #body="slotProps">
                                    {{ slotProps.data.trade_symbol }}
                                </template>
                            </Column>
                            <Column
                                field="upline_name"
                                :header="$t('public.upline')"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell"
                            >
                                <template #body="slotProps">
                                    <div class="flex items-center gap-3">
                                        <div class="flex flex-col items-start">
                                            <div class="font-medium">
                                                {{ slotProps.data.upline_name }}
                                            </div>
                                            <div class="text-gray-500 text-xs">
                                                {{ slotProps.data.upline_email }}
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </Column>
                            <Column
                                field="upline_id_number"
                                :header="$t('public.upline_id')"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell"
                            >
                                <template #body="slotProps">
                                    <div class="flex items-center gap-3">
                                        <div class="flex flex-col items-start">
                                            {{ slotProps.data.upline_id_number }}
                                        </div>
                                    </div>
                                </template>
                            </Column>
                            <Column
                                field="trade_open_price"
                                sortable
                                :header="`${$t('public.open_price')}&nbsp;($)`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell"
                            >
                                <template #body="slotProps">
                                    {{ formatAmount(slotProps.data.trade_open_price ?? 0) }}
                                </template>
                            </Column>
                            <Column
                                field="trade_sl"
                                sortable
                                :header="`${$t('public.stop_loss')}&nbsp;($)`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell"
                            >
                                <template #body="slotProps">
                                    {{ formatAmount(slotProps.data.trade_sl ?? 0) }}
                                </template>
                            </Column>
                            <Column
                                field="trade_tp"
                                sortable
                                :header="`${$t('public.take_profit')}&nbsp;($)`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell"
                            >
                                <template #body="slotProps">
                                    {{ formatAmount(slotProps.data.trade_tp ?? 0) }}
                                </template>
                            </Column>
                            <Column
                                field="trade_close_time"
                                sortable
                                :header="`${$t('public.closed_time')}`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell min-w-32"
                            >
                                <template #body="slotProps">
                                    {{ slotProps.data.trade_close_time }}
                                </template>
                            </Column>

                            <Column
                                field="trade_close_price"
                                sortable
                                :header="`${$t('public.close_price')}&nbsp;($)`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell"
                            >
                                <template #body="slotProps">
                                    {{ formatAmount(slotProps.data.trade_close_price ?? 0) }}
                                </template>
                            </Column>
                            <Column
                                field="account_type_currency"
                                :header="`${$t('public.account_currency')}`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell"
                            >
                                <template #body="slotProps">
                                    {{ slotProps.data.account_type_currency ?? '-' }}
                                </template>
                            </Column>
                            <Column
                                field="trade_commission"
                                sortable
                                :header="`${$t('public.commission')}&nbsp;($)`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell"
                            >
                                <template #body="slotProps">
                                    {{ formatAmount(slotProps.data.trade_commission ?? 0) }}
                                </template>
                            </Column>
                            <Column
                                field="trade_swap"
                                sortable
                                :header="`${$t('public.swap')}&nbsp;($)`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell"
                            >
                                <template #body="slotProps">
                                    {{ formatAmount(slotProps.data[`trade_swap_${(slotProps.data.account_type_currency || '').toLowerCase()}`] ?? 0) }}
                                </template>
                            </Column>
                            <Column
                                field="trade_profit"
                                sortable
                                :header="`${$t('public.profit')}&nbsp;($)`"
                                headerClass="text-nowrap"
                                class="hidden md:table-cell"
                            >
                                <template #body="slotProps">
                                    {{ formatAmount(slotProps.data[`trade_profit_${(slotProps.data.account_type_currency || '').toLowerCase()}`] ?? 0) }}
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
                                                    {{ `${slotProps.data.meta_login}&nbsp;|&nbsp;${formatAmount(slotProps.data.trade_lots)}&nbsp;Ł` }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="overflow-hidden text-right text-ellipsis font-semibold">
                                            $&nbsp;{{ formatAmount(slotProps.data[`trade_profit_${(slotProps.data.account_type_currency || '').toLowerCase()}`] ?? 0) }}
                                        </div>
                                    </div>
                                </template>
                            </Column>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>
        <OverlayPanel ref="op">
            <div class="flex flex-col gap-8 w-72 py-5 px-4">
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

                <div class="flex flex-col gap-2 items-center self-stretch">
                    <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                        {{ $t('public.filter_closed_time') }}
                    </div>
                    <div class="flex flex-col relative gap-1 self-stretch">
                        <Calendar
                            v-model="selectedCloseDate"
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
                            v-if="selectedCloseDate && selectedCloseDate.length > 0"
                            class="absolute top-2/4 -mt-2.5 right-4 text-gray-400 select-none cursor-pointer bg-white"
                            @click="clearCloseDate"
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

                <!-- Filter Symbol-->
                <div class="flex flex-col gap-2 items-center self-stretch">
                    <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                        {{ $t('public.filter_symbol') }}
                    </div>
                    <Dropdown
                        v-model="selectedSymbols"
                        :options="symbols"
                        :placeholder="$t('public.filter_symbol')"
                        filter
                        class="w-full md:w-64 font-normal"
                    >
                        <template #option="{option}">
                            <div class="flex flex-col">
                                <span>{{ option }}</span>
                            </div>
                        </template>
                        <template #value>
                            <div v-if="selectedSymbols">
                                <span>{{ selectedSymbols }}</span>
                            </div>
                            <span v-else class="text-gray-400">
                                {{ $t('public.filter_symbol') }}
                            </span>
                        </template>
                    </Dropdown>
                </div>

                <!-- Filter Type -->
                <div class="flex flex-col items-center gap-2 self-stretch">
                    <span class="self-stretch text-gray-950 text-xs font-semibold">{{ $t('public.filter_type') }}</span>
                    <div class="flex flex-col gap-1 self-stretch">
                        <div class="flex items-center gap-2 text-sm text-gray-950">
                            <RadioButton v-model="filters['trade_type']" inputId="trade_buy" value="buy" class="w-4 h-4" />
                            <label for="buy">{{ $t('public.buy') }}</label>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-950">
                            <RadioButton v-model="filters['trade_type']" inputId="trade_sell" value="sell" class="w-4 h-4" />
                            <label for="sell">{{ $t('public.sell') }}</label>
                        </div>
                    </div>
                </div>

                <!-- Filter Account Currency -->
                <div class="flex flex-col items-center gap-2 self-stretch">
                    <span class="self-stretch text-gray-950 text-xs font-semibold">{{ $t('public.filter_account_currency') }}</span>
                    <div class="flex flex-col gap-1 self-stretch">
                        <div class="flex items-center gap-2 text-sm text-gray-950">
                            <RadioButton v-model="filters['account_currency']" inputId="usd" value="USD" class="w-4 h-4" />
                            <label for="usd">{{ $t('USD') }}</label>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-950">
                            <RadioButton v-model="filters['account_currency']" inputId="usc" value="USC" class="w-4 h-4" />
                            <label for="usc">{{ $t('USC') }}</label>
                        </div>
                    </div>
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
    </AuthenticatedLayout>
</template>
