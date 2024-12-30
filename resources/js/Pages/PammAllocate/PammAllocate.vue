<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import {ref, watch, computed, watchEffect} from "vue";
import { transactionFormat } from "@/Composables/index.js";
import { usePage } from "@inertiajs/vue3";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import {
  IconSearch, IconCircleXFilled, IconUserDollar, IconPremiumRights,
  IconAdjustments, IconScanEye, IconTriangleFilled, IconTriangleInvertedFilled,
  IconChevronLeft, IconChevronRight
} from '@tabler/icons-vue';
import AddAssetMaster from '@/Pages/PammAllocate/Partials/AddAssetMaster.vue';
import InputText from 'primevue/inputtext';
import Button from '@/Components/Button.vue';
import Badge from '@/Components/Badge.vue';
import Dropdown from "primevue/dropdown";
import Action from "@/Pages/PammAllocate/Partials/Action.vue";
import OverlayPanel from 'primevue/overlaypanel';
import Calendar from 'primevue/calendar';
import AssetMaster from '@/Pages/PammAllocate/Partials/AssetMaster.vue';
import dayjs from "dayjs";

const { formatDate, formatMonthDate, formatAmount } = transactionFormat();

const loading = ref(false);
const topThreeMasters = ref([]);
const currentAssets = ref(0);
const lastMonthAssetComparison = ref(0);
const currentInvestors = ref(0);
const lastMonthInvestorComparison = ref(0);
const today = new Date(new Date().setHours(0, 0, 0, 0));
const selectedDate = ref(today);
const isCalendarVisible = ref(false);
const profit = ref(0);
const loss = ref(0);

const groupsOptions = ref([]);

const getResults = async () => {
    loading.value = true;

    try {
        const response = await axios.get('/pamm_allocate/getMetrics');
        topThreeMasters.value = response.data.topThreeMasters;
        currentAssets.value = response.data.currentAssets;
        lastMonthAssetComparison.value = response.data.lastMonthAssetComparison;
        currentInvestors.value = response.data.currentInvestors;
        lastMonthInvestorComparison.value = response.data.lastMonthInvestorComparison;
    } catch (error) {
        console.error('Error getting metrics:', error);
    } finally {
        loading.value = false;
    }
};

const getOptions = async () => {
    try {
        const response = await axios.get('/pamm_allocate/getOptions');
        groupsOptions.value = response.data.groupsOptions;
    } catch (error) {
        console.error('Error getting options:', error);
    }
};

const getProfitLoss = async (date) => {
    try {
        const formattedDate = dayjs(date).format('YYYY-MM-DD');
        const response = await axios.get('/pamm_allocate/getProfitLoss', { params: { date: formattedDate } });
        profit.value = response.data.profit;
        loss.value = response.data.loss;
    } catch (error) {
        console.error('Error getting profit/loss:', error);
    }
};

getResults();
getOptions();
getProfitLoss();

function calculatePercentage(fund) {
  if (!currentAssets.value || !fund) {
    return 0;
  }
  return ((fund / currentAssets.value) * 100).toFixed(2);
}

function calculateProfitPercentage(profit, total) {
    if (total === 0) {
        return 0;
    }
    return ((profit / total) * 100).toFixed(2);
}

function calculateLossPercentage(loss, total) {
    if (total === 0) {
        return 0;
    }
    return ((loss / total) * 100).toFixed(2);
}

watch(selectedDate, (newDate) => {
    getProfitLoss(newDate);
    isCalendarVisible.value = false;
});

const toggleCalendar = () => {
  isCalendarVisible.value = !isCalendarVisible.value;
};

const changeDate = (days) => {
    const newDate = new Date(selectedDate.value);
    newDate.setDate(newDate.getDate() + days);

    if (newDate <= today) {
        selectedDate.value = newDate;
    }
};

const isNextButtonDisabled = computed(() => {
    return (selectedDate.value >= today);
});

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults();
    }
});
</script>

<template>
    <AuthenticatedLayout :title="$t('public.pamm_allocate')">
        <div class="flex flex-col items-center gap-5 md:gap-8">

            <!-- Add Master -->
            <div class="flex justify-end items-center self-stretch">
                <AddAssetMaster :groupsOptions="groupsOptions" />
            </div>

            <!-- Overview -->
            <div class="w-full flex flex-col md:flex-row gap-5 self-stretch">
                <div class="w-full flex flex-col items-center py-5 px-4 md:px-8 md:py-6 gap-5 md:gap-8 rounded-2xl bg-white shadow-toast">
                    <div class="flex flex-col items-start self-stretch md:flex-shrink-0">
                        <div class="flex justify-center items-center py-2">
                            <span class="text-gray-500 text-sm">{{ $t('public.current_assets_under_management') + `($)` }}</span>
                        </div>
                        <div class="flex items-end gap-5">
                            <span class="text-gray-950 text-xl font-semibold md:text-xxl">{{ currentAssets ? formatAmount(currentAssets) : formatAmount(0) }}</span>
                            <div class="flex items-center pb-1.5 gap-2">
                                <div v-if="currentAssets" class="flex items-center gap-2">
                                    <div
                                        class="flex items-center gap-2"
                                        :class="
                                            {
                                                'text-green': lastMonthAssetComparison > 0,
                                                'text-pink': lastMonthAssetComparison < 0
                                            }"
                                    >
                                        <IconTriangleFilled v-if="lastMonthAssetComparison > 0" size="12" stroke-width="1.25" />
                                        <IconTriangleInvertedFilled v-if="lastMonthAssetComparison < 0" size="12" stroke-width="1.25" />
                                        <span class="text-xs font-medium md:text-sm">  {{ `${formatAmount(lastMonthAssetComparison)}%` }}</span>
                                    </div>
                                    <span class="text-gray-400 text-xs md:text-sm">{{ $t('public.compare_last_month') }}</span>
                                </div>
                                <span v-else class="text-gray-400 text-xs md:text-sm">{{ $t('public.data_not_available') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col gap-3 items-center self-stretch w-full">
                        <div v-for="index in 3" :key="index" class="flex items-center py-2 gap-3 md:gap-4 w-full">
                            <div class="w-full flex items-center min-w-[140px] md:min-w-[180px] md:max-w-[240px] gap-3 md:gap-4">
                                <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                                    <DefaultProfilePhoto />
                                </div>
                                <span class="truncate w-full max-w-[180px] md:max-w-[200px] text-gray-950 text-sm font-medium md:text-base">
                                    {{ topThreeMasters[index - 1]?.asset_name || '------' }}
                                </span>
                            </div>
                            <div class="w-full max-w-[52px] xs:max-w-sm sm:max-w-md md:max-w-full h-1 bg-gray-100 rounded-full relative">
                                <div
                                    class="absolute top-0 left-0 h-full rounded-full bg-primary-500 transition-all duration-700 ease-in-out"
                                    :style="{ width: `${calculatePercentage(topThreeMasters[index - 1]?.total_fund)}%` }"
                                />
                            </div>
                            <span class="truncate text-gray-950 text-right text-sm font-medium md:text-base w-full max-w-[88px] md:max-w-[110px]">
                                {{ topThreeMasters[index - 1]?.total_fund ? formatAmount(topThreeMasters[index - 1].total_fund) : formatAmount(0) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="w-full flex flex-col gap-5 md:max-w-[262px] xl:max-w-[358px] 2xl:max-w-[560px]">
                    <div class="w-full flex flex-col items-center py-5 px-4 gap-3 rounded-2xl bg-white shadow-toast md:px-6 md:py-6">
                        <span class="self-stretch text-gray-500 text-sm">{{ $t('public.current_joining_investors') }}</span>
                        <div class="flex flex-col items-start gap-1 self-stretch">
                            <span class="text-gray-950 text-xl font-semibold md:text-xxl">{{ currentInvestors ? formatAmount(currentInvestors, 0) : 0 }}</span>
                            <div class="flex items-center pb-1.5 gap-2">
                                <div v-if="currentInvestors" class="flex items-center gap-2">
                                    <div
                                        class="flex items-center gap-2"
                                        :class="
                                            {
                                                'text-green': lastMonthInvestorComparison > 0,
                                                'text-pink': lastMonthInvestorComparison < 0
                                            }"
                                    >
                                        <IconTriangleFilled v-if="lastMonthInvestorComparison > 0" size="12" stroke-width="1.25" />
                                        <IconTriangleInvertedFilled v-if="lastMonthInvestorComparison < 0" size="12" stroke-width="1.25" />
                                        <span class="text-xs font-medium md:text-sm">  {{ `${lastMonthInvestorComparison} ${$t('public.pax')}` }}</span>
                                    </div>
                                    <span class="text-gray-400 text-xs md:text-sm">{{ $t('public.compare_last_month') }}</span>
                                </div>
                                <span v-else class="text-gray-400 text-xs md:text-sm">{{ $t('public.data_not_available') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="w-full flex flex-col items-center px-4 pt-5 pb-6 gap-3 rounded-2xl bg-white shadow-toast md:px-6">
                        <!-- date selection -->
                         <div class="flex justify-between items-center self-stretch">
                            <Button
                                type="button"
                                variant="gray-text"
                                size="sm"
                                iconOnly
                                @click="changeDate(-1)"
                            >
                                <div class="w-full text-gray-500">
                                    <IconChevronLeft size="16" stroke-width="1.25" />
                                </div>
                            </Button>
                            <div class="w-full h-full flex justify-center items-center relative">
                                <span class="text-gray-950 text-center text-sm select-none cursor-pointer font-semibold" @click="toggleCalendar">{{ formatMonthDate(selectedDate) }}</span>
                                <div class="absolute top-10 z-20">
                                    <Calendar
                                        v-if="isCalendarVisible"
                                        v-model="selectedDate"
                                        selectionMode="single"
                                        :manualInput="false"
                                        :maxDate="today"
                                        dateFormat="M dd"
                                        class="w-full"
                                        inline
                                    >
                                    </Calendar>
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="gray-text"
                                size="sm"
                                iconOnly
                                @click="changeDate(1)"
                                :disabled="isNextButtonDisabled"
                                >
                                <IconChevronRight size="16" stroke-width="1.25" />
                            </Button>
                         </div>

                         <div class="flex flex-col items-center gap-1.5 self-stretch">
                            <div class="flex justify-between items-center self-stretch">
                                <span class="text-gray-500 text-xs">{{ $t('public.profit') }}</span>
                                <span class="text-gray-500 text-right text-xs">{{ $t('public.loss') }}</span>
                            </div>
                            <!-- bar -->
                             <div class="w-full h-1 rounded-full relative bg-gray-100">
                                 <!-- Profit bar, starting from the left -->
                                 <div
                                     class="absolute top-0 left-0 h-full rounded-l-full bg-green"
                                     :style="{ width: `${calculateProfitPercentage(profit, profit + loss)}%` }"
                                 ></div>

                                 <!-- Loss bar, starting from the right -->
                                 <div
                                     class="absolute top-0 right-0 h-full rounded-r-full bg-pink"
                                     :style="{ width: `${calculateLossPercentage(loss, profit + loss)}%` }"
                                 ></div>
                             </div>
                            <div class="flex justify-between items-center self-stretch">
                                <span class="text-gray-950 text-sm font-medium">{{ formatAmount(profit) }}%</span>
                                <span class="text-gray-950 text-right text-sm font-medium">{{ formatAmount(loss) }}%</span>
                            </div>
                         </div>
                    </div>
                </div>
            </div>

            <div class="w-full flex flex-col items-center gap-5 md:gap-8">
                <AssetMaster
                    :groupsOptions="groupsOptions"
                />
            </div>

        </div>


    </AuthenticatedLayout>
</template>
