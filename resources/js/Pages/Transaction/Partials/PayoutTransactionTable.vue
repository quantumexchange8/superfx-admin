<script setup>
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import {ref, watch, onMounted, computed} from "vue";
import {FilterMatchMode} from "primevue/api";
import Loader from "@/Components/Loader.vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import Empty from "@/Components/Empty.vue";
import dayjs from "dayjs";
import InputText from "primevue/inputtext";
import Button from "@/Components/Button.vue";
import {IconCircleXFilled, IconSearch, IconX} from "@tabler/icons-vue";
import {transactionFormat, generalFormat} from "@/Composables/index.js";
import Dialog from "primevue/dialog";
import Tag from "primevue/tag";
import debounce from "lodash/debounce.js";
import Calendar from "primevue/calendar";
import Accordion from 'primevue/accordion';
import AccordionTab from 'primevue/accordiontab';

const props = defineProps({
    selectedMonths: Array,
    selectedType: String,
    tradingPlatforms: Array,
});

const isLoading = ref(false);
const dt = ref(null);
const transactions = ref([]);
const { formatAmount } = transactionFormat();
const { formatRgbaColor } = generalFormat()
const totalRecords = ref(0);
const first = ref(0);
const emit = defineEmits(['update-totals'])

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    month_range: { value: props.selectedMonths, matchMode: FilterMatchMode.EQUALS },
    start_date: { value: null, matchMode: FilterMatchMode.EQUALS },
    end_date: { value: null, matchMode: FilterMatchMode.EQUALS },
});

const abortController = ref(null)
const lazyParams = ref({});

const loadLazyData = (event) => {
    isLoading.value = true;

    // Abort previous request if exists
    if (abortController.value) {
        abortController.value.abort();
    }

    // Create new controller for this request
    abortController.value = new AbortController();

    lazyParams.value = { ...lazyParams.value, first: event?.first || first.value };
    lazyParams.value.filters = filters.value;

    setTimeout(async () => {
        try {
            const params = {
                page: JSON.stringify(event?.page + 1),
                sortField: event?.sortField,
                sortOrder: event?.sortOrder,
                include: [],
                lazyEvent: JSON.stringify(lazyParams.value)
            };

            const url = route('transaction.getPayoutData', params);

            const response = await fetch(url, {
                signal: abortController.value.signal
            });

            const results = await response.json();
            transactions.value = results?.data?.data;
            totalRecords.value = results?.data?.total;

            emit('update-totals', {
                totalTransaction: totalRecords.value,
                totalTransactionAmount: results?.totalPayout,
                maxAmount: results?.maxAmount,
            });

        } catch (e) {
            if (e.name === 'AbortError') {
                console.log('Skip');
            } else {
                console.error('Fetch error', e);
                transactions.value = [];
                totalRecords.value = 0;
            }
        } finally {
            isLoading.value = false;
        }
    }, 100);
};

onMounted(() => {
    lazyParams.value = {
        first: dt.value.first,
        rows: dt.value.rows,
        sortField: null,
        sortOrder: null,
        filters: filters.value
    };

    loadLazyData();
});

watch(() => props.selectedMonths, () => {
    filters.value['month_range'].value = props.selectedMonths;
    loadLazyData()
})

watch(
    filters.value['global'],
    debounce(() => {
        loadLazyData();
    }, 300)
);

const onPage = (event) => {
    lazyParams.value = event;
    loadLazyData(event);
};
const onSort = (event) => {
    lazyParams.value = event;
    loadLazyData(event);
};
const onFilter = (event) => {
    lazyParams.value.filters = filters.value ;
    loadLazyData(event);
};

const clearFilterGlobal = () => {
    filters.value['global'].value = null;
}

const data = ref([]);
const visible = ref(false);

const openDialog = (rowData) => {
    visible.value = true;
    data.value = rowData;
}

// compute minDate and maxDate based on selectedMonths
const minDate = computed(() => {
    if (!props.selectedMonths?.length) return null;

    const first = props.selectedMonths[0];
    const [year, month] = first.split('/');
    return new Date(parseInt(year), parseInt(month) - 1, 1);
});

const maxDate = computed(() => {
    if (!props.selectedMonths?.length) return null;

    const last = props.selectedMonths[props.selectedMonths.length - 1];
    const [year, month] = last.split('/');
    const lastDay = new Date(parseInt(year), parseInt(month), 0).getDate();
    return new Date(parseInt(year), parseInt(month) - 1, lastDay);
});

const today = new Date();
const selectedDate = ref([today, today]);

const clearDate = () => {
    selectedDate.value = [];
}

watch(selectedDate, (newDateRange) => {
    if (Array.isArray(newDateRange)) {
        const [startDate, endDate] = newDateRange;
        filters.value['start_date'].value = startDate;
        filters.value['end_date'].value = endDate;

        if (startDate !== null && endDate !== null) {
            loadLazyData();
        }
    } else {
        console.warn('Invalid date range format:', newDateRange);
    }
}, {immediate: true});

const exportStatus = ref(false);

const exportReport = () => {
    exportStatus.value = true;
    isLoading.value = true;

    lazyParams.value = { ...lazyParams.value, first: event?.first || first.value };

    const params = {
        page: JSON.stringify(event?.page + 1),
        sortField: event?.sortField,
        sortOrder: event?.sortOrder,
        include: [],
        lazyEvent: JSON.stringify(lazyParams.value),
        exportStatus: true,
    };

    const url = route('transaction.getPayoutData', params);

    try {
        window.location.href = url;
    } catch (e) {
        console.error('Error occurred during export:', e);
    } finally {
        isLoading.value = false;
        exportStatus.value = false;
    }
};
</script>

<template>
    <DataTable
        :value="transactions"
        :rowsPerPageOptions="[10, 20, 50, 100]"
        lazy
        :paginator="transactions?.length > 0"
        removableSort
        paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
        :currentPageReportTemplate="$t('public.paginator_caption')"
        :first="first"
        :rows="10"
        v-model:filters="filters"
        ref="dt"
        dataKey="id"
        :totalRecords="totalRecords"
        :loading="isLoading"
        @page="onPage($event)"
        @sort="onSort($event)"
        @filter="onFilter($event)"
        selectionMode="single"
        @row-click="(event) => openDialog(event.data)"
        :globalFilterFields="['name', 'email', 'id_number', 'to_meta_login', 'transaction_number']"
    >
        <template #header>
            <div class="flex flex-col md:flex-row gap-3 items-center self-stretch md:pb-6">
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
                <div class="flex items-center w-full gap-3">
                    <div class="relative w-full md:max-w-60">
                        <Calendar
                            v-model="selectedDate"
                            selectionMode="range"
                            :manualInput="false"
                            :minDate="minDate"
                            :maxDate="maxDate"
                            dateFormat="yy/mm/dd"
                            iconDisplay="input"
                            placeholder="yyyy/mm/dd - yyyy/mm/dd"
                            class="w-full font-normal"
                        />
                        <div
                            v-if="selectedDate && selectedDate.length > 0"
                            class="absolute top-2/4 -mt-2.5 right-4 text-gray-400 select-none cursor-pointer bg-white"
                            @click="clearDate"
                        >
                            <IconX size="20" stroke-width="1.5" />
                        </div>
                    </div>

                    <div class="w-full flex justify-end">
                        <Button
                            variant="primary-outlined"
                            @click="exportReport"
                            class="w-full md:w-auto"
                        >
                            {{ $t('public.export') }}
                        </Button>
                    </div>
                </div>
            </div>
        </template>
        <template #empty>
            <Empty :title="$t('public.empty_account_title')" :message="$t('public.empty_account_message')"/>
        </template>
        <template #loading>
            <div class="flex flex-col gap-2 items-center justify-center">
                <Loader />
                <span class="text-sm text-gray-700">{{ $t('public.loading_accounts_data') }}</span>
            </div>
        </template>
        <template v-if="transactions?.length > 0">
            <Column
                field="execute_at"
                sortable
                class="hidden md:table-cell w-[15%]"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.date') }}</span>
                </template>
                <template #body="{data}">
                    {{ dayjs(data.execute_at).format('YYYY/MM/DD') }}
                </template>
            </Column>

            <Column
                field="name"
                :header="$t('public.name')"
                class="hidden md:table-cell"
            >
                <template #body="{data}">
                    <div class="flex items-center gap-3 max-w-full">
                        <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                            <template v-if="data?.profile_photo">
                                <img :src="data?.profile_photo" alt="profile_photo" class="w-7 h-7 object-cover">
                            </template>
                            <template v-else>
                                <DefaultProfilePhoto />
                            </template>
                        </div>
                        <div class="grid grid-cols-1 items-start max-w-full">
                            <div class="font-medium max-w-full truncate">
                                {{ data?.name }}
                            </div>
                            <div class="text-gray-500 text-xs max-w-full truncate">
                                {{ data?.email }}
                            </div>
                        </div>
                    </div>
                </template>
            </Column>

            <Column
                field="volume"
                sortable
                class="hidden md:table-cell"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.volume') }} (Ł)</span>
                </template>
                <template #body="{data}">
                    <span class="break-all">{{ formatAmount(data?.volume ?? 0) }}</span>
                </template>
            </Column>

            <Column
                field="rebate"
                sortable
                class="hidden md:table-cell"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.payout') }} ($)</span>
                </template>
                <template #body="{data}">
                    <span class="break-all">{{ formatAmount(data?.rebate ?? 0, 3) }}</span>
                </template>
            </Column>

            <Column class="md:hidden">
                <template #body="{data}">
                    <div class="flex items-center gap-2 self-stretch">
                        <div class="flex flex-col items-start w-full">
                            <span class="text-sm text-gray-950 font-semibold">{{ data?.name}}</span>
                            <div class="flex gap-1 items-center text-xs">
                                <div class="text-gray-400 font-medium">{{ dayjs(data.execute_at).format('YYYY/MM/DD') }}</div>
                                <span>|</span>
                                <div class="text-gray-500 font-medium">{{ formatAmount(data.volume) }} Ł</div>
                            </div>
                        </div>

                        <div class="font-semibold">
                            ${{ formatAmount(data.rebate ?? 0, 3) }}
                        </div>
                    </div>
                </template>
            </Column>
        </template>
    </DataTable>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t(`public.${selectedType}_details`)"
        class="dialog-xs md:dialog-md"
    >
        <div class="flex flex-col justify-center items-start pb-4 gap-3 self-stretch border-b border-gray-200 md:flex-row md:pt-4 md:justify-between">
            <!-- below md -->
            <span class="md:hidden self-stretch text-gray-950 text-xl font-semibold">${{ formatAmount(data.rebate ?? 0, 3) }}</span>
            <div class="flex items-center gap-3 self-stretch">
                <div class="w-9 h-9 rounded-full overflow-hidden grow-0 shrink-0">
                    <template v-if="data?.profile_photo">
                        <img :src="data?.profile_photo" alt="profile_photo" class="w-7 h-7 object-cover">
                    </template>
                    <template v-else>
                        <DefaultProfilePhoto />
                    </template>
                </div>
                <div class="flex flex-col items-start flex-grow">
                    <span class="self-stretch overflow-hidden text-gray-950 text-ellipsis text-sm font-medium">{{ data.name }}</span>
                    <span class="self-stretch overflow-hidden text-gray-500 text-ellipsis text-xs">{{ data?.email }}</span>
                </div>
            </div>
            <!-- above md -->
            <span class="hidden md:block w-[180px] text-gray-950 text-right text-xl font-semibold">${{ formatAmount(data.rebate ?? 0, 3) }}</span>
        </div>

        <div class="flex flex-col items-center py-4 gap-3 self-stretch border-b border-gray-200">
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.payout_date') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ dayjs(data.execute_at).format('YYYY/MM/DD') }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.total_trade_volume') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ formatAmount(data.volume ?? 0) }} Ł</span>
            </div>
        </div>

        <!-- Account type details-->
        <div class="flex flex-col items-center pb-4 gap-3 self-stretch">
            <Accordion :multiple="true" :activeIndex="[0]" class="w-full">
                <AccordionTab v-for="tab in data.details" :key="tab.account_type_id">
                    <template #header>
                        <div class="flex gap-2 items-center">
                            <Tag
                                :severity="tab?.trading_platform === 'mt4' ? 'secondary' : 'info'"
                                class="uppercase text-xs"
                                :value="tab?.trading_platform"
                            />
                            <div
                                class="break-all flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded w-fit text-nowrap"
                                :style="{
                                    backgroundColor: formatRgbaColor(tab?.account_type_color, 0.15),
                                    color: `#${tab?.account_type_color}`,
                                }"
                            >
                                {{ tab?.account_type_name }}
                            </div>
                        </div>
                    </template>
                    <div class="flex flex-col items-center gap-1 self-stretch md:gap-0">
                        <div class="flex flex-col items-center pb-4 gap-3 self-stretch">
                            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.trade_volume') }}</span>
                                <span class="self-stretch text-gray-950 text-sm font-medium">{{ formatAmount(tab.total_volume ?? 0) }} Ł</span>
                            </div>
                            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.total_rebate') }}</span>
                                <span class="self-stretch text-gray-950 text-sm font-medium">${{ formatAmount(data.rebate ?? 0) }}</span>
                            </div>
                        </div>
                        <!-- below md -->
                        <span class="md:hidden self-stretch text-gray-950 text-xs font-bold">{{ $t('public.products_traded') }}</span>
                        <div class="md:hidden flex flex-col items-center self-stretch" v-for="(symbol, index) in tab.symbol_groups" :key="symbol.id" :class="{'border-b border-gray-200': index !== tab.symbol_groups.length - 1}">
                            <div class="flex justify-between items-center py-1 self-stretch">
                                <div class="flex flex-col items-start flex-grow">
                                    <span class="self-stretch overflow-hidden text-gray-950 text-ellipsis text-xs font-semibold" style="text-transform: capitalize;" >{{ $t('public.' + symbol.name) }}</span>
                                    <div class="flex items-center gap-2 self-stretch">
                                        <span class="text-gray-700 font-medium text-xs">{{ formatAmount(symbol.volume ?? 0) }} Ł</span>
                                        <span>|</span>
                                        <span class="text-gray-500 text-xs">{{ formatAmount(symbol.net_rebate) }}</span>
                                    </div>
                                </div>
                                <span class="w-[100px] overflow-hidden text-gray-950 text-right text-ellipsis font-semibold">$&nbsp;{{ formatAmount(symbol.rebate ?? 0, 3) }}</span>
                            </div>
                        </div>
                        <!-- above md -->
                        <div class="w-full hidden md:grid grid-cols-4 gap-2 py-2 items-center border-b border-gray-200 bg-gray-100 uppercase text-gray-950 text-xs font-semibold">
                            <div class="flex items-center px-2">
                                {{ $t('public.product') }}
                            </div>
                            <div class="flex items-center px-2">
                                {{ $t('public.volume') }} (Ł)
                            </div>
                            <div class="flex items-center px-2">
                                {{ $t('public.rebate') }} / Ł ($)
                            </div>
                            <div class="flex items-center px-2">
                                {{ $t('public.total') }} ($)
                            </div>
                        </div>

                        <div v-for="(symbol, index) in tab.symbol_groups" :key="symbol.id" class="w-full hidden md:grid grid-cols-4 gap-2 py-3 items-center hover:bg-gray-50" :class="{'border-b border-gray-200': index !== tab.symbol_groups.length - 1}">
                            <div class="flex items-center px-2">
                                <span class="text-gray-950 text-sm" style="text-transform: capitalize;" >{{ $t('public.' + symbol.name) }}</span>
                            </div>
                            <div class="flex items-center px-2">
                                <span class="text-gray-950 text-sm">{{ formatAmount(symbol.volume) }}</span>
                            </div>
                            <div class="flex items-center px-2">
                                <span class="text-gray-950 text-sm">{{ formatAmount(symbol.net_rebate) }}</span>
                            </div>
                            <div class="flex items-center px-2">
                                <span class="text-gray-950 text-sm">{{ formatAmount(symbol.rebate, 3) }}</span>
                            </div>
                        </div>
                    </div>
                </AccordionTab>
            </Accordion>
        </div>
    </Dialog>
</template>
