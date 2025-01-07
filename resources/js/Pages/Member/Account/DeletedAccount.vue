<script setup>
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import {ref, watch, watchEffect, onMounted} from "vue";
import {FilterMatchMode} from "primevue/api";
import Loader from "@/Components/Loader.vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import Empty from "@/Components/Empty.vue";
import dayjs from "dayjs";
import InputText from "primevue/inputtext";
import Button from "@/Components/Button.vue";
import {IconCircleXFilled, IconSearch, IconX} from "@tabler/icons-vue";
import {transactionFormat} from "@/Composables/index.js";
import Dialog from "primevue/dialog";
import {usePage} from "@inertiajs/vue3";
import Calendar from "primevue/calendar";
import debounce from "lodash/debounce.js";

const props = defineProps({
  loadResults: Boolean,
  leverages: Array,
  accountTypes: Array,
});

const accounts = ref([]);
const totalRecords = ref(0);
const rows = ref(10);
const page = ref(0);
const sortField = ref(null);  
const sortOrder = ref(null);  // (1 for ascending, -1 for descending)
const loading = ref(false);
const {formatAmount, formatDate} = transactionFormat();
const visible = ref(false);
const selectedDate = ref([]);

const filters = ref({
    global: '',
});

const clearFilterGlobal = () => {
    filters.value.global = '';
}

const getResults = async (selectedDate = []) => {
    loading.value = true;

    try {
        let url = `/member/getAccountListingPaginate?rows=${rows.value}&page=${page.value}`;

        if (filters.value.global) {
            url += `&search=${filters.value.global}`;
        }

        if (selectedDate.length === 2) {
            const [startDate, endDate] = selectedDate;

            if (startDate && endDate) {
                url += `&startDate=${formatDate(startDate)}&endDate=${formatDate(endDate)}`;
            }
        }

        if (sortField.value && sortOrder.value !== null) {
            url += `&sortField=${sortField.value}&sortOrder=${sortOrder.value}`;
        }

        const response = await axios.get(url);
        accounts.value = response?.data?.data?.data;
        totalRecords.value = response?.data?.data?.total;
    } catch (error) {
        console.error('Error changing locale:', error);
    } finally {
        loading.value = false;
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

// If the prop is passed initially as true, run getResults
onMounted(() => {
    getResults();
});

const exportCSV = () => {
    dt.value.exportCSV();
};

// Clear date selection
const clearDate = () => {
    selectedDate.value = [];
};

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

// Watch for changes on the entire 'filters' object and debounce the API call
watch(filters, debounce(() => {
    page.value = 0; // Reset to first page when filters change
    getResults(); // Call getResults function to fetch the data
}, 1000), { deep: true });

const data = ref({});
const openDialog = (rowData) => {
    visible.value = true;
    data.value = rowData;
}

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults();
    }
});
</script>

<template>
    <DataTable
        :value="accounts"
        :paginator="accounts?.length > 0"
        lazy
        removableSort
        :rows="rows"
        :rowsPerPageOptions="[10, 20, 50, 100]"
        tableStyle="md:min-width: 50rem"
        paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
        :currentPageReportTemplate="$t('public.paginator_caption')"
        :globalFilterFields="['user_name', 'user_email', 'meta_login']"
        ref="dt"
        dataKey="id"
        selectionMode="single"
        @row-click="(event) => openDialog(event.data)"
        :totalRecords="totalRecords"
        :loading="loading"
        @page="onPage($event)"
        @sort="onSort($event)"
    >
        <template #header>
            <div class="flex flex-col md:flex-row gap-3 items-center self-stretch md:pb-6">
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
                <div class="grid grid-cols-2 w-full gap-3">
                    <div class="relative w-full md:w-[272px]">
                        <Calendar
                            v-model="selectedDate"
                            selectionMode="range"
                            :manualInput="false"
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
        <template #empty>
            <Empty :title="$t('public.empty_account_title')" :message="$t('public.empty_account_message')"/>
        </template>
        <template #loading>
            <div class="flex flex-col gap-2 items-center justify-center">
                <Loader/>
                <span class="text-sm text-gray-700">{{ $t('public.loading_accounts_data') }}</span>
            </div>
        </template>
        <template v-if="accounts?.length > 0">
            <Column
                field="deleted_at"
                sortable
                class="hidden md:table-cell"
            >
                <template #header>
                    <span class="hidden md:block max-w-[40px] md:max-w-[60px] lg:max-w-[100px] truncate">{{
                            $t('public.deleted_time')
                        }}</span>
                </template>
                <template #body="slotProps">
                    {{ dayjs(slotProps.data.deleted_at).format('YYYY/MM/DD HH:mm:ss') }}
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
                            <template v-if="slotProps.data.user_profile_photo">
                                <img :src="slotProps.data.user_profile_photo" alt="profile_photo">
                            </template>
                            <template v-else>
                                <DefaultProfilePhoto/>
                            </template>
                        </div>
                        <div class="flex flex-col items-start">
                            <div class="font-medium max-w-[120px] lg:max-w-[160px] xl:max-w-[400px] truncate">
                                {{ slotProps.data.user_name }}
                            </div>
                            <div class="text-gray-500 text-xs max-w-[120px] lg:max-w-[160px] xl:max-w-[400px] truncate">
                                {{ slotProps.data.user_email }}
                            </div>
                        </div>
                    </div>
                </template>
            </Column>
            <Column
                field="meta_login"
                sortable
                class="hidden md:table-cell"
            >
                <template #header>
                    <span class="hidden md:block max-w-[40px] lg:max-w-[100px] truncate">{{
                            $t('public.account')
                        }}</span>
                </template>
                <template #body="slotProps">
                    {{ slotProps.data.meta_login }}
                </template>
            </Column>
            <Column
                field="balance"
                sortable
                class="hidden md:table-cell"
            >
                <template #header>
                    <span class="hidden md:block max-w-[40px] lg:max-w-[100px] truncate">{{
                            $t('public.balance')
                        }} ($)</span>
                </template>
                <template #body="slotProps">
                    {{ formatAmount(slotProps.data.balance) }}
                </template>
            </Column>
            <Column
                field="credit"
                sortable
                class="hidden md:table-cell"
            >
                <template #header>
                    <span class="hidden md:block max-w-[40px] lg:max-w-[100px] truncate">{{
                            $t('public.credit')
                        }} ($)</span>
                </template>
                <template #body="slotProps">
                    {{ slotProps.data?.credit ? formatAmount(slotProps.data?.credit) : formatAmount(0) }}
                </template>
            </Column>
            <Column class="md:hidden">
                <template #body="slotProps">
                    <div class="flex items-center gap-2 self-stretch">
                        <div class="flex flex-col items-start w-full">
                            <span class="text-sm text-gray-950 font-semibold">{{ slotProps.data.meta_login }}</span>
                            <div class="text-xs">
                                <span class="text-gray-500">{{ $t('public.deleted_time') }}</span> <span
                                class="text-gray-700 font-medium"> {{
                                    dayjs(slotProps.data.deleted_at).format('YYYY/MM/DD HH:mm:ss')
                                }}</span>
                            </div>
                        </div>
                    </div>
                </template>
            </Column>
        </template>
    </DataTable>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.account_details')"
        class="dialog-xs md:dialog-sm"
    >
        <div
            class="flex flex-col justify-center items-start pb-4 gap-3 self-stretch border-b border-gray-200 md:flex-row md:pt-4 md:justify-between">
            <div class="flex items-center gap-3 self-stretch">
                <div class="w-9 h-9 rounded-full overflow-hidden grow-0 shrink-0">
                    <div v-if="data.user_profile_photo">
                        <img :src="data.user_profile_photo" alt="Profile Photo"/>
                    </div>
                    <div v-else>
                        <DefaultProfilePhoto/>
                    </div>
                </div>
                <div class="flex flex-col items-start flex-grow">
                    <span class="self-stretch overflow-hidden text-gray-950 text-ellipsis text-sm font-medium">{{
                            data.user_name
                        }}</span>
                    <span class="self-stretch overflow-hidden text-gray-500 text-ellipsis text-xs">{{
                            data.user_email
                        }}</span>
                </div>
            </div>
        </div>

        <div class="flex flex-col items-center py-4 gap-3 self-stretch border-b border-gray-200">
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.account') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ data.meta_login }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.balance') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">$ {{ formatAmount(data.balance) }}</span>
            </div>
            <!-- <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.equity') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">$ {{ data?.equity ? formatAmount(data?.equity) : formatAmount(0) }}</span>
            </div> -->
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.credit') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">$ {{ formatAmount(data.credit) }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.leverage') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">1:{{ data.leverage }}</span>
            </div>
        </div>

        <div class="flex flex-col items-center py-4 gap-3 self-stretch">
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:min-w-[140px] text-gray-500 text-xs">{{
                        $t('public.deleted_time')
                    }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{
                        dayjs(data.deleted_at).format('YYYY/MM/DD HH:mm:ss')
                    }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:min-w-[140px] text-gray-500 text-xs">{{
                        $t('public.last_logged_in')
                    }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{
                        dayjs(data.last_login).format('YYYY/MM/DD HH:mm:ss')
                    }} ({{ dayjs().diff(dayjs(data.last_login), 'day') }} {{ $t('public.days') }})</span>
            </div>
        </div>
    </Dialog>
</template>
