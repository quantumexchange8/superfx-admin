<script setup>
import dayjs from "dayjs";
import Button from "@/Components/Button.vue";
import {IconCloudDownload, IconX} from "@tabler/icons-vue";
import ColumnGroup from "primevue/columngroup";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Loader from "@/Components/Loader.vue";
import Calendar from "primevue/calendar";
import Empty from "@/Components/Empty.vue";
import Row from "primevue/row";
import {ref, watch} from "vue";
import {transactionFormat} from "@/Composables/index.js";
import StatusBadge from "@/Components/StatusBadge.vue";
import Dialog from "primevue/dialog";
import Tag from "primevue/tag";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";

const props = defineProps({
    profile: Object
})

const dt = ref(null);
const loading = ref(false);
const bonusWithdrawalHistories = ref([]);
const totalApprovedAmount = ref();
const {formatAmount} = transactionFormat();

// Reactive variable for selected date range
const selectedDate = ref([]);

// Get current date
const today = new Date();
const maxDate = ref(today);

const getStatementData = async (filterDate = null) => {
    loading.value = true;

    try {
        let url = `/billboard/getBonusWithdrawalHistories?profile_id=${props.profile.id}`;

        if (filterDate) {
            const [startDate, endDate] = filterDate;
            url += `&startDate=${dayjs(startDate).format('YYYY-MM-DD')}&endDate=${dayjs(endDate).format('YYYY-MM-DD')}`;
        }

        const response = await axios.get(url);
        bonusWithdrawalHistories.value = response.data.bonusWithdrawalHistories;
        totalApprovedAmount.value = response.data.totalApprovedAmount;
    } catch (error) {
        console.error('Error changing locale:', error);
    } finally {
        loading.value = false;
    }
};

getStatementData();

watch(selectedDate, (newDateRange) => {
    if (Array.isArray(newDateRange)) {
        const [startDate, endDate] = newDateRange;

        if (startDate && endDate) {
            getStatementData([startDate, endDate]);
        } else if (startDate || endDate) {
            getStatementData([startDate || endDate, endDate || startDate]);
        } else {
            getStatementData();
        }
    } else {
        console.warn('Invalid date range format:', newDateRange);
    }
})

const clearDate = () => {
    selectedDate.value = [];
};

const visible = ref(false);
const withdrawalData = ref();
const tooltipText = ref('copy');

const rowClicked = (data) => {
    withdrawalData.value = data;
    visible.value = true;
}

const copyToClipboard = (text) => {
    const textToCopy = text;

    const textArea = document.createElement('textarea');
    document.body.appendChild(textArea);

    textArea.value = textToCopy;
    textArea.select();

    try {
        const successful = document.execCommand('copy');

        tooltipText.value = 'copied';
        setTimeout(() => {
            tooltipText.value = 'copy';
        }, 1500);
    } catch (err) {
        console.error('Copy to clipboard failed:', err);
    }

    document.body.removeChild(textArea);
}
</script>

<template>
    <div class="flex flex-col items-center gap-4 flex-grow self-stretch">
        <DataTable
            :value="bonusWithdrawalHistories"
            removableSort
            scrollable
            scrollHeight="400px"
            tableStyle="md:min-width: 50rem"
            ref="dt"
            :loading="loading"
            selectionMode="single"
            @row-click="rowClicked($event.data)"
        >
            <template #header>
                <div class="flex flex-col md:flex-row gap-3 items-center self-stretch md:pb-6">
                    <div class="w-full grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="relative w-full md:w-[272px]">
                            <Calendar
                                v-model="selectedDate"
                                selectionMode="range"
                                :manualInput="false"
                                :maxDate="maxDate"
                                dateFormat="dd/mm/yy"
                                showIcon
                                iconDisplay="input"
                                placeholder="yyyy/mm/dd - yyyy/mm/dd"
                                class="w-full font-normal"
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
                                @click="exportCSV($event)"
                                class="w-full md:w-auto"
                            >
                                {{ $t('public.export') }}
                                <IconCloudDownload size="20" color="#2970FF" stroke-width="1.25" />
                            </Button>
                        </div>
                    </div>
                    <div class="flex justify-end self-stretch md:hidden">
                        <span class="text-gray-500 text-right text-sm font-medium">{{ $t('public.total_approved') }}:</span>
                        <span class="text-gray-950 text-sm font-semibold ml-2">$ {{ formatAmount(totalApprovedAmount ? totalApprovedAmount : 0) }}</span>
                    </div>
                </div>
            </template>
            <template #empty><Empty :message="$t('public.no_record_message')"/></template>
            <template #loading>
                <div class="flex flex-col gap-2 items-center justify-center">
                    <Loader />
                </div>
            </template>

            <Column
                field="created_at"
                sortable
                :header="$t('public.date')"
                class="hidden md:table-cell md:py-3"
            >
                <template #body="slotProps">
                    {{ dayjs(slotProps.data.created_at).format('YYYY/MM/DD') }}
                </template>
            </Column>
            <Column
                field="transaction_number"
                sortable
                :header="$t('public.id')"
                class="hidden md:table-cell"
            >
                <template #body="slotProps">
                    {{ slotProps.data.transaction_number }}
                </template>
            </Column>
            <Column
                field="amount"
                sortable
                :header="$t('public.amount') + ' ($)'"
                class="hidden md:table-cell">
                <template #body="slotProps"
                >
                    {{ formatAmount(slotProps.data.amount) }}
                </template>
            </Column>
            <Column
                field="status"
                :header="$t('public.status')"
                class="hidden md:table-cell"
            >
                <template #body="slotProps">
                    <StatusBadge :value="slotProps.data.status" class="w-fit">
                        {{ $t(`public.${slotProps.data.status}`) }}
                    </StatusBadge>
                </template>
            </Column>
            <Column class="md:hidden px-0">
                <template #body="slotProps">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex flex-col items-start gap-1">
                                <div class="flex gap-1 items-center self-stretch">
                                    <div class="text-sm text-gray-950 font-semibold">
                                        {{ slotProps.data.transaction_number }}
                                    </div>
                                    <StatusBadge :value="slotProps.data.status" class="w-fit">
                                        <span class="text-xxs">{{ $t(`public.${slotProps.data.status}`) }}</span>
                                    </StatusBadge>
                                </div>

                                <div class="flex items-center gap-2 text-gray-500 text-xs">
                                    <span>{{ dayjs(slotProps.data.created_at).format('YYYY/MM/DD H:m:ss') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="w-full text-base text-right max-w-[90px] truncate font-semibold">
                            $ {{ formatAmount(slotProps.data.amount) }}
                        </div>
                    </div>
                </template>
            </Column>
            <ColumnGroup type="footer">
                <Row>
                    <Column class="hidden md:table-cell" :footer="$t('public.total_approved') + ':'" :colspan="2" footerStyle="text-align:right" />
                    <Column class="hidden md:table-cell" :colspan="2" :footer="formatAmount(totalApprovedAmount ? totalApprovedAmount : 0)" />
                </Row>
            </ColumnGroup>
        </DataTable>
    </div>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.withdrawal_details')"
        class="dialog-xs md:dialog-md"
    >
        <div class="flex flex-col justify-center items-start pb-4 gap-3 self-stretch border-b border-gray-200 md:flex-row md:pt-4 md:justify-between">
            <!-- below md -->
            <span class="md:hidden self-stretch text-gray-950 text-xl font-semibold">$ {{ formatAmount(withdrawalData.transaction_amount) }}</span>
            <div class="flex items-center gap-3 self-stretch">
                <div class="w-9 h-9 rounded-full overflow-hidden grow-0 shrink-0">
                    <template v-if="withdrawalData.user_profile_photo">
                        <img :src="withdrawalData.user_profile_photo" alt="profile_photo">
                    </template>
                    <template v-else>
                        <DefaultProfilePhoto />
                    </template>
                </div>
                <div class="flex flex-col items-start flex-grow">
                    <span class="self-stretch overflow-hidden text-gray-950 text-ellipsis text-sm font-medium">{{ withdrawalData.user_name }}</span>
                    <span class="self-stretch overflow-hidden text-gray-500 text-ellipsis text-xs">{{ withdrawalData.user_email }}</span>
                </div>
            </div>
            <!-- above md -->
            <span class="hidden md:block w-[180px] text-gray-950 text-right text-xl font-semibold">$ {{ formatAmount(withdrawalData.transaction_amount) }}</span>
        </div>

        <div class="flex flex-col items-center py-4 gap-3 self-stretch border-b border-gray-200">
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.transaction_id') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ withdrawalData.transaction_number }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.requested_date') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ dayjs(withdrawalData.created_at).format('YYYY/MM/DD H:mm:ss') }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.approval_date') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ dayjs(withdrawalData.approved_at).format('YYYY/MM/DD H:mm:ss') }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.from') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium"> {{ $t(`public.${withdrawalData.from_wallet_name}`)  }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.status') }}</span>
                <StatusBadge :value="withdrawalData.status">
                    <span v-if="withdrawalData.status === 'successful'">{{ $t('public.approved') }}</span>
                    <span v-else>{{ $t('public.rejected') }}</span>
                </StatusBadge>
            </div>
        </div>

        <div class="flex flex-col items-center py-4 gap-3 self-stretch border-b border-gray-200">
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.wallet_name') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ withdrawalData.to_wallet_name }}</span>
            </div>
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.receiving_address') }}</span>
                <div class="flex justify-center items-center self-stretch select-none cursor-pointer relative" @click="copyToClipboard(withdrawalData.to_wallet_address)">
                    <Tag
                        v-if="tooltipText === 'copied'"
                        class="absolute -top-7 right-32"
                        severity="contrast"
                        :value="$t(`public.${tooltipText}`)"
                    ></Tag>
                    <span class="flex-grow overflow-hidden text-gray-950 text-ellipsis text-sm font-medium break-words">{{ withdrawalData.to_wallet_address }}</span>
                </div>
            </div>
        </div>

        <div class="flex flex-col items-center py-4 gap-3 self-stretch">
            <div class="flex flex-col md:flex-row items-start gap-1 self-stretch">
                <span class="self-stretch md:w-[140px] text-gray-500 text-xs">{{ $t('public.remarks') }}</span>
                <span class="self-stretch text-gray-950 text-sm font-medium">{{ withdrawalData.remarks }}</span>
            </div>
        </div>
    </Dialog>
</template>
