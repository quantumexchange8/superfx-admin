<script setup>
import {onMounted, ref, watch, watchEffect} from "vue";
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

const props = defineProps({
  uplines: Array,
});

const exportStatus = ref(false);
const isLoading = ref(false);
const dt = ref(null);
const histories = ref();
const { formatDate, formatAmount } = transactionFormat();
const { formatRgbaColor } = generalFormat();
const totalRecords = ref(0);
const first = ref(0);
const rows = ref(10);
const page = ref(0);
const sortField = ref(null);  
const sortOrder = ref(null);  // (1 for ascending, -1 for descending)

const totalRebateAmount = ref();

const filters = ref({
    global: null,
    start_date: null,
    end_date: null,
    start_close_date: null,
    end_close_date: null,
    upline_id: null,
    t_type: null,
});
const upline_id = ref(null)
const uplines = ref()

// Watch for changes in props.uplines
watch(() => props.uplines, (newUplines) => {
    // Whenever uplines change, update the local ref
    uplines.value = newUplines;
  }, { immediate: true }
);

// Watch for individual changes in upline_id and apply it to filters
watch([upline_id], ([newUplineId]) => {
    if (newUplineId !== null) {
        filters.value['upline_id'] = newUplineId.value;
    }
});

// Watch for changes on the entire 'filters' object and debounce the API call
watch(filters, debounce(() => {
    // Count active filters, excluding null, undefined, empty strings, and empty arrays
    filterCount.value = Object.values(filters.value).filter(filter => {
        if (Array.isArray(filter)) {
            return filter.length > 0;  // Check if the array is not empty
        }
        return filter !== null && filter !== '';  // Check if the value is not null or an empty string
    }).length;

    page.value = 0; // Reset to first page when filters change
    getResults(); // Call getResults function to fetch the data
}, 1000), { deep: true });

// Function to construct the URL with necessary query parameters
const constructUrl = (exportStatus = false) => {
    let url = `/report/getRebateHistory?rows=${rows.value}&page=${page.value}`;

    // Add filters if present
    if (filters.value.global) {
        url += `&search=${filters.value.global}`;
    }

    // Dynamically use selectedDate and selectedCloseDate for date filters
    if (selectedDate.value && selectedDate.value.length === 2) {
        url += `&startDate=${formatDate(selectedDate.value[0])}`;
        url += `&endDate=${formatDate(selectedDate.value[1])}`;
    }

    if (selectedCloseDate.value && selectedCloseDate.value.length === 2) {
        url += `&startClosedDate=${formatDate(selectedCloseDate.value[0])}`;
        url += `&endClosedDate=${formatDate(selectedCloseDate.value[1])}`;
    }

    if (filters.value.upline_id) {
        url += `&upline_id=${filters.value.upline_id}`;
    }

    if (filters.value.t_type) {
        url += `&type=${filters.value.t_type}`;
    }

    if (sortField.value && sortOrder.value !== null) {
        url += `&sortField=${sortField.value}&sortOrder=${sortOrder.value}`;
    }

    // Add exportStatus if export is required
    if (exportStatus) {
        url += `&exportStatus=true`;
    }

    return url;
};

// Optimized getResults function
const getResults = async () => {
    isLoading.value = true;
    try {
        // Construct the URL dynamically
        const url = constructUrl();

        // Make the API request
        const response = await axios.get(url);
        // Update the data and total records with the response
        histories.value = response?.data?.data?.data;
        totalRecords.value = response?.data?.data?.total;
        totalRebateAmount.value = response?.data?.totalRebateAmount;

    } catch (error) {
        histories.value = [];
        totalRecords.value = 0;
        isLoading.value = false;
    } finally {
        isLoading.value = false;
    }
};

// Optimized exportRebateReport function
const exportRebateReport = async () => {
    exportStatus.value = true;
    try {
        // Construct the URL dynamically with exportStatus for export
        const url = constructUrl(exportStatus.value);

        // Send the request to trigger the export
        window.location.href = url;  // This will trigger the download directly
    } catch (e) {
        console.error('Error occurred during export:', e);
    } finally {
        isLoading.value = false;
        exportStatus.value = false;  // Reset export status
    }
};

const onPage = async (event) => {
    rows.value = event.rows;
    page.value = event.page;

    getResults();
};

const onSort = (event) => {
    sortField.value = event.sortField;
    sortOrder.value = event.sortOrder;  // Store ascending or descending order

    getResults();
};

onMounted(() => {
    getResults();
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
        getResults();
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
}

const clearCloseDate = () => {
    selectedCloseDate.value = null;
}

watch(selectedDate, (newDateRange) => {
    if (Array.isArray(newDateRange)) {
        const [startDate, endDate] = newDateRange;
        // Check if both start and end dates are valid
        if (startDate && endDate) {
            getResults();
        } 
        // Handle case where one of the dates is missing
        else if (startDate || endDate) {
            // If either startDate or endDate is available, use them for the date range
            getResults();
        } 
        // If no dates are selected, pass an empty array
        else {
            getResults();
        }
    }
    else if (newDateRange === null) {
        getResults();
    }
    else {
        console.warn('Invalid date range format:', newDateRange);
    }
})

watch(selectedCloseDate, (newDateRange) => {
    if (Array.isArray(newDateRange)) {
        const [startCloseDate, endCloseDate] = newDateRange;
        // Check if both start and end dates are valid
        if (startCloseDate && endCloseDate) {
            getResults();
        } 
        // Handle case where one of the dates is missing
        else if (startCloseDate || endCloseDate) {
            getResults();
        } 
        // If no dates are selected, pass an empty array
        else {
            getResults();
        }
    } 
    else if (newDateRange === null) {
        getResults();
    }
    else {
        console.warn('Invalid date range format:', newDateRange);
    }
})

const clearFilter = () => {
    filters.value = {
        global: '',
        start_date: null,
        end_date: null,
        start_close_date: null,
        end_close_date: null,
        upline_id: null,
        t_type: null,
    };
    
    selectedDate.value = [minDate.value, maxDate.value];
    selectedCloseDate.value = null;
};

</script>

<template>
    <div class="flex flex-col items-center px-4 py-6 gap-5 self-stretch rounded-2xl border border-gray-200 bg-white shadow-table md:px-6 md:gap-5">
        <div
            class="w-full"
        >
            <DataTable
                :value="histories"
                :rowsPerPageOptions="[10, 20, 50, 100]"
                lazy
                :paginator="histories?.length > 0"
                removableSort
                paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
                :currentPageReportTemplate="$t('public.paginator_caption')"
                :first="first"
                :page="page"
                :rows="1"
                ref="dt"
                dataKey="id"
                :totalRecords="totalRecords"
                :loading="isLoading"
                @page="onPage($event)"
                @sort="onSort($event)"
                :globalFilterFields="['name', 'email', 'username', 'meta_login', 'id_number', 'deal_id']"
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
                                   @click="exportRebateReport"
                                   class="w-full md:w-auto"
                               >
                                   {{ $t('public.export') }}
                               </Button>
                           </div>
                        </div>
                        <div class="flex justify-end self-stretch md:hidden">
                            <span class="text-gray-500 text-right text-sm font-medium">{{ $t('public.total') }}:</span>
                            <span class="text-gray-950 text-sm font-semibold ml-2">$ {{ formatAmount(totalRebateAmount) }}</span>
                        </div>
                    </div>
                </template>
                <template #empty>
                    <Empty
                        :title="$t('public.empty_rebate_record_title')"
                        :message="$t('public.empty_rebate_record_message')"
                    />
                </template>
                <template #loading>
                    <div class="flex flex-col gap-2 items-center justify-center">
                        <Loader />
                        <span class="text-sm text-gray-700">{{ $t('public.loading_rebate_record_caption') }}</span>
                    </div>
                </template>
                <template v-if="histories?.length > 0">
                    <Column
                        field="created_at"
                        sortable
                        :header="`${$t('public.date')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ dayjs(slotProps.data.created_at).format('YYYY/MM/DD') }}
                        </template>
                    </Column>
                    <Column
                        field="upline"
                        :header="$t('public.upline')"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            <div class="flex items-center gap-3">
                                <div class="flex flex-col items-start">
                                    <div class="font-medium">
                                        {{ slotProps.data.upline.name }}
                                    </div>
                                    <div class="text-gray-500 text-xs">
                                        {{ slotProps.data.upline.email }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Column>
                    <Column
                        field="upline_id"
                        :header="$t('public.upline_id')"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            <div class="flex items-center gap-3">
                                <div class="flex flex-col items-start">
                                    {{ slotProps.data.upline.id_number }}
                                </div>
                            </div>
                        </template>
                    </Column>
                    <Column
                        field="deal_id"
                        sortable
                        :header="`${$t('public.ticket')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ slotProps.data.deal_id }}
                        </template>
                    </Column>
                    <Column
                        field="open_time"
                        sortable
                        :header="`${$t('public.open_time')}`"
                        class="hidden md:table-cell min-w-32"
                    >
                        <template #body="slotProps">
                            {{ slotProps.data.open_time }}
                        </template>
                    </Column>
                    <Column
                        field="closed_time"
                        sortable
                        :header="`${$t('public.closed_time')}`"
                        class="hidden md:table-cell min-w-32"
                    >
                        <template #body="slotProps">
                            {{ slotProps.data.closed_time }}
                        </template>
                    </Column>
                    <Column
                        field="trade_open_price"
                        sortable
                        :header="`${$t('public.open_price')}&nbsp;($)`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ slotProps.data.trade_open_price ?? 0 }}
                        </template>
                    </Column>
                    <Column
                        field="trade_close_price"
                        sortable
                        :header="`${$t('public.close_price')}&nbsp;($)`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ slotProps.data.trade_close_price ?? 0 }}
                        </template>
                    </Column>
                    <Column
                        field="t_type"
                        :header="`${$t('public.type')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ $t(`public.${slotProps.data.t_type}`) }}
                        </template>
                    </Column>
                    <Column
                        field="name"
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
                                        {{ slotProps.data.downline.name }}
                                    </div>
                                    <div class="text-gray-500 text-xs">
                                        {{ slotProps.data.downline.email }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Column>
                    <Column
                        field="id_number"
                        :header="`${$t('public.id')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ slotProps.data.downline.id_number }}
                        </template>
                    </Column>
                    <Column
                        field="trade_profit"
                        sortable
                        :header="`${$t('public.profit')}&nbsp;($)`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ formatAmount(slotProps.data.trade_profit ?? 0) }}
                        </template>
                    </Column>
                    <Column
                        field="meta_login"
                        :header="`${$t('public.account')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            <div class="flex items-center content-center gap-3 flex-grow relative">
                                <span >{{ slotProps.data.meta_login }}</span>
                                <div
                                    class="flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded"
                                    :style="{
                                        backgroundColor: formatRgbaColor(slotProps.data.of_account_type.color, 0.15),
                                        color: `#${slotProps.data.of_account_type.color}`,
                                    }"
                                >
                                    {{ $t(`public.${slotProps.data.of_account_type.slug}`) }}
                                </div>
                            </div>
                        </template>
                    </Column>
                    <Column
                        field="symbol"
                        :header="`${$t('public.product')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ slotProps.data.symbol }}
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
                        field="revenue"
                        sortable
                        :header="`${$t('public.rebate')}&nbsp;($)`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ formatAmount(slotProps.data.revenue) }}
                        </template>
                    </Column>
                    <Column
                        field="t_status"
                        :header="`${$t('public.status')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            <StatusBadge value="success">
                                {{ $t(`public.completed`) }}
                            </StatusBadge>
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
                                            {{ slotProps.data.downline.name }}
                                        </div>
                                        <div class="text-gray-500 text-xs">
                                            {{ `${slotProps.data.meta_login}&nbsp;|&nbsp;${formatAmount(slotProps.data.volume)}&nbsp;Ł` }}
                                        </div>
                                    </div>
                                </div>
                                <div class="overflow-hidden text-right text-ellipsis font-semibold">
                                    $&nbsp;{{ formatAmount(slotProps.data.revenue) }}
                                </div>
                            </div>
                        </template>
                    </Column>
                    <ColumnGroup type="footer">
                        <Row>
                            <Column class="hidden md:table-cell" :footer="$t('public.total') + ':'" :colspan="13" footerStyle="text-align:right" />
                            <Column class="hidden md:table-cell" :footer="'$' + formatAmount(totalRebateAmount ?? 0)" />
                        </Row>
                    </ColumnGroup>
                </template>
            </DataTable>
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
                        :minDate="minDate"
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
                    v-model="upline_id"
                    :options="uplines"
                    filter
                    :filterFields="['name']"
                    optionLabel="name"
                    :placeholder="$t('public.select_upline')"
                    class="w-full"
                    scroll-height="236px"
                >
                    <template #value="slotProps">
                        <div v-if="slotProps.value" class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <div class="w-5 h-5 rounded-full overflow-hidden">
                                    <template v-if="slotProps.value.profile_photo">
                                        <img :src="slotProps.value.profile_photo" alt="profile_picture" />
                                    </template>
                                    <template v-else>
                                        <DefaultProfilePhoto />
                                    </template>
                                </div>
                                <div>{{ slotProps.value.name }}</div>
                            </div>
                        </div>
                        <span v-else class="text-gray-400">{{ slotProps.placeholder }}</span>
                    </template>
                    <template #option="slotProps">
                        <div class="flex items-center gap-2">
                            <div class="w-5 h-5 rounded-full overflow-hidden">
                                <template v-if="slotProps.option.profile_photo">
                                    <img :src="slotProps.option.profile_photo" alt="profile_picture" />
                                </template>
                                <template v-else>
                                    <DefaultProfilePhoto />
                                </template>
                            </div>
                            <div>{{ slotProps.option.name }}</div>
                        </div>
                    </template>
                </Dropdown>
            </div>

            <div class="flex flex-col items-center gap-2 self-stretch">
                <span class="self-stretch text-gray-950 text-xs font-bold">{{ $t('public.filter_type') }}</span>
                <div class="flex flex-col gap-1 self-stretch">
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton v-model="filters['t_type']" inputId="trade_buy" value="buy" class="w-4 h-4" />
                        <label for="buy">{{ $t('public.buy') }}</label>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton v-model="filters['t_type']" inputId="trade_sell" value="sell" class="w-4 h-4" />
                        <label for="sell">{{ $t('public.sell') }}</label>
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
</template>
