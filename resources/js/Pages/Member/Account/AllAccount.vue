<script setup>
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import {ref, watch, onMounted, computed} from "vue";
import {FilterMatchMode} from "primevue/api";
import Loader from "@/Components/Loader.vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import OverlayPanel from 'primevue/overlaypanel';
import Dropdown from "primevue/dropdown";
import MultiSelect from "primevue/multiselect";
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
import Tag from "primevue/tag";
import debounce from "lodash/debounce.js";

const props = defineProps({
    loadResults: Boolean,
    leverages: Array,
    tradingPlatforms: Array,
    uplines: Array,
    type: String,
});

const isLoading = ref(false);
const dt = ref(null);
const accounts = ref([]);
const { formatAmount } = transactionFormat();
const { formatRgbaColor } = generalFormat()
const totalRecords = ref(0);
const first = ref(0);

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    type: { value: props.type, matchMode: FilterMatchMode.EQUALS },
    platform: { value: null, matchMode: FilterMatchMode.EQUALS },
    account_type: { value: null, matchMode: FilterMatchMode.EQUALS },
    last_logged_in_days: { value: null, matchMode: FilterMatchMode.EQUALS },
    upline: { value: null, matchMode: FilterMatchMode.EQUALS },
    balance_type: { value: null, matchMode: FilterMatchMode.EQUALS },
    leverage: { value: null, matchMode: FilterMatchMode.EQUALS },
});

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
                lazyEvent: JSON.stringify(lazyParams.value)
            };

            const url = route('member.getAccountListingData', params);
            const response = await fetch(url);
            const results = await response.json();

            accounts.value = results?.data?.data;
            totalRecords.value = results?.data?.total;

            isLoading.value = false;
        }, 100);
    }  catch (e) {
        accounts.value = [];
        totalRecords.value = 0;
        isLoading.value = false;
    }
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

// Get account types
const accountTypes = ref([])
const loadingAccountTypes = ref(false);

const getAccountTypeByPlatform = async () => {
    loadingAccountTypes.value = true;

    try {
        const response = await axios.get(
            `/getAccountTypeByPlatform?trading_platform=${filters.value['platform'].value}`
        );

        // All groups from API
        accountTypes.value = response.data.accountTypes;

    } catch (error) {
        console.error('Error getting account types:', error);
    } finally {
        loadingAccountTypes.value = false;
    }
};

watch(filters.value['platform'], () => {
    getAccountTypeByPlatform()
})

watch(
    filters.value['global'],
    debounce(() => {
        loadLazyData();
    }, 300)
);

watch([filters.value['type'], filters.value['platform'], filters.value['account_type'], filters.value['last_logged_in_days'], filters.value['upline'], filters.value['balance_type'], filters.value['leverage']], () => {
    loadLazyData()
});

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

const filterCount = computed(() => {
    return Object.entries(filters.value)
        .filter(([key, filter]) =>
            key !== 'global' &&
            key !== 'type' &&
            filter?.value !== null &&
            filter?.value !== '' &&
            filter?.value !== undefined
        ).length;
});

const clearAll = () => {
    filters.value['global'].value = null;
    filters.value['platform'].value = null;
    filters.value['account_type'].value = null;
    filters.value['last_logged_in_days'].value = null;
    filters.value['leverage'].value = null;
    filters.value['upline'].value = null;
    filters.value['balance_type'].value = null;
};

const clearFilterGlobal = () => {
    filters.value['global'].value = null;
}

const op = ref();
const toggle = (event) => {
    op.value.toggle(event);
}

const data = ref([]);
const visible = ref(false);

const openDialog = (rowData) => {
    visible.value = true;
    data.value = rowData;

    getTradingAccountData();
}

// Get latest acc data

const loadingAccount = ref(false);

const getTradingAccountData = async () => {
    loadingAccount.value = true;
    try {
        const response = await axios.get(route('member.getFreshTradingAccountData', {
            meta_login: data.value.meta_login,
            account_type_id: data.value.account_type.id
        }));
        data.value = response.data.data;
        updateAccount(data.value)
    } catch (error) {
        console.error('Error fetching trade acc:', error);
    } finally {
        loadingAccount.value = false;
    }
};

const updateAccount = (updatedAccount) => {
    const index = accounts.value.findIndex(acc => acc.id === updatedAccount.id);

    if (index !== -1) {
        accounts.value[index] = updatedAccount;
    }
};

const getProfilePhoto = (user) => {
    // Find first media in 'profile_photo' collection
    const mediaItem = user.media?.find(m => m.collection_name === 'profile_photo');
    return mediaItem?.original_url || null;
};

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

    const url = route('member.getAccountListingData', params);

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
        :value="accounts"
        :rowsPerPageOptions="[10, 20, 50, 100]"
        lazy
        :paginator="accounts?.length > 0"
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
        :globalFilterFields="['name', 'email', 'id_number', 'meta_login']"
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
        <template v-if="accounts?.length > 0">
            <Column
                field="last_access"
                sortable
                class="hidden md:table-cell w-[15%]"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.last_logged_in') }}</span>
                </template>
                <template #body="{data}">
                    {{ dayjs(data.last_access).format('YYYY/MM/DD') }}
                    <div class="text-xs text-gray-500">
                        {{ dayjs(data.last_access).format('HH:mm:ss') }}
                    </div>
                </template>
            </Column>
            <Column
                field="name"
                sortable
                :header="$t('public.name')"
                class="hidden md:table-cell"
            >
                <template #body="{data}">
                    <div class="flex items-center gap-3 max-w-full">
                        <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                            <template v-if="getProfilePhoto(data.users)">
                                <img :src="getProfilePhoto(data.users)" alt="profile_photo" class="w-7 h-7 object-cover">
                            </template>
                            <template v-else>
                                <DefaultProfilePhoto />
                            </template>
                        </div>
                        <div class="grid grid-cols-1 items-start max-w-full">
                            <div class="font-medium max-w-full truncate">
                                {{ data.users.name }}
                            </div>
                            <div class="text-gray-500 text-xs max-w-full truncate">
                                {{ data.users.email }}
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
                    <span class="hidden md:block truncate">{{ $t('public.account') }}</span>
                </template>
                <template #body="slotProps">
                    <span class="break-all">{{ slotProps.data?.meta_login }}</span>
                </template>
            </Column>
            <Column
                field="balance"
                sortable
                class="hidden md:table-cell"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.balance') }} ($)</span>
                </template>
                <template #body="slotProps">
                    <span class="break-all">{{ formatAmount(slotProps.data?.trading_account.balance ?? 0) }}</span>
                </template>
            </Column>
            <Column
                field="credit"
                sortable
                class="hidden md:table-cell"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.credit') }} ($)</span>
                </template>
                <template #body="slotProps">
                    <span class="break-all">{{ slotProps.data?.trading_account.credit ? formatAmount(slotProps.data?.trading_account.credit) : formatAmount(0) }}</span>
                </template>
            </Column>
            <Column
                field="leverage"
                sortable
                class="hidden md:table-cell"
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
                class="hidden md:table-cell"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.account_type') }}</span>
                </template>
                <template #body="slotProps">
                    <div class="flex gap-2 items-center">
                        <Tag
                            :severity="slotProps.data.account_type.trading_platform.slug === 'mt4' ? 'secondary' : 'info'"
                            class="uppercase"
                            :value="slotProps.data.account_type.trading_platform.slug"
                        />
                        <div
                            class="break-all flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded w-fit text-nowrap"
                            :style="{
                            backgroundColor: formatRgbaColor(slotProps.data?.account_type.color, 0.15),
                            color: `#${slotProps.data?.account_type.color}`,
                        }"
                        >
                            {{ $t('public.' + slotProps.data?.account_type.slug) }}
                        </div>
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
                        @updated:account="updateAccount"
                    />
                </template>
            </Column>
            <Column class="md:hidden">
                <template #body="slotProps">
                    <div class="flex items-center gap-2 self-stretch">
                        <div class="flex flex-col items-start w-full">
                            <span class="text-sm text-gray-950 font-semibold">{{ slotProps.data?.meta_login }}</span>
                            <div class="text-xs">
                                <span class="text-gray-500">{{ $t('public.last_logged_in') }}</span> <span class="text-gray-700 font-medium"> {{ dayjs(slotProps.data?.last_access).format('YYYY/MM/DD HH:mm:ss') }}</span>
                            </div>
                        </div>
                        <AccountTableActions
                            :account="slotProps.data"
                            @updated:account="updateAccount"
                        />
                    </div>
                </template>
            </Column>
        </template>
    </DataTable>

    <OverlayPanel ref="op">
        <div class="flex flex-col gap-5 w-60 py-5 px-4">
            <!-- Filter Last logged in -->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_last_logged_in') }}
                </div>
                <div class="flex flex-col self-stretch">
                    <!-- Greater than 30 days -->
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <div class="flex justify-center items-center rounded-full grow-0 shrink-0 hover:bg-gray-100">
                            <RadioButton
                                v-model="filters['last_logged_in_days'].value"
                                inputId="greater_than_30_days"
                                value="greater_than_30_days"
                                class="w-4 h-4"
                            />
                        </div>
                        <label for="greater_than_30_days">{{ $t('public.greater_than_30_days') }}</label>
                    </div>

                    <!-- Greater than 90 days -->
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <div class="flex justify-center items-center rounded-full grow-0 shrink-0 hover:bg-gray-100">
                            <RadioButton
                                v-model="filters['last_logged_in_days'].value"
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
                <div class="flex flex-col self-stretch">
                    <!-- Zero Balance -->
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <div class="flex justify-center items-center rounded-full grow-0 shrink-0 hover:bg-gray-100">
                            <RadioButton
                                v-model="filters['balance_type'].value"
                                inputId="zero_balance"
                                value="0.00"
                                class="w-4 h-4"
                            />
                        </div>
                        <label for="zero_balance">{{ $t('public.zero_balance') }}</label>
                    </div>

                    <!-- without deposit -->
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <div class="flex justify-center items-center rounded-full grow-0 shrink-0 hover:bg-gray-100">
                            <RadioButton
                                v-model="filters['balance_type'].value"
                                inputId="never_deposited"
                                value="never_deposited"
                                class="w-4 h-4"
                            />
                        </div>
                        <label for="never_deposited">{{ $t('public.never_deposited') }}</label>
                    </div>
                </div>
            </div>

            <!-- Filter platform -->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_platform') }}
                </div>
                <div class="flex flex-col self-stretch">
                    <div
                        v-for="platform in tradingPlatforms"
                        class="flex items-center gap-2 text-sm text-gray-950"
                    >
                        <div class="flex justify-center items-center rounded-full grow-0 shrink-0 hover:bg-gray-100">
                            <RadioButton
                                v-model="filters['platform'].value"
                                :inputId="platform.slug"
                                :value="platform.slug"
                                class="w-4 h-4"
                            />
                        </div>
                        <label :for="platform.slug" class="uppercase">{{ platform.slug }}</label>
                    </div>
                </div>
            </div>

            <!-- Filter account_type-->
            <div v-if="filters['platform'].value !== null" class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_account_type') }}
                </div>
                <MultiSelect
                    v-model="filters['account_type'].value"
                    :options="accountTypes"
                    :placeholder="$t('public.account_type_placeholder')"
                    filter
                    :filterFields="['name']"
                    :maxSelectedLabels="1"
                    :selectedItemsLabel="`${filters['account_type']?.length} ${$t('public.account_types_selected')}`"
                    class="w-full font-normal"
                    :disabled="filters['platform'].value === null"
                    :loading="loadingAccountTypes"
                >
                    <template #option="{ option }">
                        <div class="flex items-center gap-2">
                            <span>{{ option.name }}</span>
                        </div>
                    </template>

                    <template #value>
                        <div v-if="filters['account_type']?.length === 1">
                            <span>{{ filters['account_type'][0].name }}</span>
                        </div>
                        <span v-else-if="filters['account_type']?.length > 1">
                            {{ filters['account_type']?.length }} {{ $t('public.account_types_selected') }}
                        </span>
                        <span v-else class="text-gray-400">
                            {{ $t('public.account_type_placeholder') }}
                        </span>
                    </template>
                </MultiSelect>
            </div>

            <!-- Filter Leverage-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_leverage') }}
                </div>
                <Dropdown
                    v-model="filters['leverage'].value"
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

            <!-- Filter Upline-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_upline') }}
                </div>
                <Dropdown
                    v-model="filters['upline'].value"
                    :options="uplines"
                    filter
                    :filterFields="['name', 'email', 'id_number']"
                    optionLabel="name"
                    :placeholder="$t('public.filter_upline')"
                    class="w-full"
                    scroll-height="236px"
                    :virtualScrollerOptions="{ itemSize: 56 }"
                >
                <template #option="{option}">
                        <div class="flex flex-col">
                            <span>{{ option.name }}</span>
                            <span class="text-xs text-gray-400 max-w-52 truncate">{{ option.email }}</span>
                        </div>
                    </template>
                    <template #value="{value, placeholder}">
                        <div v-if="value">
                            <span>{{ value.name }}</span>
                        </div>
                        <span v-else class="text-gray-400">
                            {{ placeholder }}
                        </span>
                    </template>
                </Dropdown>
            </div>

            <div class="flex w-full">
                <Button
                    type="button"
                    variant="primary-outlined"
                    class="flex justify-center w-full"
                    @click="clearAll"
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
                    <template v-if="getProfilePhoto(data.users)">
                        <img :src="getProfilePhoto(data.users)" alt="profile_photo" class="w-7 h-7 object-cover">
                    </template>
                    <template v-else>
                        <DefaultProfilePhoto />
                    </template>
                </div>
                <div class="flex flex-col items-start flex-grow">
                    <span class="self-stretch overflow-hidden text-gray-950 text-ellipsis text-sm font-medium">{{ data.users.name }}</span>
                    <span class="self-stretch overflow-hidden text-gray-500 text-ellipsis text-xs">{{ data?.users.email }}</span>
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
                <span class="self-stretch text-gray-950 text-sm font-medium">$ {{ formatAmount(data?.trading_account.balance ?? 0) }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.equity') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">$ {{ data?.trading_account.equity ? formatAmount(data?.trading_account.equity) : formatAmount(0) }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.credit') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">$ {{ formatAmount(data?.trading_account.credit) }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.leverage') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ data?.leverage === 0 ? $t('public.free') : `1:${data?.leverage}` }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.account_type') }}</span>
                <div class="flex gap-2 items-center">
                    <Tag
                        :severity="data.account_type.trading_platform.slug === 'mt4' ? 'secondary' : 'info'"
                        class="uppercase"
                        :value="data.account_type.trading_platform.slug"
                    />
                    <div
                        class="flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded"
                        :style="{
                        backgroundColor: formatRgbaColor(data?.account_type.color, 0.15),
                        color: `#${data?.account_type.color}`,
                    }"
                    >
                        {{ $t(`public.${data.account_type.slug}`) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col items-center py-4 gap-3 self-stretch">
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:min-w-[140px] text-gray-500 text-xs">{{ $t('public.last_logged_in') }}</span>
                <div class="flex flex-col items-start">
                    <span class="self-stretch text-gray-950 text-sm font-medium">{{ dayjs(data?.last_access).format('YYYY/MM/DD HH:mm:ss') }}</span>
                    <Tag
                        :severity="dayjs().diff(dayjs(data?.last_access ?? dayjs().toDate()), 'day') === 0 ? 'secondary' : 'warning'"
                        :value="`${dayjs().diff(dayjs(data?.last_access ?? dayjs().toDate()), 'day')} ${$t('public.days')}`"
                    />
                </div>
            </div>
        </div>

        <!-- Loading mask -->
        <div v-if="loadingAccount" class="absolute rounded-3xl inset-0 bg-gray-200/60 flex items-center justify-center z-10">
            <div class="flex flex-col justify-center items-center gap-5 self-stretch">
                <Loader />
                <span class="text-sm font-semibold text-gray-700">
                    {{ $t('public.loading_accounts_data') }}
                </span>
            </div>
        </div>
    </Dialog>
</template>
