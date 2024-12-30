<script setup>
import InputNumber from "primevue/inputnumber";
import dayjs from "dayjs";
import {computed, ref} from "vue";
import Button from "@/Components/Button.vue"
import {IconPlus, IconCircleX} from "@tabler/icons-vue"

const props = defineProps({
    expectedGain: String,
    proceedRegenerate: Boolean,
    last_distribution_date: String,
})

// Initialize the remainingDays as an empty array
const remainingDays = ref([]);
const emit = defineEmits(['update:proceedRegenerate', 'get:daily_profits']);
const lastAddedDate = ref(
    props.last_distribution_date ? dayjs(props.last_distribution_date) : dayjs()
);

const addDividend = () => {
    let nextDay = lastAddedDate.value.add(1, 'day'); // Start from the next day of the last added date

    while (true) {
        const dayOfWeek = nextDay.day();
        const formattedDate = nextDay.format('DD/MM');

        if (
            dayOfWeek !== 0 && // Not Sunday
            dayOfWeek !== 6 && // Not Saturday
            formattedDate !== '25/12' && // Not 25th December
            formattedDate !== '01/01' // Not 1st January
        ) {
            remainingDays.value.push({
                date: nextDay.format('D/M'),
                daily_profit: 0, // Default value for daily_profit
            });

            emit('get:daily_profits', remainingDays.value);

            lastAddedDate.value = nextDay; // Update the last added date
            break; // Exit loop after pushing the valid date
        }

        nextDay = nextDay.add(1, 'day'); // Move to the next day
    }
};

addDividend();

const removeDividend = (index) => {
    if (index > 0 && index < remainingDays.value.length) {
        remainingDays.value.splice(index, 1);
    }
};

const totalDailyProfit = computed(() => {
    return remainingDays.value.reduce((total, day) => {
        return total + (parseFloat(day.daily_profit) || 0);
    }, 0);
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
                v-for="(profitDay, index) in remainingDays"
                class="flex justify-between py-1 items-center self-stretch h-10"
            >
                <div class="text-gray-950 px-2 w-full">
                    {{ profitDay.date }}
                </div>
                <div class="flex items-center gap-1 px-2 w-full">
                    <InputNumber
                        v-model="profitDay.daily_profit"
                        :minFractionDigits="2"
                        fluid
                        inputClass="py-1.5 px-3 w-20"
                    />
                    <Button
                        type="button"
                        variant="error-text"
                        pill
                        icon-only
                        size="sm"
                        @click="removeDividend(index)"
                        :disabled="index === 0"
                    >
                        <IconCircleX size="16" />
                    </Button>
                </div>
            </div>

            <div class="flex gap-3 py-3 text-sm font-semibold text-gray-950">
                <span>{{ $t('public.total') }}: </span>
                <span
                    :class="{
                            'text-gray-400': totalDailyProfit === 0 || !totalDailyProfit,
                            'text-success-500': totalDailyProfit > 0,
                            'text-error-500': totalDailyProfit < 0,
                        }"
                >{{ totalDailyProfit.toFixed(2) }}%</span>
            </div>
        </div>

        <Button
            type="button"
            variant="primary-outlined"
            size="sm"
            class="w-full"
            @click="addDividend"
        >
            <IconPlus size="16" />
            {{ $t('public.add_another') }}
        </Button>
    </div>
</template>
