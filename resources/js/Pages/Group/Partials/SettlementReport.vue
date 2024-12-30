<script setup>
import {CalendarIcon, ClockRewindIcon} from "@/Components/Icons/outline.jsx";
import Button from "@/Components/Button.vue";
import {onMounted, ref, watch} from "vue";
import Dialog from "primevue/dialog";
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import {transactionFormat} from "@/Composables/index.js";
import {
    IconPlus,
    IconMinus,
    IconCloudDownload
} from "@tabler/icons-vue";
import dayjs from "dayjs";
import MultiSelect from "primevue/multiselect";
import IconField from "primevue/iconfield";
import Dropdown from "primevue/dropdown";

const visible = ref(false);
const settlementReports = ref();
const expandedRows = ref({});
const months = ref([]);
const {formatAmount} = transactionFormat();

const getCurrentMonthYear = () => {
    const date = new Date();
    const month = String(date.getMonth()).padStart(2, '0');
    const year = date.getFullYear();
    return `${month}/${year}`;
};

const getTransactionMonths = async () => {
    try {
        const monthsResponse = await axios.get('/transaction/getTransactionMonths');
        months.value = monthsResponse.data;
    } catch (error) {
        console.error('Error transaction months:', error);
    }
};

onMounted(() => {
    getTransactionMonths();
})

// Reactive variables
const selectedMonths = ref([getCurrentMonthYear()]);
const getResults = async (selectedMonths = []) => {
    try {
        let url = '/group/getSettlementReport';

        // Convert the array to a comma-separated string if not empty
        if (selectedMonths && selectedMonths.length > 0) {
            const selectedMonthString = selectedMonths.join(',');
            url += `?selectedMonths=${selectedMonthString}`;
        }

        const response = await axios.get(url);
        settlementReports.value = response.data.settlementReports;
    } catch (error) {
        console.error('Error changing locale:', error);
    }
};

getResults();

watch(selectedMonths, (newMonths) => {
    getResults(newMonths);
});

const expandAll = () => {
    expandedRows.value = settlementReports.value.reduce((acc, p) => (acc[p.id] = true) && acc, {});
};
const collapseAll = () => {
    expandedRows.value = null;
};
</script>

<template>
    <Button
        variant="primary-outlined"
        type="button"
        size="base"
        class="w-full md:w-auto"
        @click="visible = true"
    >
        <div class="flex justify-center items-center gap-3 self-stretch">
            <ClockRewindIcon />
            <div class="text-center text-sm font-medium">
                {{ $t('public.settlement_report') }}
            </div>
        </div>
    </Button>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.view_settlement_report')"
        class="dialog-xs md:dialog-lg"
    >
        <DataTable
            v-model:expandedRows="expandedRows"
            :value="settlementReports"
            dataKey="id"
            removable-sort
        >
            <template #header>
                <div class="flex flex-col gap-5 items-center self-stretch mb-5">
                    <div class="flex flex-col gap-3 md:flex-row md:justify-between items-center self-stretch">
                        <div class="flex flex-col md:flex-row gap-3 items-center w-full">
                            <IconField iconPosition="left" class="relative flex items-center w-full md:w-60">
                                <CalendarIcon class="z-20 w-5 h-5 text-gray-400" />
                                <MultiSelect
                                    v-model="selectedMonths"
                                    filter
                                    :options="months"
                                    :placeholder="$t('public.month_placeholder')"
                                    :maxSelectedLabels="1"
                                    :selectedItemsLabel="`${selectedMonths.length} ${$t('public.months_selected')}`"
                                    class="w-full md:w-60 font-normal"
                                >
                                    <template #filtericon>{{ $t('public.select_all') }}</template>
                                </MultiSelect>
                            </IconField>
                        </div>
                        <Button
                            type="button"
                            variant="primary-outlined"
                            class="w-full md:w-36"
                            @click="exportCSV($event)"
                        >
                            {{ $t('public.export') }}
                            <IconCloudDownload size="20" stroke-width="1.25" />
                        </Button>
                    </div>
                    <div class="flex md:flex-wrap md:justify-end gap-3 w-full">
                        <Button
                            type="button"
                            variant="gray-text"
                            @click="expandAll"
                        >
                            <IconPlus size="20" stroke-width="1.25" />
                            {{ $t('public.expand_all') }}
                        </Button>
                        <Button
                            type="button"
                            variant="gray-text"
                            @click="collapseAll"
                        >
                            <IconMinus size="20" stroke-width="1.25" />
                            {{ $t('public.collapse_all') }}
                        </Button>
                    </div>
                </div>
            </template>
            <Column expander style="width: 5rem" />
            <Column field="month" header="month" sortable style="width: 30%" />
            <Column field="total_fee" :header="$t('public.total_fee') + ' ($)'" style="width: 30%">
                <template #body="slotProps">
                    {{ formatAmount(slotProps.data.total_fee) }}
                </template>
            </Column>
            <Column field="total_balance" :header="$t('public.total_balance') + ' ($)'" style="width: 30%">
                <template #body="slotProps">
                    {{ formatAmount(slotProps.data.total_balance) }}
                </template>
            </Column>
            <template #expansion="slotProps">
                <DataTable
                    :value="slotProps.data.group_settlements"
                    removable-sort
                >
                    <Column field="group_name" :header="$t('public.group')" style="width: 15%" />
                    <Column field="group_deposit" :header="$t('public.deposit') + ' ($)'" sortable style="width: 20%">
                        <template #body="slotProps">
                            {{ formatAmount(slotProps.data.group_deposit) }}
                        </template>
                    </Column>
                    <Column field="group_withdrawal" :header="$t('public.withdrawal') + ' ($)'" sortable style="width: 20%">
                        <template #body="slotProps">
                            {{ formatAmount(slotProps.data.group_withdrawal) }}
                        </template>
                    </Column>
                    <Column field="group_fee" :header="$t('public.fee') + ' ($)'" sortable style="width: 20%">
                        <template #body="slotProps">
                            {{ formatAmount(slotProps.data.group_fee) }}
                        </template>
                    </Column>
                    <Column field="group_balance" :header="$t('public.balance') + ' ($)'" sortable style="width: 20%">
                        <template #body="slotProps">
                            {{ formatAmount(slotProps.data.group_balance) }}
                        </template>
                    </Column>
                </DataTable>
            </template>
        </DataTable>
    </Dialog>
</template>
