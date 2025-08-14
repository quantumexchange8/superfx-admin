<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/Components/Button.vue';
import { IconRefresh } from '@tabler/icons-vue';
import DataTable from 'primevue/datatable';
import { onMounted, ref, watch, watchEffect } from 'vue';
import Column from 'primevue/column';
import Empty from '@/Components/Empty.vue';
import Loader from "@/Components/Loader.vue";
import { usePage } from '@inertiajs/vue3';
import AddProfile from '@/Pages/MarkupProfile/Partials/AddProfile.vue';
import MarkupProfileAction from '@/Pages/MarkupProfile/Partials/MarkupProfileAction.vue';

const props = defineProps({
    accountTypes: Array,
    users: Array,
})

const markupProfiles = ref();
const totalRecords = ref(0);
const loading = ref(false);
const dt = ref(null);
const first = ref(0);

const lazyParams = ref({});
const abortController = ref(null);

// Load Account Types with Pagination + Sorting + Abort
const loadLazyData = (event = {}) => {
    loading.value = true;

    // Abort previous request if still pending
    if (abortController.value) {
        abortController.value.abort();
    }

    abortController.value = new AbortController();

    lazyParams.value = {
        ...lazyParams.value,
        first: event.first ?? first.value,
    };

    setTimeout(async () => {
        try {
            const params = {
                page: JSON.stringify((event.page ?? 0) + 1),
                sortField: event.sortField,
                sortOrder: event.sortOrder,
                lazyEvent: JSON.stringify(lazyParams.value),
            };

            const url = route('markup_profile.getMarkupProfiles', params);

            const response = await fetch(url, {
                signal: abortController.value.signal,
            });

            const results = await response.json();

            markupProfiles.value = results?.data?.data ?? [];
            totalRecords.value = results?.data?.total ?? 0;
        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('Request aborted');
            } else {
                console.error('Error getting markup profiles:', error);
                markupProfiles.value = [];
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
    <AuthenticatedLayout :title="$t('public.markup_profile')">
        <div class="flex flex-col items-center gap-5 md:gap-8">
            <div class="flex justify-end items-center self-stretch">
                <AddProfile
                    :accountTypes="props.accountTypes"
                    :users="props.users"
                />
                <!-- <Button
                    variant="primary-flat"
                    type="button"
                    class="w-full md:w-auto"
                    :href="route('accountType.syncAccountTypes')"
                >
                    <IconRefresh size="20" stroke-width="1.25" color="#FFF" />
                    {{ $t('public.synchronise') }}
                </Button> -->
            </div>

            <div
                class="py-6 px-4 md:p-6 flex flex-col justify-center items-center gap-6 self-stretch rounded-2xl border border-solid border-gray-200 bg-white shadow-table">
                <DataTable
                    v-model:first="first"
                    :value="markupProfiles"
                    lazy
                    removableSort
                    :loading="loading"
                    @row-click="rowClicked($event.data)"
                    :paginator="markupProfiles?.length > 0"
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
                        <Empty :title="$t('public.no_profile_header')"
                               :message="$t('public.no_profile_caption')"/>
                    </template>

                    <template #loading>
                        <div class="flex flex-col gap-2 items-center justify-center">
                            <Loader />
                            <span class="text-sm text-gray-700">{{ $t('public.loading_profile') }}</span>
                        </div>
                    </template>

                    <template v-if="markupProfiles?.length > 0">
                        <Column field="name" sortable class="w-1/2 md:w-1/4 max-w-0 truncate">
                            <template #header>
                                <span class="w-10 truncate sm:w-auto">{{ $t('public.name') }}</span>
                            </template>
                            <template #body="slotProps">
                                <span class="px-2.5 md:px-0">{{ slotProps.data.name }}</span>
                            </template>
                        </Column>
                        <Column field="account_types" class="hidden md:table-cell md:w-2/5 max-w-0">
                            <template #header>
                                <span>{{ $t('public.account_type') }}</span>
                            </template>
                            <template #body="slotProps">
                                {{ slotProps.data.account_types?.length ? slotProps.data.account_types.map(type => type.name).join(', ') : $t('public.no_account_type') }}
                            </template>
                        </Column>
                        <Column field="total_account" sortable bodyClass="text-center md:text-left" class="hidden md:table-cell md:w-1/5 max-w-0">
                            <template #header>
                                <span class="w-14 truncate sm:w-auto">{{ $t('public.total_account') }}</span>
                            </template>
                            <template #body="slotProps">
                                {{ slotProps.data.total_account }}
                            </template>
                        </Column>
                        <Column field="action" class="md:w-[15%] max-w-0">
                            <template #body="slotProps">
                                <div class="flex justify-end items-center gap-2 flex-1">
                                    <MarkupProfileAction 
                                        :profile="slotProps.data" 
                                        :accountTypes="props.accountTypes"
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
    </AuthenticatedLayout>
</template>
