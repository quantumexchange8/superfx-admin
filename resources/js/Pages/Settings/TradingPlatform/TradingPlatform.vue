<script setup>
import Column from "primevue/column";
import Loader from "@/Components/Loader.vue";
import DataTable from "primevue/datatable";
import Empty from "@/Components/Empty.vue";
import {onMounted, ref} from "vue";
import TradingPlatformAction from "@/Pages/Settings/TradingPlatform/TradingPlatformAction.vue";

const tradingPlatforms = ref([]);
const totalRecords = ref(0);
const isLoading = ref(false);
const dt = ref(null);
const first = ref(0);

const lazyParams = ref({});

const loadLazyData = (event) => {
    isLoading.value = true;

    lazyParams.value = { ...lazyParams.value, first: event?.first || first.value };
    try {
        setTimeout(async () => {
            const params = {
                page: JSON.stringify(event?.page + 1),
                sortField: event?.sortField,
                sortOrder: event?.sortOrder,
                include: [],
                lazyEvent: JSON.stringify(lazyParams.value)
            };

            const url = route('system.getTradingPlatforms', params);
            const response = await fetch(url);
            const results = await response.json();

            tradingPlatforms.value = results?.data?.data;

            totalRecords.value = results?.data?.total;
            isLoading.value = false;

        }, 100);
    }  catch (e) {
        tradingPlatforms.value = [];
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

//filter toggle
const op = ref();
const toggle = (event) => {
    op.value.toggle(event);
};

onMounted(() => {
    lazyParams.value = {
        first: dt.value.first,
        rows: dt.value.rows,
        sortField: null,
        sortOrder: null,
    };
    loadLazyData();
});
</script>

<template>
    <div class="py-6 px-4 md:p-6 flex flex-col justify-center items-center gap-6 self-stretch rounded-2xl border border-solid border-gray-200 bg-white shadow-table">
        <div class="text-sm font-bold w-full">
            {{ $t('public.trading_platform') }}
        </div>

        <DataTable
            v-model:first="first"
            :value="tradingPlatforms"
            lazy
            removableSort
            :loading="isLoading"
            :paginator="tradingPlatforms?.length > 0"
            :rows="10"
            :rowsPerPageOptions="[10, 20, 50, 100]"
            paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
            :currentPageReportTemplate="$t('public.paginator_caption')"
            ref="dt"
            dataKey="id"
            :totalRecords="totalRecords"
            @page="onPage($event)"
            @sort="onSort($event)"
        >
            <template #empty>
                <Empty
                    :title="$t('public.no_settings_header')"
                    :message="$t('public.no_settings_caption')"
                />
            </template>

            <template #loading>
                <div class="flex flex-col gap-2 items-center justify-center">
                    <Loader />
                    <span class="text-sm text-gray-700">{{ $t('public.loading_settings') }}</span>
                </div>
            </template>

            <template v-if="tradingPlatforms?.length > 0">
                <Column field="platform_name" sortable>
                    <template #header>
                        <span>{{ $t('public.name') }}</span>
                    </template>
                    <template #body="{data}">
                        {{ data.platform_name }}
                    </template>
                </Column>

                <Column field="action" style="width: 15%">
                    <template #body="slotProps">
                        <div class="py-2 px-3 flex justify-center items-center gap-2 flex-1">
                            <TradingPlatformAction
                                :tradingPlatform="slotProps.data"
                            />
                        </div>
                    </template>
                </Column>
            </template>
        </DataTable>
    </div>
</template>
