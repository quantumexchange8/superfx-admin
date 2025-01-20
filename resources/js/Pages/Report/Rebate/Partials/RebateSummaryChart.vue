<script setup>
import { ref, watch, computed } from 'vue';
import { transactionFormat } from "@/Composables/index.js";
import { trans, wTrans } from "laravel-vue-i18n";
import Chart from 'primevue/chart';

const { formatAmount } = transactionFormat();

// Define reactive variables with default values
const rebateSummary = ref([]);
const totalRebate = ref(0);

// Define props
const props = defineProps({
    rebateSummary: Array,
    totalVolume: Number,
    totalRebate: Number
});

// Watch for changes in props and update reactive variables
watch(() => props.rebateSummary, (newSummary) => {
    rebateSummary.value = newSummary.length ? newSummary : [];
}, { immediate: true });

watch(() => props.totalRebate, (newRebate) => {
    totalRebate.value = newRebate ?? 0;
}, { immediate: true });

// Function to calculate percentage of each rebate
const calculatePercentages = (rebates, totalRebate) => {
    if (totalRebate === 0) return rebates.map(() => 0);
    return rebates.map(item => (item.rebate / totalRebate) * 100);
};

// Base colors
const baseColors = [
    '#004E62',
    '#297052',
    '#528B5F',
    '#84AD72',
    '#D1E08C',
    '#D1E0A2',
];

// Function to darken color
const darkenColor = (color, percent) => {
    let r = parseInt(color.slice(1, 3), 16);
    let g = parseInt(color.slice(3, 5), 16);
    let b = parseInt(color.slice(5, 7), 16);

    r = Math.max(0, Math.min(255, r - r * percent));
    g = Math.max(0, Math.min(255, g - g * percent));
    b = Math.max(0, Math.min(255, b - b * percent));

    return `#${Math.round(r).toString(16).padStart(2, '0')}${Math.round(g).toString(16).padStart(2, '0')}${Math.round(b).toString(16).padStart(2, '0')}`;
};

// Darken each color by 20%
const darkerColors = baseColors.map(color => darkenColor(color, 0.2));

// Chart data
const chartData = computed(() => {
    if (rebateSummary.value.length === 0 || totalRebate.value === 0) {
        return {
            labels: [trans('public.' + 'no_data')],
            datasets: [
                {
                    data: [100],
                    backgroundColor: ['#F2F4F7'],
                    hoverBackgroundColor: ['#E0E2E5']
                }
            ]
        };
    }

    const percentages = calculatePercentages(rebateSummary.value, totalRebate.value);
    return {
        labels: rebateSummary.value.map(item => trans('public.' + item.symbol_group)),
        datasets: [
            {
                data: percentages,
                backgroundColor: baseColors,
                hoverBackgroundColor: darkerColors
            }
        ]
    };
});

// Chart options
const chartOptions = computed(() => {
    const isEmpty = rebateSummary.value.length === 0 || totalRebate.value === 0;
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        if (isEmpty) return null;
                        const value = context.raw;
                        return ` ${trans('public.percentage')}: ${value.toFixed(2)}%`;
                    }
                }
            }
        }
    };
});

// Legend items
const legendItems = computed(() => {
    return rebateSummary.value.map((item, index) => ({
        label: trans('public.' + item.symbol_group),
        color: baseColors[index]
    }));
});
</script>

<template>
    <div class="w-full flex flex-col items-center py-4 px-4 gap-5 self-stretch rounded-2xl bg-white shadow-toast md:px-8 md:py-6 md:gap-7">
        <div class="h-9 flex items-center flex-shrink-0 self-stretch">
            <span class="flex-grow text-gray-950 text-sm font-semibold md:text-base">{{ $t('public.rebate_percentage_breakdown') }}</span>
        </div>
        <div class="flex flex-col items-center gap-6 self-stretch">
            <Chart 
                type="doughnut" 
                :data="chartData" 
                :options="chartOptions" 
                class="w-[160px] h-[160px] md:w-[280px] md:h-[280px]" 
            />
            <!-- Legend series -->
            <div class="flex justify-center items-center content-center gap-x-5 gap-y-2 align-self-stretch flex-wrap">
                <div v-for="(item, index) in legendItems" :key="index" class="flex items-center gap-2">
                    <div :style="{ backgroundColor: item.color }" class="w-2 h-2 rounded-full"></div>
                    <span class="text-gray-700 text-xs md:text-sm">{{ item.label }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
