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
import Badge from "@/Components/Badge.vue";
import InputText from "primevue/inputtext";
import Button from "@/Components/Button.vue";
import {IconAdjustments, IconCircleXFilled, IconSearch, IconX} from "@tabler/icons-vue";
import RadioButton from "primevue/radiobutton";
import {transactionFormat, generalFormat} from "@/Composables/index.js";
import Dialog from "primevue/dialog";
import Tag from "primevue/tag";
import debounce from "lodash/debounce.js";
import StatusBadge from "@/Components/StatusBadge.vue";
import Calendar from "primevue/calendar";

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
    type: { value: props.selectedType, matchMode: FilterMatchMode.EQUALS },
    month_range: { value: props.selectedMonths, matchMode: FilterMatchMode.EQUALS },
    role: { value: null, matchMode: FilterMatchMode.EQUALS },
    account_type: { value: null, matchMode: FilterMatchMode.EQUALS },
    trading_platform: { value: null, matchMode: FilterMatchMode.EQUALS },
    upline: { value: null, matchMode: FilterMatchMode.EQUALS },
    status: { value: null, matchMode: FilterMatchMode.EQUALS },
    payment_platform: { value: null, matchMode: FilterMatchMode.EQUALS },
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

            const url = route('transaction.getTransactionListingData', params);

            const response = await fetch(url, {
                signal: abortController.value.signal
            });

            const results = await response.json();
            transactions.value = results?.data?.data;
            totalRecords.value = results?.data?.total;

            emit('update-totals', {
                totalTransaction: totalRecords.value,
                totalTransactionAmount: results?.totalSuccessAmount,
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

// Get users
const users = ref([])
const loadingUsers = ref(false);

const getUsers = async () => {
    loadingUsers.value = true;

    try {
        const response = await axios.get(route('getUsers'));

        // All groups from API
        users.value = response.data.users;

    } catch (error) {
        console.error('Error getting users:', error);
    } finally {
        loadingUsers.value = false;
    }
};

// Get payment_platforms
const paymentGateways = ref([])
const loadingPaymentGateways = ref(false);

const getPaymentGateways = async () => {
    loadingPaymentGateways.value = true;

    try {
        const response = await axios.get(route('get_payment_gateways'));

        // All groups from API
        paymentGateways.value = response.data.paymentGateways;

    } catch (error) {
        console.error('Error getting users:', error);
    } finally {
        loadingPaymentGateways.value = false;
    }
};

// Get account types
const accountTypes = ref([])
const loadingAccountTypes = ref(false);

const getAccountTypeByPlatform = async () => {
    loadingAccountTypes.value = true;

    try {
        const response = await axios.get(
            `/getAccountTypeByPlatform?trading_platform=${filters.value['trading_platform'].value}`
        );

        // All groups from API
        accountTypes.value = response.data.accountTypes;

    } catch (error) {
        console.error('Error getting account types:', error);
    } finally {
        loadingAccountTypes.value = false;
    }
};

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

watch(filters.value['trading_platform'], () => {
    getAccountTypeByPlatform()
})

watch([filters.value['type'], filters.value['month_range'], filters.value['role'], filters.value['account_type'], filters.value['trading_platform'], filters.value['upline'], filters.value['status'], filters.value['payment_platform']], () => {
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
            key !== 'month_range' &&
            filter?.value !== null &&
            filter?.value !== '' &&
            filter?.value !== undefined
        ).length;
});

const clearAll = () => {
    filters.value['global'].value = null;
    filters.value['role'].value = null;
    filters.value['account_type'].value = null;
    filters.value['trading_platform'].value = null;
    filters.value['upline'].value = null;
    filters.value['status'].value = null;
    filters.value['payment_platform'].value = null;
    filters.value['start_date'].value = null;
    filters.value['end_date'].value = null;

    clearDate()
};

const clearFilterGlobal = () => {
    filters.value['global'].value = null;
}

const op = ref();
const toggle = (event) => {
    op.value.toggle(event);
    getUsers();
    getPaymentGateways();
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

const selectedDate = ref([]);

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
})

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

    const url = route('transaction.getTransactionListingData', params);

    try {
        window.location.href = url;
    } catch (e) {
        console.error('Error occurred during export:', e);
    } finally {
        isLoading.value = false;
        exportStatus.value = false;
    }
};

const copyToClipboard = (text) => {
    const textToCopy = text;

    const textArea = document.createElement('textarea');
    document.body.appendChild(textArea);

    textArea.value = textToCopy;
    textArea.select();

    try {
        const successful = document.execCommand('copy');

    } catch (err) {
        console.error('Copy to clipboard failed:', err);
    }

    document.body.removeChild(textArea);
}
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
        <template v-if="transactions?.length > 0">
            <Column
                field="created_at"
                sortable
                class="hidden md:table-cell w-[15%]"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.requested_on') }}</span>
                </template>
                <template #body="{data}">
                    {{ dayjs(data.created_at).format('YYYY/MM/DD') }}
                    <div class="text-xs text-gray-500">
                        {{ dayjs(data.created_at).format('HH:mm:ss') }}
                    </div>
                </template>
            </Column>
            <Column
                field="transaction_number"
                sortable
                class="hidden md:table-cell"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.id') }}</span>
                </template>
                <template #body="slotProps">
                    <span class="break-all">{{ slotProps.data?.transaction_number }}</span>
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
                            <template v-if="getProfilePhoto(data?.user)">
                                <img :src="getProfilePhoto(data?.user)" alt="profile_photo" class="w-7 h-7 object-cover">
                            </template>
                            <template v-else>
                                <DefaultProfilePhoto />
                            </template>
                        </div>
                        <div class="grid grid-cols-1 items-start max-w-full">
                            <div class="font-medium max-w-full truncate">
                                {{ data?.user.name }}
                            </div>
                            <div class="text-gray-500 text-xs max-w-full truncate">
                                {{ data?.user.email }}
                            </div>
                        </div>
                    </div>
                </template>
            </Column>
            <Column
                field="to_meta_login"
                sortable
                class="hidden md:table-cell"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.account') }}</span>
                </template>
                <template #body="{data}">
                    <span class="break-all">{{ data?.to_meta_login }}</span>
                </template>
            </Column>
            <Column
                field="account_type"
                class="hidden md:table-cell"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.account_type') }}</span>
                </template>
                <template #body="{data}">
                    <div v-if="data.to_login" class="flex gap-2 items-center">
                        <Tag
                            :severity="data?.to_login?.account_type.trading_platform.slug === 'mt4' ? 'secondary' : 'info'"
                            class="uppercase"
                            :value="data?.to_login?.account_type.trading_platform.slug"
                        />
                        <div
                            class="break-all flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded w-fit text-nowrap"
                            :style="{
                            backgroundColor: formatRgbaColor(data?.to_login?.account_type.color, 0.15),
                            color: `#${data?.to_login?.account_type.color}`,
                        }"
                        >
                            {{ data?.to_login?.account_type.account_group }}
                        </div>
                    </div>
                    <div v-else>-</div>
                </template>
            </Column>
            <Column
                field="transaction_amount"
                sortable
                class="hidden md:table-cell"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.amount') }} ($)</span>
                </template>
                <template #body="{data}">
                    <span class="break-all">{{ formatAmount(data?.transaction_amount ?? 0) }}</span>
                </template>
            </Column>
            <Column
                field="status"
                class="hidden md:table-cell"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.status') }} </span>
                </template>
                <template #body="{data}">
                    <StatusBadge
                        class="w-fit text-nowrap"
                        :variant="data.status"
                        :value="$t(`public.${data.status}`)"
                    />
                </template>
            </Column>

            <Column class="md:hidden">
                <template #body="{data}">
                    <div class="flex items-center gap-2 self-stretch">
                        <div class="flex flex-col items-start w-full">
                            <span class="text-sm text-gray-950 font-semibold">{{ data?.transaction_number}}</span>
                            <div class="flex gap-1 items-center text-xs">
                                <div class="text-gray-400 font-medium"> {{ dayjs(data?.created_at).format('YYYY/MM/DD HH:mm:ss') }}</div>
                                <span>|</span>
                                <div class="text-gray-700 font-medium"> {{ data.to_login ? data.to_meta_login : '-' }}</div>
                            </div>
                        </div>

                        <div
                            :class="[
                                'font-semibold',
                                {
                                    'text-success-500': data.status === 'successful',
                                    'text-error-500': data.status === 'failed',
                                    'text-blue-500': data.status === 'processing',
                                }
                            ]"
                        >
                            ${{ formatAmount(data.amount) }}
                        </div>
                    </div>
                </template>
            </Column>
        </template>
    </DataTable>

    <OverlayPanel ref="op">
        <div class="flex flex-col gap-5 w-60 py-5 px-4">
            <!-- Filter date -->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_date') }}
                </div>
                <div class="relative w-full">
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
                                v-model="filters['trading_platform'].value"
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
            <div v-if="filters['trading_platform'].value !== null" class="flex flex-col gap-2 items-center self-stretch">
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
                    :disabled="filters['trading_platform'].value === null"
                    :loading="loadingAccountTypes"
                >
                    <template #option="{ option }">
                        <div class="flex items-center gap-2">
                            <span>{{ option.name }}</span>
                        </div>
                    </template>

                    <template #value>
                        <div v-if="filters['account_type'].value?.length === 1">
                            <span>{{ filters['account_type'].value[0].name }}</span>
                        </div>
                        <span v-else-if="filters['account_type'].value?.length > 1">
                            {{ filters['account_type'].value?.length }} {{ $t('public.account_types_selected') }}
                        </span>
                        <span v-else class="text-gray-400">
                            {{ $t('public.account_type_placeholder') }}
                        </span>
                    </template>
                </MultiSelect>
            </div>

            <!-- Filter status -->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_status') }}
                </div>
                <div class="flex flex-col self-stretch">
                    <div
                        v-for="status in ['successful', 'failed', 'processing']"
                        class="flex items-center gap-2 text-sm text-gray-950"
                    >
                        <div class="flex justify-center items-center rounded-full grow-0 shrink-0 hover:bg-gray-100">
                            <RadioButton
                                v-model="filters['status'].value"
                                :inputId="`status_${status}`"
                                :value="status"
                                class="w-4 h-4"
                            />
                        </div>
                        <label :for="`status_${status}`">{{ $t(`public.${status}`) }}</label>
                    </div>
                </div>
            </div>

            <!-- Filter role -->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_role') }}
                </div>
                <div class="flex flex-col self-stretch">
                    <div
                        v-for="role in ['ib', 'member']"
                        class="flex items-center gap-2 text-sm text-gray-950"
                    >
                        <div class="flex justify-center items-center rounded-full grow-0 shrink-0 hover:bg-gray-100">
                            <RadioButton
                                v-model="filters['role'].value"
                                :inputId="`role_${role}`"
                                :value="role"
                                class="w-4 h-4"
                            />
                        </div>
                        <label :for="`role_${role}`">{{ $t(`public.${role}`) }}</label>
                    </div>
                </div>
            </div>

            <!-- Filter payment gateways -->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_payment_platform') }}
                </div>
                <Dropdown
                    v-model="filters['payment_platform'].value"
                    :options="paymentGateways"
                    filter
                    :filterFields="['name']"
                    optionLabel="name"
                    :placeholder="$t('public.select_payment_platform')"
                    class="w-full"
                    scroll-height="236px"
                    :loading="loadingPaymentGateways"
                >
                    <template #option="{option}">
                        <div class="flex items-center gap-2">
                            {{ option.name }}
                            <Tag
                                severity="secondary"
                                :value="$t(`public.${option.platform}`)"
                                class="text-xxs"
                            />
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

            <!-- Filter Upline-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_upline') }}
                </div>
                <Dropdown
                    v-model="filters['upline'].value"
                    :options="users"
                    filter
                    :filterFields="['name', 'email', 'id_number']"
                    optionLabel="name"
                    :placeholder="$t('public.filter_upline')"
                    class="w-full"
                    scroll-height="236px"
                    :loading="loadingUsers"
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
        :header="$t(`public.${selectedType}_details`)"
        class="dialog-xs md:dialog-md"
    >
        <div class="flex flex-col justify-center items-start pb-4 gap-3 self-stretch border-b border-gray-200 md:flex-row md:pt-4 md:justify-between">
            <!-- below md -->
            <span class="md:hidden self-stretch text-gray-950 text-xl font-semibold">${{ formatAmount(data.transaction_amount ?? 0) }}</span>
            <div class="flex items-center gap-3 self-stretch">
                <div class="w-9 h-9 rounded-full overflow-hidden grow-0 shrink-0">
                    <template v-if="getProfilePhoto(data.user)">
                        <img :src="getProfilePhoto(data.user)" alt="profile_photo" class="w-7 h-7 object-cover">
                    </template>
                    <template v-else>
                        <DefaultProfilePhoto />
                    </template>
                </div>
                <div class="flex flex-col items-start flex-grow">
                    <span class="self-stretch overflow-hidden text-gray-950 text-ellipsis text-sm font-medium">{{ data.user.name }}</span>
                    <span class="self-stretch overflow-hidden text-gray-500 text-ellipsis text-xs">{{ data?.user.email }}</span>
                </div>
            </div>
            <!-- above md -->
            <span class="hidden md:block w-[180px] text-gray-950 text-right text-xl font-semibold">${{ formatAmount(data.transaction_amount ?? 0) }}</span>
        </div>

        <div class="flex flex-col items-center py-4 gap-3 self-stretch border-b border-gray-200">
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.transaction_id') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ data.transaction_number }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.requested_date') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ dayjs(data.created_at).format('YYYY/MM/DD HH:mm:ss') }}</span>
            </div>
            <div v-if="data.status !== 'processing'" class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.transaction_date') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ dayjs(data.approved_at).format('YYYY/MM/DD HH:mm:ss') }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.account') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ data.to_meta_login }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.account_type') }}</span>
                <div v-if="data.to_login" class="flex gap-2 items-center">
                    <Tag
                        :severity="data.to_login.account_type.trading_platform.slug === 'mt4' ? 'secondary' : 'info'"
                        class="uppercase"
                        :value="data.to_login.account_type.trading_platform.slug"
                    />
                    <div
                        class="break-all flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded w-fit text-nowrap"
                        :style="{
                            backgroundColor: formatRgbaColor(data?.to_login?.account_type.color, 0.15),
                            color: `#${data?.to_login?.account_type.color}`,
                        }"
                    >
                        {{ data?.to_login?.account_type.account_group }}
                    </div>
                </div>
                <div v-else class="text-gray-950">-</div>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.status') }}</span>
                <StatusBadge :variant="data.status" :value="$t('public.' + data.status)"/>
            </div>
        </div>

        <div class="flex flex-col items-center py-4 gap-3 self-stretch border-b border-gray-200">
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="w-full md:max-w-[140px] text-gray-500 text-xs">{{ $t('public.platform') }}</span>
                <span class="w-full text-gray-950 text-sm font-medium">{{ data.payment_gateway.name ?? 'Payme' }}</span>
            </div>
            <div v-if="data.status !== 'processing' && data.payment_platform_name"  class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:min-w-[140px] text-gray-500 text-xs">{{ $t('public.bank') }}</span>
                <div class="flex justify-center items-center self-stretch">
                    <span class="flex-grow overflow-hidden text-gray-950 text-ellipsis text-sm font-medium break-words">{{ data.payment_platform_name ?? '-' }} <span v-if="data.bank_code" class="text-gray-500">({{ data.bank_code ?? '-' }})</span></span>
                </div>
            </div>
            <div v-if="data.status !== 'processing' && data.to_wallet_address"  class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:min-w-[140px] text-gray-500 text-xs">{{ data.payment_gateway.platform === 'bank' ? $t('public.account_no') : $t('public.receiving_address') }}</span>
                <div class="flex justify-center items-center self-stretch select-none hover:cursor-pointer" @click="copyToClipboard(data.to_wallet_address)">
                    <span class="flex-grow overflow-hidden text-gray-950 text-ellipsis text-sm font-medium break-words">{{ data.to_wallet_address ?? '-' }}</span>
                </div>
            </div>
        </div>

        <div v-if="data.status !== 'processing' && data.payment_platform === 'bank'" class="flex flex-col items-center py-4 gap-3 self-stretch border-b border-gray-200">
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="w-full md:max-w-[140px] text-gray-500 text-xs">{{ $t('public.bank_name') }}</span>
                <span class="w-full text-gray-950 text-sm font-medium">{{ data.payment_platform_name || '-' }}
                    <span class="text-xs text-gray-500">
                        {{ data.bank_code ? ` (${data.bank_code})` : '' }}
                    </span>
                </span>
            </div>

            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="w-full md:max-w-[140px] text-gray-500 text-xs">{{ data.payment_account_type === 'card' ? $t('public.card_name') : $t('public.account_name') }}</span>
                <span class="w-full text-gray-950 text-sm font-medium">{{ data.payment_account_name  ?? '-' }}</span>
            </div>
        </div>

        <div class="flex flex-col items-center py-4 gap-3 self-stretch">
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.remarks') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ data.remarks ?? '-' }}</span>
            </div>
        </div>
    </Dialog>
</template>
