<script setup>
import { ref, h, watch, computed, onMounted } from "vue";
import Button from '@/Components/Button.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputText from 'primevue/inputtext';
import { useForm, usePage } from '@inertiajs/vue3';
import RadioButton from 'primevue/radiobutton';
import { IconRefresh } from '@tabler/icons-vue';
import { transactionFormat } from '@/Composables/index.js';
import InputNumber from "primevue/inputnumber";
import ProfitDisplaySetting from "@/Pages/PammAllocate/Partials/ProfitDisplaySetting.vue";
import CustomProfitSetting from "@/Pages/PammAllocate/Partials/CustomProfitSetting.vue";

const { formatDate, formatDateTime, formatAmount } = transactionFormat();

const props = defineProps({
    master: Object
})

const selectedMode = ref('automatic');
const emit = defineEmits(['update:visible'])

const form = useForm({
    id: props.master.id,
    expected_gain: '',
    daily_profits: '',
});

const closeDialog = () => {
    emit('update:visible', false);
}

watch(selectedMode, () => {
    expectedGain.value = null
});

const expectedGain = ref();
const dailyProfits = ref();
const proceedRegenerate = ref(false);

const handleRegenerate = () => {
    proceedRegenerate.value = true;
}

const submit = () => {
    form.daily_profits = dailyProfits.value;
    form.profit_generation_mode = selectedMode.value;
    form.post(route('pamm_allocate.addProfitDistribution'), {
        onSuccess: () => {
            closeDialog();
        }
    })
}
</script>

<template>
    <div class="flex flex-col items-center gap-3 self-stretch md:pb-5 md:gap-5">
        <div
            class="flex flex-col items-center gap-3 self-stretch"
            :class="{
                'md:gap-2': selectedMode === 'automatic',
                'md:gap-5': selectedMode === 'custom',
            }"
        >
            <div class="flex flex-col md:flex-row gap-5 self-stretch items-start">
                <div class="flex flex-col items-start gap-1 self-stretch w-full">
                    <InputLabel for="generate_mode" :value="$t('public.generate_mode')" />
                    <div class="w-full flex gap-5 items-center self-stretch py-0.5">
                        <div class="flex items-center gap-2 text-sm text-gray-950 w-full">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full grow-0 shrink-0 hover:bg-gray-100">
                                <RadioButton
                                    v-model="selectedMode"
                                    inputId="auto"
                                    value="automatic"
                                    class="w-5 h-5"
                                />
                            </div>
                            <label for="auto">{{ $t('public.automatic') }}</label>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-950 w-full">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full grow-0 shrink-0 hover:bg-gray-100">
                                <RadioButton
                                    v-model="selectedMode"
                                    inputId="custom"
                                    value="custom"
                                    class="w-5 h-5"
                                />
                            </div>
                            <label for="custom">{{ $t('public.custom') }}</label>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col items-start gap-1 self-stretch md:min-w-60 md:flex-grow w-full">
                    <InputLabel for="expected_gain" :value="$t('public.expected_gain')" />
                    <InputText
                        id="expected_gain"
                        type="number"
                        class="block w-full"
                        v-model="expectedGain"
                        :invalid="!!form.errors.expected_gain"
                        placeholder="0.00%"
                        :disabled="selectedMode === 'custom'"
                    />
                    <InputError :message="form.errors.expected_gain" />
                </div>
            </div>

            <div v-if="selectedMode === 'automatic'" class="w-full flex justify-center items-end md:justify-end">
                <Button
                    type="button"
                    variant="primary-text"
                    size="sm"
                    :disabled="!expectedGain || form.processing"
                    @click="handleRegenerate"
                >
                    <IconRefresh size="20" stroke-width="1.25" />
                    {{ $t('public.generate_again') }}
                </Button>
            </div>

            <!-- Allocate Table -->
            <ProfitDisplaySetting
                v-if="selectedMode === 'automatic'"
                :expectedGain="expectedGain"
                :proceedRegenerate="proceedRegenerate"
                :last_distribution_date="master.last_distribution_date"
                @update:proceedRegenerate="proceedRegenerate = $event"
                @get:daily_profits="dailyProfits = $event"
            />

            <CustomProfitSetting
                v-if="selectedMode === 'custom'"
                :expectedGain="expectedGain"
                :proceedRegenerate="proceedRegenerate"
                :last_distribution_date="master.last_distribution_date"
                @update:proceedRegenerate="proceedRegenerate = $event"
                @get:daily_profits="dailyProfits = $event"
            />
        </div>
    </div>
    <div class="flex justify-end items-center pt-5 self-stretch md:pt-7">
        <Button type="button" variant="primary-flat" size="base" @click.prevent="submit">{{ $t('public.save') }}</Button>
    </div>
</template>
