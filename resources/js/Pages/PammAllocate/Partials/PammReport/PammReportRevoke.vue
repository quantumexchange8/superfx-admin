<script setup>
import { ref, watch } from "vue";
import Button from '@/Components/Button.vue';
import InputText from 'primevue/inputtext';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import Empty from '@/Components/Empty.vue';
import Loader from "@/Components/Loader.vue";
import { FilterMatchMode } from "primevue/api";
import { IconSearch, IconCircleXFilled, IconX } from '@tabler/icons-vue';
import Calendar from 'primevue/calendar';
import { transactionFormat } from "@/Composables/index.js";
import dayjs from "dayjs";
import ColumnGroup from "primevue/columngroup";
import Row from "primevue/row";

const { formatDateTime, formatAmount } = transactionFormat();

const props = defineProps({
    master: Object
})

const dt = ref(null);
const loading = ref(false);
const revokePammAccounts = ref([]);
const totalPenaltyFee = ref();
const filteredValueCount = ref(0);
const emit = defineEmits(['update:rowCount']);

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    name: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
});

const clearFilterGlobal = () => {
    filters.value['global'].value = null;
}

// Reactive variable for selected date range
const selectedDate = ref([]);

// Get current date
const today = new Date();

const maxDate = ref(today);

const getRevokedPammAccountsData = async (filterDate = null) => {
    loading.value = true;

    try {
        let url = `/pamm_allocate/getRevokePammAccountsData?asset_master_id=${props.master.id}`;

        if (filterDate) {
            const [startDate, endDate] = filterDate;
            url += `&startDate=${dayjs(startDate).format('YYYY-MM-DD')}&endDate=${dayjs(endDate).format('YYYY-MM-DD')}`;
        }

        const response = await axios.get(url);
        revokePammAccounts.value = response.data.revokePammAccounts;
        totalPenaltyFee.value = response.data.totalPenaltyFee;
        emit('update:rowCount', revokePammAccounts.value.length);
    } catch (error) {
        console.error('Error changing locale:', error);
    } finally {
        loading.value = false;
    }
};

getRevokedPammAccountsData();

watch(selectedDate, (newDateRange) => {
    if (Array.isArray(newDateRange)) {
        const [startDate, endDate] = newDateRange;

        if (startDate && endDate) {
            getRevokedPammAccountsData([startDate, endDate]);
        } else if (startDate || endDate) {
            getRevokedPammAccountsData([startDate || endDate, endDate || startDate]);
        } else {
            getRevokedPammAccountsData();
        }
    } else {
        console.warn('Invalid date range format:', newDateRange);
    }
})

const clearDate = () => {
    selectedDate.value = [];
};

const handleFilter = (e) => {
    filteredValueCount.value = e.filteredValue.length;
};
</script>

<template>
    <div class="flex flex-col items-center gap-4 flex-grow self-stretch">
        <DataTable
            v-model:filters="filters"
            :value="revokePammAccounts"
            removableSort
            scrollable
            scrollHeight="400px"
            tableStyle="md:min-width: 50rem"
            ref="dt"
            :loading="loading"
            :globalFilterFields="['user_name', 'user_email', 'meta_login']"
            @filter="handleFilter"
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
                            </Button>
                        </div>
                    </div>
                    <div class="flex justify-end self-stretch md:hidden" v-if="revokePammAccounts?.length > 0 && filteredValueCount > 0">
                        <span class="text-gray-500 text-right text-sm font-medium">{{ $t('public.total') }}:</span>
                        <span class="text-gray-950 text-sm font-semibold ml-2">$ {{ formatAmount(totalPenaltyFee)}}</span>
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
                field="revoked_date"
                sortable
                :header="$t('public.revoked_date')"
                class="hidden md:table-cell"
            >
                <template #body="slotProps">
                    {{ dayjs(slotProps.data.revoked_date).format('YYYY/MM/DD') }}
                </template>
            </Column>
            <Column
                field="name"
                sortable
                :header="$t('public.name')"
                class="hidden md:table-cell"
            >
                <template #body="slotProps">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                            <DefaultProfilePhoto />
                        </div>
                        <div class="flex flex-col items-start">
                            <div class="font-medium max-w-[150px] lg:max-w-[240px] truncate">
                                {{ slotProps.data.user_name }}
                            </div>
                            <div class="text-gray-500 text-xs max-w-[150px] lg:max-w-[240px] truncate">
                                {{ slotProps.data.user_email }}
                            </div>
                        </div>
                    </div>
                </template>
            </Column>
            <Column
                field="meta_login"
                :header="$t('public.account')"
                class="hidden md:table-cell">
                <template #body="slotProps"
            >
                    {{ slotProps.data.meta_login }}
                </template>
            </Column>
            <Column
                field="penalty_fee"
                sortable
                :header="$t('public.balance') + '&nbsp;($)'"
                class="hidden md:table-cell"
            >
                <template #body="slotProps">
                    {{ formatAmount(slotProps.data.penalty_fee) }}
                </template>
            </Column>
            <Column class="md:hidden px-0">
                <template #body="slotProps">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                                <DefaultProfilePhoto />
                            </div>
                            <div class="flex flex-col items-start gap-1">
                                <div class="text-xs truncate font-medium max-w-36">
                                    {{ slotProps.data.user_name }}
                                </div>

                                <div class="flex items-center gap-2 text-gray-500 text-xs">
                                    <div>
                                        {{ dayjs(slotProps.data.revoked_date).format('YYYY/MM/DD') }}
                                    </div>
                                    <span>|</span>
                                    <div>
                                        {{ slotProps.data.meta_login }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="w-full text-right max-w-[90px] truncate font-semibold">
                            $ {{ formatAmount(slotProps.data.penalty_fee) }}
                        </div>
                    </div>
                </template>
            </Column>
            <ColumnGroup type="footer" v-if="revokePammAccounts?.length > 0 && filteredValueCount > 0">
                <Row>
                    <Column class="hidden md:table-cell" :footer="$t('public.total') + ' ($):'" :colspan="3" footerStyle="text-align:right" />
                    <Column class="hidden md:table-cell" :footer="formatAmount(totalPenaltyFee ? totalPenaltyFee : 0)" />
                    <Column class="hidden md:table-cell" v-if="revokePammAccounts.length > 0 ? (revokePammAccounts[0].investment_periods > 0 ? $t('public.status') : '') : null" />
                </Row>
            </ColumnGroup>
        </DataTable>
    </div>
</template>