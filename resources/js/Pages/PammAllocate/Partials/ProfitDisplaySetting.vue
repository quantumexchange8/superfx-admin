<script setup>
import {ref, onMounted, watch} from 'vue';
import dayjs from "dayjs";

const props = defineProps({
    expectedGain: String,
    proceedRegenerate: Boolean,
    last_distribution_date: String,
})

let currentDate = props.last_distribution_date
    ? dayjs(props.last_distribution_date)
    : dayjs();

// Initialize the remainingDays as an empty array
const remainingDays = ref([]);
const emit = defineEmits(['update:proceedRegenerate', 'get:daily_profits']);

// Calculate the remaining days in the current month excluding Saturday, Sunday, 25th Dec, and 1st Jan
const calculateRemainingDays = () => {
    remainingDays.value = [];
    let daysInMonth = currentDate.daysInMonth(); // Get the total days in the current month
    let currentDay = currentDate.date(); // Get the current day

    // Calculate remaining days in the current month
    for (let i = currentDay + 1; i <= daysInMonth; i++) {
        const day = currentDate.date(i);
        const dayOfWeek = day.day(); // 0 is Sunday, 6 is Saturday
        const formattedDate = day.format('D/M');

        // Exclude Saturdays, Sundays, 25th Dec, and 1st Jan
        if (
            dayOfWeek !== 0 && // Not Sunday
            dayOfWeek !== 6 && // Not Saturday
            formattedDate !== '25/12' && // Not 25th December
            formattedDate !== '1/1' // Not 1st January
        ) {
            remainingDays.value.push({
                date: formattedDate,
                daily_profit: 0
            });
        }
    }

    // If the remainingDays array is empty, move to the next month
    if (remainingDays.value.length === 0) {
        // Move to the next month
        currentDate = currentDate.add(1, 'month').startOf('month');
        daysInMonth = currentDate.daysInMonth(); // Get total days in the next month

        // Calculate remaining days in the next month
        for (let i = 1; i <= daysInMonth; i++) {
            const day = currentDate.date(i);
            const dayOfWeek = day.day(); // 0 is Sunday, 6 is Saturday
            const formattedDate = day.format('D/M');

            // Exclude Saturdays, Sundays, 25th Dec, and 1st Jan
            if (
                dayOfWeek !== 0 && // Not Sunday
                dayOfWeek !== 6 && // Not Saturday
                formattedDate !== '25/12' && // Not 25th December
                formattedDate !== '1/1' // Not 1st January
            ) {
                remainingDays.value.push({
                    date: formattedDate,
                    daily_profit: 0
                });
            }
        }
    }
};

// Function to shuffle an array (to randomize day order)
const shuffleArray = (array) => {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
};

// Distribute expectedGain across remaining days with balanced logic
const distributeExpectedGain = (gain) => {
    const totalDays = remainingDays.value.length;
    const maxPerDay = gain / 3; // Maximum value per distribution
    const shuffledIndices = Array.from({ length: totalDays }, (_, i) => i);
    shuffleArray(shuffledIndices);

    let remainingGain = gain;
    let negativeStreak = 0;

    for (let i = 0; i < totalDays; i++) {
        const index = shuffledIndices[i];
        let randomValue;

        // Avoid excessive negative streaks
        if (negativeStreak >= 4) {
            randomValue = Math.random() * Math.min(remainingGain, maxPerDay);
            negativeStreak = 0;
        } else {
            randomValue = (Math.random() - 0.5) * Math.min(remainingGain, maxPerDay);
        }

        // Update negative streak counter
        if (randomValue < 0) {
            negativeStreak++;
        } else {
            negativeStreak = 0;
        }

        // Adjust remaining gain
        randomValue = parseFloat(randomValue.toFixed(2));
        remainingGain -= randomValue;
        remainingGain = parseFloat(remainingGain.toFixed(2));
        remainingDays.value[index].daily_profit = randomValue;
    }

    // Distribute any remaining gain evenly to ensure balance
    const remainderPerDay = parseFloat((remainingGain / totalDays).toFixed(2));
    for (let i = 0; i < totalDays; i++) {
        remainingDays.value[i].daily_profit = parseFloat((remainingDays.value[i].daily_profit + remainderPerDay).toFixed(2));
    }

    // Adjust for rounding errors to ensure total sum is exact
    let calculatedSum = remainingDays.value.reduce((acc, day) => acc + day.daily_profit, 0);
    calculatedSum = parseFloat(calculatedSum.toFixed(2));

    if (calculatedSum !== gain) {
        const difference = gain - calculatedSum;
        remainingDays.value[0].daily_profit = parseFloat((remainingDays.value[0].daily_profit + difference).toFixed(2));
    }

    emit('get:daily_profits', remainingDays.value);
};

// Function to regenerate the distribution
watch(() => props.proceedRegenerate, () => {
    distributeExpectedGain(props.expectedGain);
    emit('update:proceedRegenerate', false);
});

// Calculate remaining days on mount
onMounted(() => {
    calculateRemainingDays();
    distributeExpectedGain(props.expectedGain);
});

// Watch for changes to expectedGain
watch(() => props.expectedGain, (newValue) => {
    distributeExpectedGain(newValue);
});
</script>

<template>
    <div class="flex flex-col items-center gap-3 self-stretch">
        <div class="flex justify-between items-center py-2 self-stretch border-b border-gray-200 bg-gray-100">
            <div class="flex items-center px-2 w-full text-gray-950 text-xs font-semibold uppercase">
                {{ $t('public.date') }}
            </div>
            <div class="flex items-center px-2 w-full text-gray-950 text-xs font-semibold uppercase">
                {{ $t('public.daily_profit') }} (%)
            </div>
        </div>

        <div class="flex flex-col items-center self-stretch max-h-[400px] overflow-y-auto">
            <div
                v-for="profitDay in remainingDays"
                class="flex justify-between py-1 items-center self-stretch h-10"
            >
                <div class="text-gray-950 px-2 w-full">
                    {{ profitDay.date }}
                </div>
                <div class="px-2 w-full">
                    <div
                        class="px-3 py-1.5"
                        :class="{
                            'text-gray-400': profitDay.daily_profit === 0 || !profitDay.daily_profit,
                            'text-success-500': profitDay.daily_profit > 0,
                            'text-error-500': profitDay.daily_profit < 0,
                        }"
                    >
                        {{ profitDay.daily_profit ? profitDay.daily_profit : 0 }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
