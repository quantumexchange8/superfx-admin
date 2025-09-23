<script setup>
import Column from "primevue/column";
import Loader from "@/Components/Loader.vue";
import DataTable from "primevue/datatable";
import Tag from "primevue/tag";
import Empty from "@/Components/Empty.vue";
import {onMounted, ref} from "vue";
import PaymentPlatformAction from "@/Pages/Settings/PaymentPlatform/PaymentPlatformAction.vue";

const paymentPlatforms = ref([]);
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

            const url = route('system.getPaymentPlatforms', params);
            const response = await fetch(url);
            const results = await response.json();

            paymentPlatforms.value = results?.data?.data;

            totalRecords.value = results?.data?.total;
            isLoading.value = false;

        }, 100);
    }  catch (e) {
        paymentPlatforms.value = [];
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

const updatePayment = (updatedPayment) => {
    const index = paymentPlatforms.value.findIndex(payment => payment.id === updatedPayment.id);

    if (index !== -1) {
        paymentPlatforms.value[index] = updatedPayment;
    }

    // Apply same sort as backend:
    paymentPlatforms.value.sort((a, b) => {
        // active first
        if (a.status === 'active' && b.status !== 'active') return -1;
        if (b.status === 'active' && a.status !== 'active') return 1;

        // null positions last
        if (a.position == null && b.position != null) return 1;
        if (b.position == null && a.position != null) return -1;

        // ascending by position
        if (a.position !== b.position) return a.position - b.position;

        // descending by created_at
        return new Date(b.created_at) - new Date(a.created_at);
    });
};
</script>

<template>
    <div class="py-6 px-4 md:p-6 flex flex-col justify-center items-center gap-6 self-stretch rounded-2xl border border-solid border-gray-200 bg-white shadow-table">
        <div class="text-sm font-bold w-full">
            {{ $t('public.payment_platform') }}
        </div>

        <DataTable
            v-model:first="first"
            :value="paymentPlatforms"
            lazy
            removableSort
            :loading="isLoading"
            :paginator="paymentPlatforms?.length > 0"
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

            <template v-if="paymentPlatforms?.length > 0">
                <Column field="name" sortable>
                    <template #header>
                        <span>{{ $t('public.name') }}</span>
                    </template>
                    <template #body="{data}">
                        {{ data.name }}
                    </template>
                </Column>

                <Column field="platform">
                    <template #header>
                        <span>{{ $t('public.platform') }}</span>
                    </template>
                    <template #body="{data}">
                        <Tag
                            :severity="data.platform === 'bank' ? 'info' : 'secondary'"
                            :value="data.platform"
                            class="text-xxs uppercase"
                        />
                    </template>
                </Column>

                <Column field="status">
                    <template #header>
                        <span>{{ $t('public.status') }}</span>
                    </template>
                    <template #body="{data}">
                        <Tag
                            :severity="data.status === 'active' ? 'success' : 'danger'"
                            :value="data.status"
                            class="text-xxs uppercase"
                        />
                    </template>
                </Column>

                <Column field="action" style="width: 15%">
                    <template #body="slotProps">
                        <PaymentPlatformAction
                            :paymentGateway="slotProps.data"
                            @updated:payment="updatePayment"
                        />
                    </template>
                </Column>
            </template>
        </DataTable>
    </div>
</template>
