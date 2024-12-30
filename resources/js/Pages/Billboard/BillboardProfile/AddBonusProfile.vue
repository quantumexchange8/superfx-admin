<script setup>
import Button from "@/Components/Button.vue"
import {IconPlus} from "@tabler/icons-vue";
import {ref, watch} from "vue";
import Dialog from "primevue/dialog";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputNumber from "primevue/inputnumber";
import {useForm} from "@inertiajs/vue3";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import Dropdown from "primevue/dropdown";
import RadioButton from "primevue/radiobutton";

const visible = ref(false);
const selectedMode = ref('personal_sales');
const selectedSalesCategory = ref('');
const selectedThreshold = ref('');

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
    agent: '',
    sales_calculation_mode: '',
    sales_category: '',
    target_amount: null,
    bonus: null,
    bonus_calculation_threshold: '',
    bonus_calculation_period: 'first_sunday_of_every_month',
})

const agents = ref();
const getAgents = async () => {
    try {
        const agentResponse = await axios.get('/billboard/getAgents');
        agents.value = agentResponse.data.users;
    } catch (error) {
        console.error('Error fetching agents:', error);
    }
};

getAgents();

watch(selectedSalesCategory, () => {
    if (selectedSalesCategory.value === 'trade_volume') {
        selectedThreshold.value = '100'
    }
})

const submitForm = () => {
    form.sales_calculation_mode = selectedMode.value;
    form.sales_category = selectedSalesCategory.value;
    form.bonus_calculation_threshold = selectedThreshold.value;

    form.post(route('billboard.createBonusProfile'), {
        onSuccess: () => {
            form.reset();
            selectedMode.value = 'personal_sales';
            selectedSalesCategory.value = '';
            selectedThreshold.value = '';
            visible.value = false;
        }
    });
}
</script>

<template>
    <Button
        type="button"
        variant="primary-flat"
        class='w-full md:w-auto'
        @click="visible = true"
    >
        <IconPlus size="20" color="#ffffff" stroke-width="1.25" />
        {{ $t('public.new_bonus_profile') }}
    </Button>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.new_bonus_profile')"
        class="dialog-xs md:dialog-md"
    >
        <form>
            <div class="flex flex-col gap-8 items-center self-stretch">
                <div class="flex flex-col gap-3 items-center self-stretch">
                    <span class="text-gray-950 text-sm font-bold text-left w-full">{{ $t('public.agent_information') }}</span>
                    <div class="flex flex-col gap-5 items-center self-stretch">
                        <div class="flex flex-col items-start gap-1 self-stretch">
                            <InputLabel
                                for="agent"
                                :value="$t('public.agent')"
                                :invalid="!!form.errors.sales_calculation_mode"
                            />
                            <Dropdown
                                id="agent"
                                v-model="form.agent"
                                :options="agents"
                                filter
                                :filterFields="['name']"
                                optionLabel="name"
                                :placeholder="$t('public.select_agent_placeholder')"
                                class="w-full"
                                scroll-height="236px"
                                :invalid="!!form.errors.agent"
                            >
                                <template #value="slotProps">
                                    <div v-if="slotProps.value" class="flex items-center gap-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-5 h-5 rounded-full overflow-hidden">
                                                <template v-if="slotProps.value.profile_photo">
                                                    <img :src="slotProps.value.profile_photo" alt="profile_picture" />
                                                </template>
                                                <template v-else>
                                                    <DefaultProfilePhoto />
                                                </template>
                                            </div>
                                            <div>{{ slotProps.value.name }}</div>
                                        </div>
                                    </div>
                                    <span v-else class="text-gray-400">
                                            {{ slotProps.placeholder }}
                                    </span>
                                </template>
                                <template #option="slotProps">
                                    <div class="flex items-center gap-2">
                                        <div class="w-5 h-5 rounded-full overflow-hidden">
                                            <template v-if="slotProps.option.profile_photo">
                                                <img :src="slotProps.option.profile_photo" alt="profile_picture" />
                                            </template>
                                            <template v-else>
                                                <DefaultProfilePhoto />
                                            </template>
                                        </div>
                                        <div>{{ slotProps.option.name }}</div>
                                    </div>
                                </template>
                            </Dropdown>
                            <InputError :message="form.errors.agent" />
                        </div>

                        <div class="flex flex-col items-start gap-1 self-stretch w-full">
                            <InputLabel
                                for="sales_calculation_mode"
                                :value="$t('public.sales_calculation_mode')"
                                :invalid="!!form.errors.sales_calculation_mode"
                            />
                            <div class="w-full flex gap-4 md:gap-5 items-center self-stretch">
                                <div class="flex items-center gap-2 text-sm text-gray-950">
                                    <div class="flex items-center justify-center w-8 h-8 md:w-10 md:h-10 rounded-full grow-0 shrink-0 hover:bg-gray-100">
                                        <RadioButton
                                            v-model="selectedMode"
                                            inputId="personal_sales"
                                            value="personal_sales"
                                            class="w-5 h-5"
                                        />
                                    </div>
                                    <label for="personal_sales">{{ $t('public.personal_sales') }}</label>
                                </div>
                                <div class="flex items-center gap-2 text-sm text-gray-950">
                                    <div class="flex items-center justify-center w-8 h-8 md:w-10 md:h-10 rounded-full grow-0 shrink-0 hover:bg-gray-100">
                                        <RadioButton
                                            v-model="selectedMode"
                                            inputId="group_sales"
                                            value="group_sales"
                                            class="w-5 h-5"
                                        />
                                    </div>
                                    <label for="group_sales">{{ $t('public.group_sales') }}</label>
                                </div>
                            </div>
                            <span class="text-gray-500 text-xs">{{ $t('public.sales_calculation_mode_caption') }}</span>
                        </div>
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
                                v-model="form.bonus_calculation_period"
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
                    {{ $t('public.create') }}
                </Button>
            </div>
        </form>
    </Dialog>
</template>
