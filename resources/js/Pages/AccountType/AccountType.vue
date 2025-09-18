<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/Components/Button.vue';
import { IconSearch, IconXboxX, IconAdjustments } from '@tabler/icons-vue';
import DataTable from 'primevue/datatable';
import {computed, onMounted, ref, watch} from 'vue';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Empty from '@/Components/Empty.vue';
import Loader from "@/Components/Loader.vue";
import { usePage } from '@inertiajs/vue3';
import AccountTypeAction from '@/Pages/AccountType/Partials/AccountTypeAction.vue';
import {FilterMatchMode} from "primevue/api";
import debounce from "lodash/debounce.js";
import Badge from "@/Components/Badge.vue";
import InputText from "primevue/inputtext";
import OverlayPanel from "primevue/overlaypanel";
import Checkbox from "primevue/checkbox";
import SyncAccountType from "@/Pages/AccountType/SyncAccountType.vue";

const props = defineProps({
    leverages: Array,
    users: Array,
    tradingPlatforms: Array,
});

const accountTypes = ref([]);
const totalRecords = ref(0);
const loading = ref(false);
const dt = ref(null);
const first = ref(0);

const lazyParams = ref({});
const abortController = ref(null);

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    status: { value: null, matchMode: FilterMatchMode.EQUALS },
    platform: { value: null, matchMode: FilterMatchMode.EQUALS },
    category: { value: null, matchMode: FilterMatchMode.EQUALS },
});

// Load Account Types with Pagination + Sorting + Abort
const loadLazyData = (event = {}) => {
    loading.value = true;

    // Abort previous request if still pending
    if (abortController.value) {
        abortController.value.abort();
    }

    abortController.value = new AbortController();

    lazyParams.value = { ...lazyParams.value, first: event?.first || first.value };
    lazyParams.value.filters = filters.value;

    setTimeout(async () => {
        try {
            const params = {
                page: JSON.stringify((event.page ?? 0) + 1),
                sortField: event.sortField,
                sortOrder: event.sortOrder,
                lazyEvent: JSON.stringify(lazyParams.value),
            };

            const url = route('accountType.getAccountTypes', params);

            const response = await fetch(url, {
                signal: abortController.value.signal,
            });

            const results = await response.json();

            accountTypes.value = results?.data?.data ?? [];
            totalRecords.value = results?.data?.total ?? 0;
        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('Request aborted');
            } else {
                console.error('Error fetching account types:', error);
                accountTypes.value = [];
                totalRecords.value = 0;
            }
        } finally {
            loading.value = false;
        }
    }, 100);
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
    lazyParams.value.filters = filters.value ;
    loadLazyData(event);
};

const op = ref();
const filterCount = computed(() => {
    return Object.entries(filters.value)
        .filter(([key, filter]) =>
            key !== 'global' &&
            filter?.value !== null &&
            filter?.value !== '' &&
            filter?.value !== undefined
        ).length;
});

const toggle = (event) => {
    op.value.toggle(event);
}

onMounted(() => {
    lazyParams.value = {
        first: dt.value?.first ?? 0,
        rows: dt.value?.rows ?? 10,
        sortField: null,
        sortOrder: null,
        filters: filters.value
    };

    loadLazyData();
});

watch(
    filters.value['global'],
    debounce(() => {
        loadLazyData();
    }, 300)
);

watch([filters.value['status'], filters.value['platform'], filters.value['category']], () => {
    loadLazyData()
});

const clearAll = () => {
    filters.value['status'].value = null;
    filters.value['platform'].value = null;
    filters.value['category'].value = null;
};

const clearFilterGlobal = () => {
    filters.value['global'].value = null;
}

watch(() => usePage().props.toast, (toast) => {
    if (toast !== null) {
        first.value = 0;
        loadLazyData();
    }
});

const visibleDetails = ref(false);
const selected_row = ref();
const rowClicked = (data) => {
    if (window.innerWidth < 768) {
        visibleDetails.value = true;
        selected_row.value = data;
    }
}
</script>

<template>
    <AuthenticatedLayout :title="$t('public.account_type')">
        <div class="flex flex-col items-center gap-5 md:gap-8">
            <div class="flex justify-end items-center self-stretch">
                <SyncAccountType
                    :tradingPlatforms="tradingPlatforms"
                />
            </div>

            <div
                class="py-6 px-4 md:p-6 flex flex-col justify-center items-center gap-6 self-stretch rounded-2xl border border-solid border-gray-200 bg-white shadow-table">
                <DataTable
                    v-model:first="first"
                    :value="accountTypes"
                    lazy
                    removableSort
                    :loading="loading"
                    @row-click="rowClicked($event.data)"
                    :paginator="accountTypes?.length > 0"
                    :rows="10"
                    :rowsPerPageOptions="[10, 20, 50, 100]"
                    paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
                    :currentPageReportTemplate="$t('public.paginator_caption')"
                    ref="dt"
                    dataKey="id"
                    :totalRecords="totalRecords"
                    @page="onPage($event)"
                    @sort="onSort($event)"
                    @filter="onFilter($event)"
                >
                    <template #header>
                        <div class="flex flex-col md:flex-row gap-3 items-center self-stretch md:pb-6">
                            <div class="relative w-full md:w-60">
                                <div class="absolute top-2/4 -mt-[9px] left-4 text-surface-400">
                                    <IconSearch size="20" stroke-width="1.5"/>
                                </div>
                                <InputText
                                    v-model="filters['global'].value"
                                    :placeholder="$t('public.keyword_search')"
                                    class="font-normal pl-12 w-full md:w-60"
                                />
                                <div
                                    v-if="filters['global'].value !== null"
                                    class="absolute top-2/4 -mt-2 right-4 text-surface-300 hover:text-surface-400 dark:text-surface-500 dark:hover:text-surface-400 select-none cursor-pointer"
                                    @click="clearFilterGlobal"
                                >
                                    <IconXboxX size="16"/>
                                </div>
                            </div>
                            <div class="flex justify-between items-center w-full gap-3">
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
                        </div>
                    </template>

                    <template #empty>
                        <Empty :title="$t('public.no_account_type_header')"
                               :message="$t('public.no_account_type_caption')"/>
                    </template>

                    <template #loading>
                        <div class="flex flex-col gap-2 items-center justify-center">
                            <Loader />
                            <span class="text-sm text-gray-700">{{ $t('public.loading_account_type') }}</span>
                        </div>
                    </template>

                    <template v-if="accountTypes?.length > 0">
                        <Column field="name" sortable class="w-1/2 md:w-1/4">
                            <template #header>
                                <span class="w-10 truncate sm:w-auto">{{ $t('public.name') }}</span>
                            </template>
                            <template #body="slotProps">
                                <span class="w-20 truncate inline-block sm:w-auto">{{ slotProps.data.name }}</span>
                            </template>
                        </Column>

                        <Column
                            field="trading_platform"
                            :header="$t('public.platform')"
                            class="text-nowrap"
                        >
                            <template #body="{data}">
                                <Tag
                                    severity="secondary"
                                    class="uppercase"
                                    :value="data.trading_platform.slug"
                                />
                            </template>
                        </Column>


                        <Column field="category" class="hidden md:table-cell">
                            <template #header>
                                <span>{{ $t('public.category') }}</span>
                            </template>
                            <template #body="slotProps">
                                <Tag
                                    :severity="slotProps.data.category === 'dollar' ? 'primary' : 'info'"
                                    :value="slotProps.data.category"
                                    class="uppercase"
                                />
                            </template>
                        </Column>

                        <Column field="max_acc" class="hidden md:table-cell">
                            <template #header>
                                <span>{{ $t('public.max_account') }}</span>
                            </template>
                            <template #body="slotProps">
                                {{ slotProps.data.maximum_account_number }}
                            </template>
                        </Column>

                        <Column field="total_account" sortable class="w-1/3 md:w-1/5" bodyClass="text-center md:text-left">
                            <template #header>
                                <span class="w-14 truncate sm:w-auto">{{ $t('public.total_account') }}</span>
                            </template>
                            <template #body="slotProps">
                                {{ slotProps.data.total_account }}
                            </template>
                        </Column>
                        <Column field="action">
                            <template #body="slotProps">
                                <div class="py-2 px-3 flex justify-center items-center gap-2 flex-1">
                                    <AccountTypeAction
                                        :accountType="slotProps.data"
                                        :leverages="props.leverages"
                                        :users="props.users"
                                        :loading="loading"
                                    />
                                </div>
                            </template>
                        </Column>
                    </template>
                </DataTable>
            </div>
        </div>

        <OverlayPanel ref="op">
            <div class="flex flex-col gap-5 w-60 py-5 px-4">
                <!-- Filter platform -->
                <div class="flex flex-col gap-2 items-center self-stretch">
                    <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                        {{ $t('public.filter_platform') }}
                    </div>
                    <div class="flex flex-col gap-1 self-stretch">
                        <div
                            v-for="platform in tradingPlatforms"
                            class="flex items-center gap-2"
                        >
                            <Checkbox
                                v-model="filters['platform'].value"
                                :inputId="platform.slug"
                                :value="platform.slug"
                            />
                            <label :for="platform.slug" class="text-sm font-semibold uppercase">{{ platform.slug }}</label>
                        </div>
                    </div>
                </div>

                <!-- Filter category -->
                <div class="flex flex-col gap-2 items-center self-stretch">
                    <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                        {{ $t('public.filter_category') }}
                    </div>
                    <div class="flex flex-col gap-1 self-stretch">
                        <div
                            v-for="acc_cat in ['dollar', 'cent']"
                            class="flex items-center gap-2"
                        >
                            <Checkbox
                                v-model="filters['category'].value"
                                :inputId="acc_cat"
                                :value="acc_cat"
                            />
                            <label :for="acc_cat" class="text-sm font-semibold uppercase">{{ acc_cat }}</label>
                        </div>
                    </div>
                </div>

                <!-- Filter category -->
                <div class="flex flex-col gap-2 items-center self-stretch">
                    <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                        {{ $t('public.filter_status') }}
                    </div>
                    <div class="flex flex-col gap-1 self-stretch">
                        <div
                            v-for="acc_status in ['active', 'inactive']"
                            class="flex items-center gap-2"
                        >
                            <Checkbox
                                v-model="filters['status'].value"
                                :inputId="acc_status"
                                :value="acc_status"
                            />
                            <label :for="acc_status" class="text-sm font-semibold uppercase">{{ acc_status }}</label>
                        </div>
                    </div>
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
    </AuthenticatedLayout>
</template>
