<script setup>
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import {ref, watch, onMounted, computed} from "vue";
import {FilterMatchMode} from "primevue/api";
import Loader from "@/Components/Loader.vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import Calendar from "primevue/calendar";
import Dropdown from "primevue/dropdown";
import MultiSelect from "primevue/multiselect";
import Empty from "@/Components/Empty.vue";
import dayjs from "dayjs";
import AccountTableActions from "@/Pages/Member/Account/Partials/AccountTableActions.vue";
import Badge from "@/Components/Badge.vue";
import InputText from "primevue/inputtext";
import Button from "@/Components/Button.vue";
import {IconX, IconCircleXFilled, IconSearch} from "@tabler/icons-vue";
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
    start_delete_date: { value: null, matchMode: FilterMatchMode.EQUALS },
    end_delete_date: { value: null, matchMode: FilterMatchMode.EQUALS },
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

const selectedDate = ref([]);

const clearDate = () => {
    selectedDate.value = [];
}

watch(selectedDate, (newDateRange) => {
    if (Array.isArray(newDateRange)) {
        const [startDate, endDate] = newDateRange;
        filters.value['start_delete_date'].value = startDate;
        filters.value['end_delete_date'].value = endDate;

        if (startDate !== null && endDate !== null) {
            loadLazyData();
        }
    } else {
        console.warn('Invalid date range format:', newDateRange);
    }
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
        :globalFilterFields="['first_name', 'last_name', 'email', 'username', 'meta_login']"
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
                field="deleted_at"
                sortable
                class="hidden md:table-cell w-[15%]"
            >
                <template #header>
                    <span class="hidden md:block truncate">{{ $t('public.deleted_time') }}</span>
                </template>
                <template #body="{data}">
                    {{ dayjs(data.deleted_at).format('YYYY-MM-DD') }}
                    <div class="text-xs text-gray-500">
                        {{ dayjs(data.deleted_at).format('HH:mm:ss') }}
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
                            <div class="flex gap-2 items-center truncate">
                                <div class="font-medium max-w-full truncate">
                                    {{ data.users.name }}
                                </div>
                                <Tag
                                    v-if="data.users.deleted_at"
                                    :value="$t('public.deleted')"
                                    severity="danger"
                                    class="text-xxs"
                                />
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
                    <span class="break-all">{{ formatAmount(slotProps.data?.balance) }}</span>
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
                    <span class="break-all">{{ slotProps.data?.credit ? formatAmount(slotProps.data?.credit) : formatAmount(0) }}</span>
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
                            severity="secondary"
                            class="uppercase"
                            :value="slotProps.data.account_type.trading_platform.slug"
                        />
                        <div
                            class="break-all flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded w-fit"
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
            <Column class="md:hidden">
                <template #body="slotProps">
                    <div class="flex items-center gap-2 self-stretch">
                        <div class="flex flex-col items-start w-full">
                            <span class="text-sm text-gray-950 font-semibold">{{ slotProps.data?.meta_login }}</span>
                            <div class="text-xs">
                                <span class="text-gray-500">{{ $t('public.deleted_time') }}</span> <span class="text-gray-700 font-medium"> {{ dayjs(slotProps.data?.deleted_at).format('YYYY/MM/DD HH:mm:ss') }}</span>
                            </div>
                        </div>
                        <Tag
                            severity="secondary"
                            class="uppercase"
                            :value="slotProps.data.account_type.trading_platform.slug"
                        />
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
                <div class="flex gap-2 items-center">
                    <Tag
                        severity="secondary"
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
                <span class="self-stretch md:min-w-[140px] text-gray-500 text-xs">{{ $t('public.deleted_time') }}</span>
                <div class="flex flex-col items-start">
                    <span class="self-stretch text-gray-950 text-sm font-medium">{{ dayjs(data?.deleted_at).format('YYYY/MM/DD HH:mm:ss') }}</span>
                </div>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:min-w-[140px] text-gray-500 text-xs">{{ $t('public.last_logged_in') }}</span>
                <div class="flex flex-col items-start">
                    <span class="self-stretch text-gray-950 text-sm font-medium">{{ dayjs(data?.last_access).format('YYYY/MM/DD HH:mm:ss') }}</span>
                    <Tag
                        :severity="dayjs().diff(dayjs(data?.last_access), 'day') === 0 ? 'secondary' : 'info'"
                        :value="`${dayjs().diff(dayjs(data?.last_access), 'day')} ${$t('public.days')}`"
                    />
                </div>
            </div>
        </div>
    </Dialog>
</template>
