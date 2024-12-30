
<script setup>
import { ref, h, watch, computed, onMounted } from "vue";
import Button from '@/Components/Button.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Dropdown from "primevue/dropdown";
import dayjs from "dayjs";
import DailyProfitChart from "@/Pages/PammAllocate/Partials/DailyProfit/DailyProfitChart.vue";

const props = defineProps({
    master: Object
})

const selectedMonth = ref('');
const historyPeriodOptions = ref([]);
const currentYear = dayjs().year();
const generatedMonthlyGain = ref(0)

// Populate historyPeriodOptions with all months of the current year
for (let month = 1; month <= 12; month++) {
    historyPeriodOptions.value.push({
        value: dayjs().month(month - 1).year(currentYear).format('MM/YYYY')
    });
}

selectedMonth.value = dayjs().format('MM/YYYY');
</script>

<template>
    <div class="flex flex-col items-center gap-5 self-stretch flex-grow md:flex-grow-0">
        <!-- Header -->
        <div class="flex flex-col items-start self-stretch md:hidden">
            <span class="h-10 flex flex-col justify-center self-stretch text-gray-950 text-sm font-bold">{{ $t('public.generated_monthly_gain') }}</span>
            <div class="flex items-center gap-5 self-stretch">
                <span class="flex-grow text-gray-950 text-xl font-semibold">{{ generatedMonthlyGain }}%</span>
                <Dropdown
                    v-model="selectedMonth"
                    :options="historyPeriodOptions"
                    optionLabel="value"
                    optionValue="value"
                    class="border-none shadow-none font-medium text-gray-950"
                    scroll-height="236px"
                />
            </div>
        </div>
        <div class="hidden md:flex flex-col items-start self-stretch">
            <div class="flex justify-between items-center self-stretch">
                <span class="text-gray-950 text-sm font-bold">{{ $t('public.generated_monthly_gain') }}</span>
                <Dropdown
                    v-model="selectedMonth"
                    :options="historyPeriodOptions"
                    optionLabel="value"
                    optionValue="value"
                    class="border-none shadow-none font-medium text-gray-950"
                    scroll-height="236px"
                />
            </div>
            <span class="flex-grow text-gray-950 text-xxl font-semibold">{{ generatedMonthlyGain }}%</span>
        </div>

        <!-- chart -->
        <DailyProfitChart
            :selectedMonth="selectedMonth"
            :masterDetail="master"
            @update:monthly-gain="generatedMonthlyGain = $event"
        />
    </div>
</template>
