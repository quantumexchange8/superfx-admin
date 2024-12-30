<script setup>
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import InputNumber from "primevue/inputnumber";
import Dropdown from "primevue/dropdown";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import Button from "@/Components/Button.vue";
import {useForm} from "@inertiajs/vue3";
import {ref} from "vue";

const props = defineProps({
    profile: Object,
})

const selectedSalesCategory = ref(props.profile.sales_category);
const selectedThreshold = ref(props.profile.bonus_calculation_threshold);
const selectedPeriod = ref(props.profile.calculation_period);
const emit = defineEmits(['update:visible'])

const categories = ref([
    'gross_deposit',
    'net_deposit',
    'trade_volume'
]);

const bonusCalculationThresholdOptions = ref([
    '50',
    '75',
    '100'
])

const bonusCalculationPeriodOptions = ref([
    'every_sunday',
    'every_second_sunday',
    'first_sunday_of_every_month'
])

const form = useForm({
    profile_id: props.profile.id,
    sales_category: '',
    target_amount: Number(props.profile.target_amount),
    bonus: Number(props.profile.bonus_rate),
    bonus_calculation_threshold: '',
    bonus_calculation_period: '',
})

const submitForm = () => {
    form.bonus_calculation_period = selectedPeriod.value;
    form.sales_category = selectedSalesCategory.value;
    form.bonus_calculation_threshold = selectedThreshold.value;

    form.post(route('billboard.editBonusProfile'), {
        onSuccess: () => {
            form.reset();
            emit('update:visible', false);
        }
    })
}
</script>

<template>
    <form>
        <div class="flex flex-col gap-8 items-center self-stretch">
            <div class="flex flex-col gap-3 items-center self-stretch">
                <span class="text-gray-950 text-sm font-bold text-left w-full">{{ $t('public.agent_information') }}</span>
                <div class="flex flex-col md:flex-row gap-3 items-start md:items-center self-stretch">
                    <div class="flex gap-3 items-center self-stretch w-full">
                        <div class="w-[42px] h-[42px] shrink-0 grow-0 rounded-full overflow-hidden">
                            <div v-if="profile.profile_photo">
                                <img :src="profile.profile_photo" alt="Profile Photo" />
                            </div>
                            <div v-else>
                                <DefaultProfilePhoto />
                            </div>
                        </div>
                        <div class="flex flex-col items-center self-stretch text-left">
                            <span class="w-full text-gray-950 text-base font-medium md:font-semibold">{{ profile.name }}</span>
                            <span class="w-full text-gray-500 text-sm max-w-[220px] md:max-w-md truncate">{{ profile.email }}</span>
                        </div>
                    </div>
                    <StatusBadge :value="profile.bonus_badge">
                        {{ $t(`public.${profile.sales_calculation_mode}`) }}
                    </StatusBadge>
                </div>
            </div>

            <div class="flex flex-col gap-3 items-center self-stretch">
                <span class="text-gray-950 text-sm font-bold text-left w-full">{{ $t('public.bonus_structure') }}</span>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-5 w-full">
                    <div class="flex flex-col items-start gap-1 self-stretch w-full">
                        <InputLabel
                            for="sales_category"
                            :value="$t('public.sales_category')"
                            :invalid="!!form.errors.sales_category"
                        />
                        <Dropdown
                            v-model="selectedSalesCategory"
                            :options="categories"
                            :placeholder="$t('public.select_category')"
                            class="w-full"
                            scroll-height="236px"
                            :invalid="!!form.errors.sales_category"
                            disabled
                        >
                            <template #value="slotProps">
                                <div v-if="slotProps.value" class="flex items-center">
                                    <div>{{ $t(`public.${slotProps.value}`) }}</div>
                                </div>
                                <span v-else>
                                            {{ slotProps.placeholder }}
                                        </span>
                            </template>
                            <template #option="slotProps">
                                <div class="flex items-center">
                                    {{ $t(`public.${slotProps.option}`) }}
                                </div>
                            </template>
                        </Dropdown>
                        <InputError :message="form.errors.sales_category" />
                        <span class="text-gray-500 text-xs">{{ $t('public.sales_category_caption') }}</span>
                    </div>
                    <div class="flex flex-col items-start gap-1 self-stretch w-full">
                        <InputLabel
                            for="target_amount"
                            :value="$t('public.set_target_amount') + (selectedSalesCategory === 'trade_volume' ? ' (Ł)' : ' ($)')"
                            :invalid="!!form.errors.target_amount"
                        />
                        <InputNumber
                            inputId="target_amount"
                            v-model="form.target_amount"
                            :min="0"
                            :step="100"
                            fluid
                            :invalid="!!form.errors.target_amount"
                            class="w-full"
                            inputClass="py-3 px-4"
                            placeholder="0"
                        />
                        <InputError :message="form.errors.target_amount" />
                        <span class="text-gray-500 text-xs">{{ $t('public.set_target_amount_caption') }}</span>
                    </div>

                    <div class="flex flex-col items-start gap-1 self-stretch w-full">
                        <InputLabel
                            for="bonus"
                            :value="$t('public.bonus') + (selectedSalesCategory === 'trade_volume' ? ' ($)' : ' (%)')"
                            :invalid="!!form.errors.bonus"
                        />
                        <InputNumber
                            inputId="bonus"
                            v-model="form.bonus"
                            :min="0"
                            :step="100"
                            fluid
                            :invalid="!!form.errors.bonus"
                            class="w-full"
                            inputClass="py-3 px-4"
                            placeholder="0"
                        />
                        <InputError :message="form.errors.bonus" />
                        <span v-if="selectedSalesCategory === 'trade_volume'" class="text-gray-500 text-xs">{{ $t('public.bonus_caption_trade') }}</span>
                        <span v-else class="text-gray-500 text-xs">{{ $t('public.bonus_caption') }}</span>
                    </div>
                    <div class="flex flex-col items-start gap-1 self-stretch w-full">
                        <InputLabel
                            for="bonus_calculation_threshold"
                            :value="$t('public.bonus_calculation_threshold') + (selectedSalesCategory === 'trade_volume' ? ' ($)' : ' (%)')"
                            :invalid="!!form.errors.bonus_calculation_threshold"
                        />
                        <Dropdown
                            v-model="selectedThreshold"
                            :options="bonusCalculationThresholdOptions"
                            :placeholder="$t('public.select_threshold')"
                            class="w-full"
                            scroll-height="236px"
                            :invalid="!!form.errors.bonus_calculation_threshold"
                            :disabled="selectedSalesCategory === 'trade_volume'"
                        >
                            <template #value="slotProps">
                                <div v-if="slotProps.value" class="flex items-center">
                                    <div>{{ slotProps.value }}%</div>
                                </div>
                                <span v-else>
                                            {{ slotProps.placeholder }}
                                        </span>
                            </template>
                            <template #option="slotProps">
                                <div class="flex items-center">
                                    {{ slotProps.option }}%
                                </div>
                            </template>
                        </Dropdown>
                        <InputError :message="form.errors.bonus_calculation_threshold" />
                        <span class="text-gray-500 text-xs">{{ $t('public.bonus_calculation_threshold_caption') }}</span>
                    </div>
                    <div class="flex flex-col items-start gap-1 self-stretch w-full">
                        <InputLabel
                            for="bonusCalculationPeriodOptions"
                            :value="$t('public.bonus_calculation_period')"
                            :invalid="!!form.errors.bonus_calculation_period"
                        />
                        <Dropdown
                            v-model="selectedPeriod"
                            :options="bonusCalculationPeriodOptions"
                            :placeholder="$t('public.select_period')"
                            class="w-full"
                            scroll-height="236px"
                            :invalid="!!form.errors.bonus_calculation_period"
                        >
                            <template #value="slotProps">
                                <div v-if="slotProps.value" class="flex items-center">
                                    <div>{{ $t(`public.${slotProps.value}`) }}</div>
                                </div>
                                <span v-else>
                                            {{ slotProps.placeholder }}
                                        </span>
                            </template>
                            <template #option="slotProps">
                                <div class="flex items-center">
                                    {{ $t(`public.${slotProps.option}`) }}
                                </div>
                            </template>
                        </Dropdown>
                        <InputError :message="form.errors.bonus_calculation_period" />
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-5 md:pt-7 flex flex-col items-end self-stretch">
            <Button
                variant="primary-flat"
                :disabled="form.processing"
                @click.prevent="submitForm"
            >
                {{ $t('public.save') }}
            </Button>
        </div>
    </form>
</template>
