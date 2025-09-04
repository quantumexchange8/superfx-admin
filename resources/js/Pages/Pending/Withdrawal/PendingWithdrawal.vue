<script setup>
import {IconCircleXFilled, IconSearch, IconLoader} from "@tabler/icons-vue";
import Loader from "@/Components/Loader.vue";
import DataTable from "primevue/datatable";
import InputText from "primevue/inputtext";
import Column from "primevue/column";
import Button from "@/Components/Button.vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import {onMounted, ref, watch, watchEffect} from "vue";
import {FilterMatchMode} from "primevue/api";
import {usePage} from "@inertiajs/vue3";
import dayjs from 'dayjs'
import {transactionFormat} from "@/Composables/index.js";
import ColumnGroup from 'primevue/columngroup';
import Row from 'primevue/row';
import Dialog from "primevue/dialog";
import InputLabel from "@/Components/InputLabel.vue";
import Chip from "primevue/chip";
import Textarea from "primevue/textarea";
import Empty from "@/Components/Empty.vue";
import toast from "@/Composables/toast.js";
import debounce from "lodash/debounce.js";
import {emitter} from "@/Composables/useEventBus.js";

const isLoading = ref(false);
const dt = ref(null);
const pendingWithdrawals = ref([]);
const totalRecords = ref(0);
const first = ref(0);
const totalAmount = ref();
const {formatAmount, formatDateTime} = transactionFormat();

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
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

            const url = route('pending.getPendingWithdrawalData', params);
            const response = await fetch(url);
            const results = await response.json();

            pendingWithdrawals.value = results?.data?.data;
            totalRecords.value = results?.data?.total;
            totalAmount.value = results?.totalAmount;

            isLoading.value = false;
        }, 100);
    }  catch (e) {
        pendingWithdrawals.value = [];
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
const onFilter = (event) => {
    lazyParams.value.filters = filters.value ;
    loadLazyData(event);
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

watch(
    filters.value['global'],
    debounce(() => {
        loadLazyData();
    }, 300)
);

const exportCSV = () => {
    dt.value.exportCSV();
};

const clearFilterGlobal = () => {
    filters.value['global'].value = null;
}

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults();
    }
});

const visible = ref(false);
const pendingData = ref();
const approvalAction = ref('');

const rowClicked = (data) => {
    pendingData.value = data;
    visible.value = true;
}

const handleApproval = (action) => {
    approvalAction.value = action;
}

const closeDialog = () => {
    visible.value = false;
    approvalAction.value = '';
}

const chips = ref({
    approve: [
        { label: 'Withdrawal successful' },
        { label: '您已成功提款' },
    ],
    reject: [
        { label: 'Withdrawal rejected' },
        { label: '提款已被拒絕' },
    ]
});

const handleChipClick = (label) => {
    form.remarks = label;
};

const form = ref({
    id: '',
    action: '',
    remarks: '',
})

const formProcessing = ref(false);

const submitForm = async (transaction) => {
    formProcessing.value = true;
    form.errors = {};

    if (form.value.remarks === '') {
        form.value.remarks = approvalAction.value === 'approve' ? 'Withdrawal approved ' : 'Withdrawal rejected. Please submit again.'
    }

    form.value.id = transaction.id;
    form.value.action = approvalAction.value;

    try {
        const response = await axios.post(route('pending.withdrawalApproval'), form.value);

        closeDialog();

        form.value = {
            action: '',
            remarks: null,
        }

        // Remove current id
        pendingWithdrawals.value = pendingWithdrawals.value.filter(
            (t) => t.id !== transaction.id
        );

        totalAmount.value = Number(totalAmount.value) - Number(transaction.transaction_amount);

        emitter.emit('refresh:pendingCounts');

        toast.add({
            title: response.data.toast_title,
            message: response.data.toast_message,
            type: response.data.toast_type,
        });

    } catch (error) {
        if (error.response?.status === 422) {
            form.value.errors = error.response.data.errors;
        } else {
            closeDialog();

            form.value = {
                action: '',
                remarks: null,
            }

            toast.add({
                title: error.response.data.toast_title,
                message: error.response.data.toast_message,
                type: error.response.data.toast_type,
            });
        }
    } finally {
        formProcessing.value = false;
    }
}
</script>

<template>
    <!-- data table -->
    <div class="p-6 flex flex-col items-center justify-center self-stretch gap-6 border border-gray-200 bg-white shadow-table rounded-2xl">
        <DataTable
            v-model:filters="filters"
            :value="pendingWithdrawals"
            :rowsPerPageOptions="[10, 20, 50, 100]"
            lazy
            :paginator="pendingWithdrawals?.length > 0"
            removableSort
            paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
            :currentPageReportTemplate="$t('public.paginator_caption')"
            :first="first"
            :rows="10"
            ref="dt"
            dataKey="id"
            :totalRecords="totalRecords"
            :loading="isLoading"
            @page="onPage($event)"
            @sort="onSort($event)"
            @filter="onFilter($event)"
            selectionMode="single"
            @row-click="rowClicked($event.data)"
            :globalFilterFields="['user.name', 'user.email', 'transaction_number', 'from_meta_login']"
        >
            <template #header>
                <div class="flex flex-col md:flex-row gap-3 md:justify-between items-center self-stretch md:pb-6">
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
                    <Button
                        variant="primary-outlined"
                        @click="exportCSV($event)"
                        class="w-full md:w-auto"
                    >
                        {{ $t('public.export') }}
                    </Button>
                </div>
            </template>
            <template #empty><Empty :title="$t('public.empty_pending_request_title')" :message="$t('public.empty_pending_request_message')" /></template>
            <template #loading>
                <div class="flex flex-col gap-2 items-center justify-center">
                    <Loader />
                    <span class="text-sm text-gray-700">{{ $t('public.loading_transactions_caption') }}</span>
                </div>
            </template>
            <template v-if="pendingWithdrawals?.length > 0">
                <Column
                    field="created_at"
                    sortable
                    style="width: 25%"
                    headerClass="hidden md:table-cell"
                >
                    <template #header>
                        <span class="hidden md:block">{{ $t('public.requested_date') }}</span>
                    </template>
                    <template #body="slotProps">
                        {{ dayjs(slotProps.data.created_at).format('YYYY/MM/DD HH:mm:ss') }}
                    </template>
                </Column>

                <Column
                    field="name"
                    :header="$t('public.member')"
                    style="width: 25%"
                    headerClass="hidden md:table-cell"
                >
                    <template #body="slotProps">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                                <div v-if="slotProps.data.user.media.some(m => m.collection_name === 'profile_photo')">
                                    <img
                                        :src="slotProps.data.user.media.find(m => m.collection_name === 'profile_photo').original_url"
                                        alt="profile"
                                    />
                                </div>
                                <div v-else>
                                    <DefaultProfilePhoto />
                                </div>
                            </div>
                            <div class="flex flex-col items-start">
                                <div class="font-medium">
                                    {{ slotProps.data.user.name }}
                                </div>
                                <div class="text-gray-500 text-xs">
                                    {{ slotProps.data.user.email }}
                                </div>
                            </div>
                        </div>
                    </template>
                </Column>

                <Column
                    field="from"
                    style="width: 25%"
                    headerClass="hidden md:table-cell"
                >
                    <template #header>
                        <span class="hidden md:block items-center justify-center w-full">{{ $t('public.from') }}</span>
                    </template>
                    <template #body="slotProps">
                        {{ slotProps.data.from_meta_login ? slotProps.data.from_meta_login?.meta_login :$t('public.rebate_wallet') }}
                    </template>
                </Column>
                <Column field="amount" header="" sortable style="width: 25%" headerClass="hidden md:table-cell">
                    <template #header>
                        <span class="hidden md:block items-center justify-center">{{ $t('public.amount') }} ($)</span>
                    </template>
                    <template #body="slotProps">
                        {{ formatAmount(slotProps.data.transaction_amount) }}
                    </template>
                </Column>
                <ColumnGroup type="footer">
                    <Row>
                        <Column class="hidden md:table-cell" :footer="$t('public.total') + ' ($) :'" :colspan="3" footerStyle="text-align:right" />
                        <Column class="hidden md:table-cell" :footer="formatAmount(totalAmount ? totalAmount : 0)" />
                        <Column class="md:hidden" footerStyle="text-align:right">
                            <template #footer>
                                <div class="flex items-center justify-end">
                                    <div class="overflow-hidden text-right text-ellipsis font-semibold">
                                        {{ $t('public.total') + ' ($) :' }}
                                    </div>
                                    <div class="w-[120px] overflow-hidden text-right text-ellipsis font-semibold">
                                        {{ formatAmount(totalAmount ? totalAmount : 0) }}
                                    </div>
                                </div>
                            </template>
                        </Column>
                    </Row>
                </ColumnGroup>
                <Column class="md:hidden">
                    <template #body="slotProps">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                                    <DefaultProfilePhoto />
                                </div>
                                <div class="flex flex-col items-start">
                                    <div class="text-sm font-semibold">
                                        {{ slotProps.data.user_name }}
                                    </div>
                                    <div class="text-gray-500 text-xs">
                                        {{ formatDateTime(slotProps.data.created_at) }}
                                    </div>
                                </div>
                            </div>
                            <div class="overflow-hidden text-right text-ellipsis font-semibold">
                                {{ slotProps.data.transaction_amount ?  '$&nbsp;' + formatAmount(slotProps.data.transaction_amount) : '' }}
                            </div>
                        </div>
                    </template>
                </Column>
            </template>
        </DataTable>
    </div>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.withdrawal_request', {action: ''})"
        class="dialog-xs md:dialog-md"
    >
        <template
            v-if="!approvalAction"
        >
            <div class="flex flex-col items-center gap-4 divide-y self-stretch">
                <div class="flex flex-col-reverse md:flex-row md:items-center gap-3 self-stretch w-full">
                    <div class="flex items-center gap-3 self-stretch w-full">
                        <div class="w-9 h-9 rounded-full overflow-hidden grow-0 shrink-0">
                            <div v-if="pendingData.user.media.some(m => m.collection_name === 'profile_photo')">
                                <img
                                    :src="pendingData.user.media.find(m => m.collection_name === 'profile_photo').original_url"
                                    alt="profile"
                                />
                            </div>
                            <div v-else>
                                <DefaultProfilePhoto />
                            </div>
                        </div>
                        <div class="flex flex-col items-start w-full">
                            <span class="text-gray-950 text-sm font-medium">{{ pendingData.user.name }}</span>
                            <span class="text-gray-500 text-xs">{{ pendingData.user.email }}</span>
                        </div>
                    </div>
                    <div class="min-w-[180px] text-gray-950 font-semibold text-xl md:text-right">
                        $ {{ formatAmount(pendingData.transaction_amount) }}
                    </div>
                </div>

                <div class="flex flex-col gap-3 items-start w-full pt-4">
                    <div class="flex flex-col md:flex-row md:items-center gap-1 self-stretch">
                        <div class="w-[140px] text-gray-500 text-xs font-medium">
                            {{ $t('public.requested_date') }}
                        </div>
                        <div class="text-gray-950 text-sm font-medium">
                            {{ dayjs(pendingData.created_at).format('YYYY/MM/DD HH:mm:ss') }}
                        </div>
                    </div>
                    <div class="flex flex-col md:flex-row md:items-center gap-1 self-stretch">
                        <div class="w-[140px] text-gray-500 text-xs font-medium">
                            {{ $t('public.withdrawal_fee') }}
                        </div>
                        <div class="text-gray-950 text-sm font-medium">
                            $ {{ formatAmount(pendingData.transaction_charges) }}
                        </div>
                    </div>
                    <div class="flex flex-col md:flex-row md:items-center gap-1 self-stretch">
                        <div class="w-[140px] text-gray-500 text-xs font-medium">
                            {{ $t('public.from') }}
                        </div>
                        <div class="text-gray-950 text-sm font-medium">
                            {{ pendingData.from_meta_login ? pendingData.from_meta_login?.meta_login : $t('public.rebate_wallet') }}
                        </div>
                    </div>
                    <div class="flex flex-col md:flex-row md:items-center gap-1 self-stretch">
                        <div class="w-[140px] text-gray-500 text-xs font-medium">
                            {{ $t('public.balance') }}
                        </div>
                        <div class="text-gray-950 text-sm font-medium">
                            $ {{ formatAmount(pendingData.balance) }}
                        </div>
                    </div>
                </div>

                <div v-if="pendingData.payment_platform === 'crypto' || !pendingData.payment_platform" class="flex flex-col gap-3 items-start w-full pt-4">
                    <div class="flex flex-col md:flex-row md:items-center gap-1 self-stretch">
                        <div class="w-[140px] text-gray-500 text-xs font-medium">
                            {{ $t('public.wallet_name') }}
                        </div>
                        <div class="text-gray-950 text-sm font-medium">
                            {{ pendingData.payment_account_name }}
                        </div>
                    </div>
                    <div class="flex flex-col md:flex-row md:items-center gap-1 self-stretch">
                        <div class="min-w-[140px] text-gray-500 text-xs font-medium">
                            {{ $t('public.receiving_address') }}
                        </div>
                        <div class="text-gray-950 text-sm break-words font-medium">
                            {{ pendingData.payment_account_no }}
                        </div>
                    </div>
                </div>
                <div v-if="pendingData.payment_platform === 'bank'" class="flex flex-col gap-3 items-start w-full pt-4">
                    <div class="flex flex-col md:flex-row md:items-center gap-1 self-stretch">
                        <div class="w-[140px] text-gray-500 text-xs font-medium">
                            {{ $t('public.bank_name') }}
                        </div>
                        <div class="text-gray-950 text-sm font-medium">
                            {{ pendingData.payment_platform_name }}
                            <span class="text-xs text-gray-500">{{ ` (${pendingData.bank_code})` }}</span>
                        </div>
                    </div>
                    <div class="flex flex-col md:flex-row md:items-center gap-1 self-stretch">
                        <div class="min-w-[140px] text-gray-500 text-xs font-medium">
                            {{ pendingData.payment_account_type === 'card' ? $t('public.card_name') : $t('public.account_name') }}
                        </div>
                        <div class="text-gray-950 text-sm break-words font-medium">
                            {{ pendingData.payment_account_name }}
                            <span class="text-xs text-gray-500">{{ ` (${pendingData.payment_account_no})` }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex justify-end items-center pt-5 gap-4 self-stretch sm:pt-7">
                <Button
                    type="button"
                    variant="error-flat"
                    class="w-full md:w-[120px]"
                    @click="handleApproval('reject')"
                    :disabled="form.processing"
                >
                    {{ $t('public.reject') }}
                </Button>
                <Button
                    variant="success-flat"
                    class="w-full md:w-[120px]"
                    @click="handleApproval('approve')"
                    :disabled="form.processing"
                >
                    {{ $t('public.approve') }}
                </Button>
            </div>
        </template>

        <template
            v-if="approvalAction"
            #container="{ closeCallback }"
        >
            <div class="pt-7 px-4 md:pt-8 md:px-7 flex flex-col gap-3 md:gap-5 self-stretch w-full">
                <div class="flex flex-col items-center self-stretch gap-2">
                    <span class="capitalize text-gray-950 text-lg font-semibold">{{ $t('public.withdrawal_request', {action: $t(`public.${approvalAction}`)}) }}</span>
                    <div class="text-gray-500 text-sm">
                        {{ $t('public.withdrawal_request_caption_1') }}
                        <span class="font-semibold lowercase" :class="[approvalAction === 'approve' ? 'text-success-500' : 'text-error-500']">{{ $t(`public.${approvalAction}`) }}</span>
                        {{ $t('public.withdrawal_request_caption_2') }}
                    </div>
                </div>

                <div class="flex flex-col-reverse md:flex-row md:items-center gap-3 py-4 self-stretch w-full">
                    <div class="flex items-center gap-3 self-stretch w-full">
                        <div class="w-9 h-9 rounded-full overflow-hidden grow-0 shrink-0">
                            <div v-if="pendingData.user.media.some(m => m.collection_name === 'profile_photo')">
                                <img
                                    :src="pendingData.user.media.find(m => m.collection_name === 'profile_photo').original_url"
                                    alt="profile"
                                />
                            </div>
                            <div v-else>
                                <DefaultProfilePhoto />
                            </div>
                        </div>
                        <div class="flex flex-col items-start w-full">
                            <span class="text-gray-950 text-sm font-medium">{{ pendingData.user.name }}</span>
                            <span class="text-gray-500 text-xs">{{ pendingData.user.email }}</span>
                        </div>
                    </div>
                    <div class="min-w-[180px] text-gray-950 font-semibold text-xl md:text-right">
                        $ {{ formatAmount(pendingData.transaction_amount) }}
                    </div>
                </div>

                <div class="flex flex-col items-start gap-3 h-40 self-stretch">
                    <InputLabel for="remarks">{{ $t('public.remarks') }}</InputLabel>
                    <div class="flex items-center gap-2 self-stretch overflow-x-auto">
                        <div v-for="(chip, index) in chips[approvalAction]" :key="index">
                            <Chip
                                :label="chip.label"
                                class="w-full text-gray-950 whitespace-nowrap overflow-hidden"
                                :class="{
                                    'border-primary-300 bg-primary-50 text-primary-500 hover:bg-primary-50': form.remarks === chip.label,
                                }"
                                @click="handleChipClick(chip.label)"
                            />
                        </div>
                    </div>
                    <Textarea
                        id="remarks"
                        type="text"
                        class="flex flex-1 self-stretch"
                        v-model="form.remarks"
                        :placeholder="approvalAction === 'approve' ? 'Withdrawal approved' : 'Withdrawal rejected. Please submit again.'"
                        :invalid="!!form?.errors?.remarks"
                        rows="5"
                        cols="30"
                    />
                </div>
            </div>

            <div class="flex justify-end items-center py-5 px-4 gap-4 self-stretch sm:p-7">
                <Button
                    type="button"
                    variant="gray-tonal"
                    class="w-full md:w-fit"
                    @click="closeDialog"
                    :disabled="formProcessing"
                >
                    {{ $t('public.cancel') }}
                </Button>
                <Button
                    variant="primary-flat"
                    class="w-full md:w-fit"
                    @click="submitForm(pendingData)"
                    :disabled="formProcessing"
                >
                    <div v-if="formProcessing" class="animate-spin">
                        <IconLoader size="20" stroke-width="1.5" />
                    </div>
                    {{ formProcessing ? $t('public.processing') : $t('public.confirm') }}
                </Button>
            </div>
        </template>
    </Dialog>
</template>
