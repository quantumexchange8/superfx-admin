<script setup>
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import {ref, watch, watchEffect, onMounted} from "vue";
import {FilterMatchMode} from "primevue/api";
import Loader from "@/Components/Loader.vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import OverlayPanel from 'primevue/overlaypanel';
import Dropdown from "primevue/dropdown";
import Empty from "@/Components/Empty.vue";
import dayjs from "dayjs";
import AccountTableActions from "@/Pages/Member/Account/Partials/AccountTableActions.vue";
import Badge from "@/Components/Badge.vue";
import InputText from "primevue/inputtext";
import Button from "@/Components/Button.vue";
import {IconAdjustments, IconCircleXFilled, IconSearch} from "@tabler/icons-vue";
import RadioButton from "primevue/radiobutton";
import {transactionFormat, generalFormat} from "@/Composables/index.js";
import Dialog from "primevue/dialog";
import {usePage} from "@inertiajs/vue3";
import debounce from "lodash/debounce.js";

const props = defineProps({
  loadResults: Boolean,
  leverages: Array,
  accountTypes: Array,
  uplines: Array,
});

// overlay panel
const op = ref();
const exportStatus = ref(false);
const accounts = ref([]);
const totalRecords = ref(0);
const leverages = ref();
const accountTypes = ref();
const selectedUplines = ref();
const uplines = ref()
const rows = ref(10);
const page = ref(0);
const sortField = ref(null);  
const sortOrder = ref(null);  // (1 for ascending, -1 for descending)
const loading = ref(false);
const filterCount = ref(0);
const {formatAmount} = transactionFormat();
const { formatRgbaColor } = generalFormat()
const visible = ref(false);

// Watch both 'leverages' and 'accountTypes' props together
watch(
  () => [props.leverages, props.accountTypes, props.uplines], ([newLeverages, newAccountTypes, newUplines]) => {
    leverages.value = newLeverages;
    accountTypes.value = newAccountTypes;
    uplines.value = newUplines;
  },
  { immediate: true } // Optionally add `immediate: true` to run the watch immediately on component mount
);

const filters = ref({
    global: '',
    last_logged_in_days: '',
    balance: '',
    leverage: '',
    account_type: '',
    upline_id: '',
});

// Watch selected values to update filters
watch([selectedUplines], ([newUplineId]) => {
    if (newUplineId) {
        filters.value['upline_id'] = newUplineId.value;
    }
});

const clearFilterGlobal = () => {
    filters.value.global = '';
}

const clearFilter = () => {
    op.value.toggle(false);

    filters.value = {
        global: '',
        last_logged_in_days: '',
        balance: '',
        leverage: '',
        account_type: '',
        upline_id: '',
    };
    selectedUplines.value = null;
};

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
    let url = `/member/getAccountListingPaginate?type=all&rows=${rows.value}&page=${page.value}`;

    // Add filters if present
    if (filters.value.global) {
        url += `&search=${filters.value.global}`;
    }

    if (filters.value.last_logged_in_days) {
        url += `&last_logged_in_days=${filters.value.last_logged_in_days}`;
    }

    if (filters.value.balance) {
        url += `&balance=${filters.value.balance}`;
    }

    if (filters.value.leverage) {
        url += `&leverage=${filters.value.leverage.value}`;
    }

    if (filters.value.account_type) {
        url += `&account_type=${filters.value.account_type.value}`;
    }

    if (filters.value.upline_id) {
        url += `&upline_id=${filters.value.upline_id}`;
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
    loading.value = true;

    try {
        // Construct the URL dynamically
        const url = constructUrl();

        // Make the API request
        const response = await axios.get(url);
        
        // Update the data and total records with the response
        accounts.value = response?.data?.data?.data;
        totalRecords.value = response?.data?.data?.total;
    } catch (error) {
        console.error('Error changing locale:', error);
    } finally {
        loading.value = false;
    }
};

// Optimized exportAccount function
const exportAccount = async () => {
    exportStatus.value = true;

    try {
        // Construct the URL dynamically with exportStatus for export
        const url = constructUrl(exportStatus.value); // Pass true to include exportStatus

        // Send the request to trigger the export
        window.location.href = url;  // This will trigger the download directly
    } catch (e) {
        console.error('Error occurred during export:', e);  // Log the error if any
    } finally {
        loading.value = false;  // Reset loading state
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

// If the prop is passed initially as true, run getResults
onMounted(() => {
    getResults();
});

const toggle = (event) => {
    op.value.toggle(event);
}

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
                    <div class="w-full flex justify-end">
                        <Button
                            variant="primary-outlined"
                            @click="exportAccount"
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
        <template v-if="accounts?.length > 0">
            <Column
                field="last_access"
                sortable
                class="hidden md:table-cell w-[15%] max-w-0"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.last_logged_in') }}</span>
                </template>
                <template #body="slotProps">
                    {{ dayjs(slotProps.data?.last_login).format('YYYY/MM/DD HH:mm:ss') }}
                </template>
            </Column>
            <Column
                field="name"
                sortable
                :header="$t('public.name')"
                class="hidden md:table-cell w-[25%] max-w-0"
            >
                <template #body="slotProps">
                    <div class="flex items-center gap-3 max-w-full">
                        <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                            <template v-if="slotProps.data?.user_profile_photo">
                                <img :src="slotProps.data?.user_profile_photo" alt="profile_photo">
                            </template>
                            <template v-else>
                                <DefaultProfilePhoto />
                            </template>
                        </div>
                        <div class="grid grid-cols-1 items-start max-w-full">
                            <div class="font-medium max-w-full truncate">
                                {{ slotProps.data?.user_name }}
                            </div>
                            <div class="text-gray-500 text-xs max-w-full truncate">
                                {{ slotProps.data?.user_email }}
                            </div>
                        </div>
                    </div>
                </template>
            </Column>
            <Column
                field="meta_login"
                sortable
                class="hidden md:table-cell w-[10%] max-w-0"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.account') }}</span>
                </template>
                <template #body="slotProps">
                    <span class="break-all">{{ slotProps.data?.meta_login }}</span>
                </template>
            </Column>
            <Column
                field="balance"
                sortable
                class="hidden md:table-cell w-[10%] max-w-0"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.balance') }} ($)</span>
                </template>
                <template #body="slotProps">
                    <span class="break-all">{{ formatAmount(slotProps.data?.balance) }}</span>
                </template>
            </Column>
            <Column
                field="credit"
                sortable
                class="hidden md:table-cell w-[10%] max-w-0"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.credit') }} ($)</span>
                </template>
                <template #body="slotProps">
                    <span class="break-all">{{ slotProps.data?.credit ? formatAmount(slotProps.data?.credit) : formatAmount(0) }}</span>
                </template>
            </Column>
            <Column
                field="leverage"
                sortable
                class="hidden md:table-cell w-[10%] max-w-0"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.leverage') }}</span>
                </template>
                <template #body="slotProps">
                    <span class="break-all">{{ slotProps.data?.leverage === 0 ? $t('public.free') : `1:${slotProps.data?.leverage}` }}</span>
                </template>
            </Column>
            <Column
                field="account_type"
                class="hidden md:table-cell w-[10%] max-w-0"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.account_type') }}</span>
                </template>
                <template #body="slotProps">
                    <div
                        class="break-all flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded"
                        :style="{
                            backgroundColor: formatRgbaColor(slotProps.data?.account_type_color, 0.15),
                            color: `#${slotProps.data?.account_type_color}`,
                        }"
                    >
                        {{ $t('public.' + slotProps.data?.account_type) }}
                    </div>
                </template>
            </Column>
            <Column
                field="action"
                header=""
                class="hidden md:table-cell w-[10%] max-w-0"
            >
                <template #body="slotProps">
                    <AccountTableActions
                        :account="slotProps.data"
                    />
                </template>
            </Column>
            <Column class="md:hidden">
                <template #body="slotProps">
                    <div class="flex items-center gap-2 self-stretch">
                        <div class="flex flex-col items-start w-full">
                            <span class="text-sm text-gray-950 font-semibold">{{ slotProps.data?.meta_login }}</span>
                            <div class="text-xs">
                                <span class="text-gray-500">{{ $t('public.last_logged_in') }}</span> <span class="text-gray-700 font-medium"> {{ dayjs(slotProps.data?.last_login).format('YYYY/MM/DD HH:mm:ss') }}</span>
                            </div>
                        </div>
                        <AccountTableActions
                            :account="slotProps.data"
                        />
                    </div>
                </template>
            </Column>
        </template>
    </DataTable>

    <OverlayPanel ref="op">
        <div class="flex flex-col gap-8 w-60 py-5 px-4">
            <!-- Filter Last logged in-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_last_logged_in') }}
                </div>
                <div class="flex flex-col gap-1 self-stretch">
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <div class="flex w-8 h-8 p-2 justify-center items-center rounded-full grow-0 shrink-0 hover:bg-gray-100">
                            <RadioButton
                                v-model="filters['last_logged_in_days']"
                                inputId="greater_than_90_days"
                                value="greater_than_90_days"
                                class="w-4 h-4"
                            />
                        </div>
                        <label for="greater_than_90_days">{{ $t('public.greater_than_90_days') }}</label>
                    </div>
                </div>
            </div>

            <!-- Filter balance -->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_balance') }}
                </div>
                <div class="flex flex-col gap-1 self-stretch">
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <div class="flex w-8 h-8 p-2 justify-center items-center rounded-full grow-0 shrink-0 hover:bg-gray-100">
                            <RadioButton
                                v-model="filters['balance']"
                                inputId="zero_balance"
                                value="0.00"
                                class="w-4 h-4"
                            />
                        </div>
                        <label for="zero_balance">{{ $t('public.zero_balance') }}</label>
                    </div>
                </div>
            </div>

            <!-- Filter Leverage-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_leverage') }}
                </div>
                <Dropdown
                    v-model="filters['leverage']"
                    :options="leverages"
                    filter
                    :filterFields="['name']"
                    optionLabel="name"
                    :placeholder="$t('public.leverages_placeholder')"
                    class="w-full"
                    scroll-height="236px"
                >
                    <template #value="slotProps">
                        <div v-if="slotProps.value" class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <div>{{ slotProps.value.name }}</div>
                            </div>
                        </div>
                        <span v-else class="text-gray-400">{{ slotProps.placeholder }}</span>
                    </template>
                    <template #option="slotProps">
                        <div class="flex items-center gap-2">
                            <div>{{ slotProps.option.name }}</div>
                        </div>
                    </template>
                </Dropdown>
            </div>

            <!-- Filter account_type-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_account_type') }}
                </div>
                <Dropdown
                    v-model="filters['account_type']"
                    :options="accountTypes"
                    filter
                    :filterFields="['name']"
                    optionLabel="name"
                    :placeholder="$t('public.account_type_placeholder')"
                    class="w-full"
                    scroll-height="236px"
                >
                    <template #value="slotProps">
                        <div v-if="slotProps.value" class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <div>{{ slotProps.value.name }}</div>
                            </div>
                        </div>
                        <span v-else class="text-gray-400">{{ slotProps.placeholder }}</span>
                    </template>
                    <template #option="slotProps">
                        <div class="flex items-center gap-2">
                            <div>{{ slotProps.option.name }}</div>
                        </div>
                    </template>
                </Dropdown>
            </div>

            <!-- Filter Upline-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_upline') }}
                </div>
                <Dropdown
                    v-model="selectedUplines"
                    :options="uplines"
                    filter
                    :filterFields="['name', 'email']"
                    optionLabel="name"
                    :placeholder="$t('public.filter_upline')"
                    class="w-full"
                    scroll-height="236px"
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

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.account_details')"
        class="dialog-xs md:dialog-sm"
    >
        <div class="flex flex-col justify-center items-start pb-4 gap-3 self-stretch border-b border-gray-200 md:flex-row md:pt-4 md:justify-between">
            <div class="flex items-center gap-3 self-stretch">
                <div class="w-9 h-9 rounded-full overflow-hidden grow-0 shrink-0">
                    <div v-if="data?.user_profile_photo">
                        <img :src="data?.user_profile_photo" alt="Profile Photo" />
                    </div>
                    <div v-else>
                        <DefaultProfilePhoto />
                    </div>
                </div>
                <div class="flex flex-col items-start flex-grow">
                    <span class="self-stretch overflow-hidden text-gray-950 text-ellipsis text-sm font-medium">{{ data?.user_name }}</span>
                    <span class="self-stretch overflow-hidden text-gray-500 text-ellipsis text-xs">{{ data?.user_email }}</span>
                </div>
            </div>
        </div>

        <div class="flex flex-col items-center py-4 gap-3 self-stretch border-b border-gray-200">
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.account') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ data?.meta_login }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.balance') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">$ {{ formatAmount(data?.balance) }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.equity') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">$ {{ data?.equity ? formatAmount(data?.equity) : formatAmount(0) }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.credit') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">$ {{ formatAmount(data?.credit) }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.leverage') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ data?.leverage === 0 ? $t('public.free') : `1:${data?.leverage}` }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.account_type') }}</span>
                <div
                    class="flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded"
                    :style="{
                        backgroundColor: formatRgbaColor(data?.account_type_color, 0.15),
                        color: `#${data?.account_type_color}`,
                    }"
                >
                    {{ $t('public.' + data?.account_type) }}
                </div>
            </div>
        </div>

        <div class="flex flex-col items-center py-4 gap-3 self-stretch">
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:min-w-[140px] text-gray-500 text-xs">{{ $t('public.last_logged_in') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ dayjs(data?.last_login).format('YYYY/MM/DD HH:mm:ss') }} ({{ dayjs().diff(dayjs(data?.last_login), 'day') }} {{ $t('public.days') }})</span>
            </div>
        </div>
    </Dialog>
</template>
