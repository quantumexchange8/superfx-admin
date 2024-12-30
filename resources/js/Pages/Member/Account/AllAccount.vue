<script setup>
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import {ref, watch, watchEffect} from "vue";
import {FilterMatchMode} from "primevue/api";
import Loader from "@/Components/Loader.vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import OverlayPanel from 'primevue/overlaypanel';
import Empty from "@/Components/Empty.vue";
import dayjs from "dayjs";
import AccountTableActions from "@/Pages/Member/Account/Partials/AccountTableActions.vue";
import Badge from "@/Components/Badge.vue";
import InputText from "primevue/inputtext";
import Button from "@/Components/Button.vue";
import {IconAdjustments, IconCircleXFilled, IconSearch} from "@tabler/icons-vue";
import RadioButton from "primevue/radiobutton";
import {transactionFormat} from "@/Composables/index.js";
import Dialog from "primevue/dialog";
import {usePage} from "@inertiajs/vue3";

// overlay panel
const op = ref();
const accounts = ref([]);
const loading = ref(false);
const filterCount = ref(0);
const selectedFilterLastLoggedIn = ref('');
const {formatAmount} = transactionFormat();
const visible = ref(false);

const getResults = async () => {
    loading.value = true;

    try {
        let url = '/member/getAccountListingData?account_listing=all';

        if (selectedFilterLastLoggedIn.value) {
            url += `&last_logged_in_days=${selectedFilterLastLoggedIn.value}`;
        }

        const response = await axios.get(url);
        accounts.value = response.data.accounts;
    } catch (error) {
        console.error('Error changing locale:', error);
    } finally {
        loading.value = false;
    }
};

getResults();

const clearFilterGlobal = () => {
    filters.value['global'].value = null;
}

const exportCSV = () => {
    dt.value.exportCSV();
};

const filters = ref({
    global: {value: null, matchMode: FilterMatchMode.CONTAINS},
    balance: {value: null, matchMode: FilterMatchMode.EQUALS},
});

watch(filters, () => {
    filterCount.value = Object.values(filters.value).filter(filter => filter.value !== null).length;
}, { deep: true });

watch([selectedFilterLastLoggedIn], () => {
    op.value.toggle(false);
    getResults();

    filterCount.value = Object.values(filters.value).filter(filter => filter.value !== null).length +
        (selectedFilterLastLoggedIn.value ? 1 : 0);
}, { deep: true });

const toggle = (event) => {
    op.value.toggle(event);
}

const data = ref({});
const openDialog = (rowData) => {
    visible.value = true;
    data.value = rowData;
}

const clearFilter = () => {
    op.value.toggle(false);

    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        balance: { value: null, matchMode: FilterMatchMode.EQUALS },
    };

    selectedFilterLastLoggedIn.value = '';
};

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults();
    }
});
</script>

<template>
    <DataTable
        v-model:filters="filters"
        :value="accounts"
        :paginator="accounts?.length > 0"
        removableSort
        :rows="10"
        :rowsPerPageOptions="[10, 20, 50, 100]"
        tableStyle="md:min-width: 50rem"
        paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
        :currentPageReportTemplate="$t('public.paginator_caption')"
        :globalFilterFields="['user_name', 'user_email', 'meta_login']"
        ref="dt"
        selectionMode="single"
        @row-click="(event) => openDialog(event.data)"
        :loading="loading"
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
                <Loader />
                <span class="text-sm text-gray-700">{{ $t('public.loading_accounts_data') }}</span>
            </div>
        </template>
        <template v-if="accounts?.length > 0">
            <Column
                field="last_login"
                sortable
                class="hidden md:table-cell"
            >
                <template #header>
                    <span class="hidden md:block max-w-[40px] md:max-w-[60px] lg:max-w-[100px] truncate">{{ $t('public.last_logged_in') }}</span>
                </template>
                <template #body="slotProps">
                    {{ dayjs(slotProps.data.last_login).format('YYYY/MM/DD HH:mm:ss') }}
                </template>
            </Column>
            <Column
                field="user_name"
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
                                <DefaultProfilePhoto />
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
                    <span class="hidden md:block max-w-[40px] lg:max-w-[100px] truncate">{{ $t('public.account') }}</span>
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
                    <span class="hidden md:block max-w-[40px] lg:max-w-[100px] truncate">{{ $t('public.balance') }} ($)</span>
                </template>
                <template #body="slotProps">
                    {{ formatAmount(slotProps.data.balance) }}
                </template>
            </Column>
            <Column
                field="equity"
                sortable
                class="hidden md:table-cell"
            >
                <template #header>
                    <span class="hidden md:block max-w-[40px] lg:max-w-[100px] truncate">{{ $t('public.equity') }} ($)</span>
                </template>
                <template #body="slotProps">
                    {{ formatAmount(slotProps.data.equity) }}
                </template>
            </Column>
            <Column
                field="action"
                header=""
                class="hidden md:table-cell"
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
                            <span class="text-sm text-gray-950 font-semibold">{{ slotProps.data.meta_login }}</span>
                            <div class="text-xs">
                                <span class="text-gray-500">{{ $t('public.last_logged_in') }}</span> <span class="text-gray-700 font-medium"> {{ dayjs(slotProps.data.last_login).format('YYYY/MM/DD HH:mm:ss') }}</span>
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
                                v-model="selectedFilterLastLoggedIn"
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
                                v-model="filters['balance'].value"
                                inputId="zero_balance"
                                value="0.00"
                                class="w-4 h-4"
                            />
                        </div>
                        <label for="zero_balance">{{ $t('public.zero_balance') }}</label>
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

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.account_details')"
        class="dialog-xs md:dialog-sm"
    >
        <div class="flex flex-col justify-center items-start pb-4 gap-3 self-stretch border-b border-gray-200 md:flex-row md:pt-4 md:justify-between">
            <div class="flex items-center gap-3 self-stretch">
                <div class="w-9 h-9 rounded-full overflow-hidden grow-0 shrink-0">
                    <div v-if="data.user_profile_photo">
                        <img :src="data.user_profile_photo" alt="Profile Photo" />
                    </div>
                    <div v-else>
                        <DefaultProfilePhoto />
                    </div>
                </div>
                <div class="flex flex-col items-start flex-grow">
                    <span class="self-stretch overflow-hidden text-gray-950 text-ellipsis text-sm font-medium">{{ data.user_name }}</span>
                    <span class="self-stretch overflow-hidden text-gray-500 text-ellipsis text-xs">{{ data.user_email }}</span>
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
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.equity') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">$ {{ formatAmount(data.equity) }}</span>
            </div>
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
                <span class="self-stretch md:min-w-[140px] text-gray-500 text-xs">{{ $t('public.last_logged_in') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ dayjs(data.last_login).format('YYYY/MM/DD HH:mm:ss') }} ({{ dayjs().diff(dayjs(data.last_login), 'day') }} {{ $t('public.days') }})</span>
            </div>
        </div>
    </Dialog>
</template>
