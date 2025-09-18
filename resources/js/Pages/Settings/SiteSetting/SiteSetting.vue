<script setup>
import DataTable from 'primevue/datatable';
import { onMounted, ref, watch } from 'vue';
import Column from 'primevue/column';
import Empty from '@/Components/Empty.vue';
import Loader from "@/Components/Loader.vue";
import { usePage } from '@inertiajs/vue3';
import SettingsActions from '@/Pages/Settings/Partials/SettingsActions.vue';

const settings = ref([]);
const totalRecords = ref(0);
const loading = ref(false);
const dt = ref(null);
const first = ref(0);

const lazyParams = ref({});

const loadLazyData = (event = {}) => {
    loading.value = true;

    lazyParams.value = { ...lazyParams.value, first: event.first ?? first.value };

    setTimeout(async () => {
        try {
            const params = {
                page: JSON.stringify((event.page ?? 0) + 1),
                sortField: event.sortField,
                sortOrder: event.sortOrder,
                lazyEvent: JSON.stringify(lazyParams.value),
            };

            const url = route('system.getSiteSettings', params);
            const response = await fetch(url);
            const results = await response.json();

            settings.value = results?.data?.data ?? [];
            totalRecords.value = results?.data?.total ?? 0;
        } catch (error) {
            console.error('Error fetching site settings:', error);
            settings.value = [];
            totalRecords.value = 0;
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

onMounted(() => {
    lazyParams.value = {
        first: dt.value?.first ?? 0,
        rows: dt.value?.rows ?? 10,
        sortField: null,
        sortOrder: null,
    };
    loadLazyData();
});

watch(() => usePage().props.toast, (toast) => {
    if (toast !== null) {
        first.value = 0;
        loadLazyData();
    }
});
</script>

<template>
    <div class="py-6 px-4 md:p-6 flex flex-col justify-center items-center gap-6 self-stretch rounded-2xl border border-solid border-gray-200 bg-white shadow-table">
        <div class="text-sm font-bold w-full">
            {{ $t('public.site_setting') }}
        </div>
        <DataTable
            v-model:first="first"
            :value="settings"
            lazy
            removableSort
            :loading="loading"
            :paginator="settings?.length > 0"
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

            <template v-if="settings?.length > 0">
                <Column field="name" sortable>
                    <template #header>
                        <span>{{ $t('public.name') }}</span>
                    </template>
                    <template #body="slotProps">
                        {{ $t('public.' + slotProps.data.name) }}
                    </template>
                </Column>

                <Column field="action" style="width: 15%">
                    <template #body="slotProps">
                        <div class="py-2 px-3 flex justify-center items-center gap-2 flex-1">
                            <SettingsActions
                                :setting="slotProps.data"
                                :loading="loading"
                            />
                        </div>
                    </template>
                </Column>

            </template>
        </DataTable>
    </div>
</template>
