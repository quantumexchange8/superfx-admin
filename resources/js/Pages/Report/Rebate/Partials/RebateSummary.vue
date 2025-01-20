<script setup>
import { ref, watch, computed } from 'vue';
import { transactionFormat } from "@/Composables/index.js";

const { formatAmount } = transactionFormat();

// Define reactive variables with default values
const rebateSummary = ref([]);
const totalVolume = ref(0);
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

watch(() => props.totalVolume, (newVolume) => {
    totalVolume.value = newVolume ?? 0;
}, { immediate: true });

watch(() => props.totalRebate, (newRebate) => {
    totalRebate.value = newRebate ?? 0;
}, { immediate: true });

</script>

<template>
    <div class="w-full flex flex-col items-center py-4 px-4 gap-2 self-stretch rounded-2xl bg-white shadow-toast md:px-8 md:py-6 md:gap-3">
        <!-- Display total volume and total rebate -->
        <div class="flex items-center gap-3 self-stretch md:gap-5">
            <div class="flex flex-col items-center py-2 gap-2 flex-grow md:py-3">
                <span class="self-stretch text-gray-500 text-center text-sm">
                    {{ `${$t('public.total_trade_volume')}&nbsp;(Ł)` }}
                </span>
                <span class="text-gray-950 text-lg font-semibold md:text-xxl">
                    {{ formatAmount(totalVolume) }}
                </span>
            </div>
            <div class="w-[1px] h-[52px] rounded-full bg-gray-300"></div>
            <div class="flex flex-col items-center py-2 gap-2 flex-grow md:py-3">
                <span class="self-stretch text-gray-500 text-center text-sm">
                    {{ `${$t('public.total_rebate_earned')}&nbsp;($)` }}
                </span>
                <span class="text-gray-950 text-lg font-semibold md:text-xxl">
                    {{ formatAmount(totalRebate) }}
                </span>
            </div>
        </div>
        <div class="flex flex-col justify-center items-center self-stretch">
            <!-- Loop through rebateSummary -->
            <div v-for="(item, index) in rebateSummary" :key="index" class="flex items-center py-3 gap-4 self-stretch md:gap-5">
                <!-- <img :src="`/img/rebate/3d-${item.symbol_group}.svg`"  alt=""  class="w-9 h-9 flex-shrink-0 md:w-10 md:h-10"> -->
                <!-- sm -->
                <div class="w-full flex flex-col items-start md:hidden">
                    <span class="self-stretch truncate text-gray-950 text-sm font-semibold">
                        {{ $t('public.' + item.symbol_group) }}
                    </span>
                    <span class="self-stretch text-gray-500 text-sm">
                        {{ item.volume > 0 ? `${formatAmount(item.volume)}&nbsp;Ł` : '-' }}
                    </span>
                </div>
                <!-- md -->
                <span class="w-full hidden md:block truncate text-gray-950 text-base font-medium">
                    {{ $t('public.' + item.symbol_group) }}
                </span>
                <span class="w-full hidden md:block text-right text-gray-950 text-base">
                    {{ item.volume > 0 ? `${formatAmount(item.volume)}&nbsp;Ł` : '-' }}
                </span>
                <span class="w-full truncate text-gray-950 text-right font-semibold md md:font-normal">
                    {{ item.rebate > 0 ? `$&nbsp;${formatAmount(item.rebate)}` : '-' }}
                </span>
            </div>
        </div>
    </div>
</template>
