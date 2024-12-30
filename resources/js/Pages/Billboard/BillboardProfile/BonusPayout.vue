<script setup>
import Button from "@/Components/Button.vue"
import {ref, watch} from "vue";
import Dialog from "primevue/dialog";
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Empty from '@/Components/Empty.vue';
import Loader from "@/Components/Loader.vue";
import { IconCloudDownload, IconX } from '@tabler/icons-vue';
import Calendar from 'primevue/calendar';
import {transactionFormat} from "@/Composables/index.js";
import dayjs from "dayjs";
import ColumnGroup from "primevue/columngroup";
import Row from "primevue/row";

const props = defineProps({
    profile: Object
})

const dt = ref(null);
const loading = ref(false);
const bonuses = ref([]);
const totalBonusAmount = ref();
const {formatAmount} = transactionFormat();

// Reactive variable for selected date range
const selectedDate = ref([]);

// Get current date
const today = new Date();
const maxDate = ref(today);

const getStatementData = async (filterDate = null) => {
    loading.value = true;

    try {
        let url = `/billboard/getStatementData?profile_id=${props.profile.id}`;

        if (filterDate) {
            const [startDate, endDate] = filterDate;
            url += `&startDate=${dayjs(startDate).format('YYYY-MM-DD')}&endDate=${dayjs(endDate).format('YYYY-MM-DD')}`;
        }

        const response = await axios.get(url);
        bonuses.value = response.data.bonuses;
        totalBonusAmount.value = response.data.totalBonusAmount;
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
</script>

<template>
    <div class="flex flex-col items-center gap-4 flex-grow self-stretch">
        <DataTable
            :value="bonuses"
            removableSort
            scrollable
            scrollHeight="400px"
            tableStyle="md:min-width: 50rem"
            ref="dt"
            :loading="loading"
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
                        <span class="text-gray-500 text-right text-sm font-medium">{{ $t('public.total') }}:</span>
                        <span class="text-gray-950 text-sm font-semibold ml-2">$ {{ formatAmount(totalBonusAmount ? totalBonusAmount : 0) }}</span>
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
                field="target_amount"
                :header="$t('public.target') + (profile.sales_category === 'trade_volume' ? ' (Ł)' : ' ($)')"
                class="hidden md:table-cell"
            >
                <template #body="slotProps">
                    {{ formatAmount(slotProps.data.target_amount) }}
                </template>
            </Column>
            <Column
                field="achieved_amount"
                sortable
                :header="$t('public.achieved') + (profile.sales_category === 'trade_volume' ? ' (Ł)' : ' ($)')"
                class="hidden md:table-cell">
                <template #body="slotProps"
                >
                    {{ formatAmount(slotProps.data.achieved_amount) }}
                </template>
            </Column>
            <Column
                field="bonus_rate"
                :header="$t('public.rate') + (profile.sales_category === 'trade_volume' ? ' ($)' : ' (%)')"
                class="hidden md:table-cell"
            >
                <template #body="slotProps">
                    {{ slotProps.data.bonus_rate }}
                </template>
            </Column>
            <Column
                field="bonus_amount"
                sortable
                :header="$t('public.bonus') + ' ($)'"
                class="hidden md:table-cell"
            >
                <template #body="slotProps">
                    {{ formatAmount(slotProps.data.bonus_amount) }}
                </template>
            </Column>
            <Column class="md:hidden px-0">
                <template #body="slotProps">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex flex-col items-start gap-1">
                                <div class="flex gap-1 items-center self-stretch">
                                    <div class="text-sm text-gray-950 font-semibold">
                                        {{ dayjs(slotProps.data.created_at).format('YYYY/MM/DD') }}
                                    </div>
                                    <div
                                        class="py-[1px] px-1 flex justify-center items-center rounded text-xxs font-semibold bg-info-50 text-info-500"
                                    >
                                        <span><span v-if="profile.sales_category === 'trade_volume'">$ </span>{{ formatAmount(slotProps.data.bonus_rate) % 1 === 0 ? formatAmount(slotProps.data.bonus_rate, 0) : formatAmount(slotProps.data.bonus_rate)  }}</span><span v-if="profile.sales_category !== 'trade_volume'">%</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 text-gray-500 text-xs">
                                    <div>
                                        <span v-if="profile.sales_category !== 'trade_volume'">$ </span>{{ formatAmount(slotProps.data.achieved_amount) }}<span v-if="profile.sales_category === 'trade_volume'"> Ł</span>
                                    </div>
                                    <span>|</span>
                                    <div>
                                        <span v-if="profile.sales_category !== 'trade_volume'">$ </span>{{ formatAmount(slotProps.data.target_amount) }}<span v-if="profile.sales_category === 'trade_volume'"> Ł</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="w-full text-base text-right max-w-[90px] truncate font-semibold">
                            $ {{ formatAmount(slotProps.data.bonus_amount) }}
                        </div>
                    </div>
                </template>
            </Column>
            <ColumnGroup type="footer">
                <Row>
                    <Column class="hidden md:table-cell" :footer="$t('public.total') + ' ($):'" :colspan="4" footerStyle="text-align:right" />
                    <Column class="hidden md:table-cell" :footer="formatAmount(totalBonusAmount ? totalBonusAmount : 0)" />
                </Row>
            </ColumnGroup>
        </DataTable>
    </div>
</template>
