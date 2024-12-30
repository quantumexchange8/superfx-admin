<script setup>
import Button from '@/Components/Button.vue';
import { IconAdjustmentsHorizontal } from '@tabler/icons-vue';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import { onMounted, ref, watch } from 'vue';
import InputText from 'primevue/inputtext';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    accountTypeId: Number,
    buttonText: String,
})

const visible = ref(false);
const categories = ref(['individual', 'manage']);
const trade_delay_duration_dropdown = ref([
    {name: '0 sec', value: '0'},
    {name: '1 sec', value: '1'},
    {name: '2 sec', value: '2'},
    {name: '3 sec', value: '3'},
    {name: '4 sec', value: '4'},
    {name: '5 sec', value: '5'},
    {name: '6 sec', value: '6'},
    {name: '7 sec', value: '7'},
    {name: '8 sec', value: '8'},
    {name: '9 sec', value: '9'},
    {name: '10 sec', value: '10'},
    {name: '1 min', value: '60'},
    {name: '2 min', value: '120'},
    {name: '3 min', value: '180'},
    {name: '4 min', value: '240'},
    {name: '5 min', value: '300'},
])
const leverages = ref();
const account_type = ref();
const disabling = ref(true);

const getData = async () => {
    try {
        disabling.value = true;
        const response = await axios.get('/account_type/getLevearges');
        leverages.value = response.data.leverages;
        const response_account = await axios.get(`/account_type/findAccountType/${props.accountTypeId}`);
        account_type.value = response_account.data.account_type;
    } catch (error) {
        console.error('Error getting leverages:', error);
    } finally {
        disabling.value = false;
    }
}

const form = useForm({
    account_type_name: '',
    category: '',
    descriptions: { en: '', tw: '' },
    leverage: '',
    trade_delay_duration: '',
    max_account: '',
})

const submitForm = () => {
    form.account_type_name = account_type.value.name
    form.category = account_type.value.category
    form.descriptions = {
        en: account_type.value.description_en,
        tw: account_type.value.description_tw
    }
    form.leverage = account_type.value.leverage
    form.trade_delay_duration = account_type.value.trade_open_duration
    form.max_account = account_type.value.maximum_account_number

    form.post(route('accountType.update', props.accountTypeId), {
        preserveScroll: true,
        onSuccess: () => {
            visible.value = false;
            emit('detailsVisible', false);
        },
        onError: (e) => {
            console.log('Error submit form:', e);
        }
    })
}

onMounted(() => {
    getData()
})

watch(() => props.accountTypeId, () => {
    getData()
})

const emit = defineEmits(['detailsVisible']);
const openSettingDialog = () => {
    visible.value = true;
}
</script>

<template>
    <Button
        v-if="buttonText"
        variant="primary-flat"
        type="button"
        class="w-full"
        @click="openSettingDialog()"
        :disabled="disabling"
    >
        {{ buttonText }}
    </Button>
    <Button
        v-else
        variant="gray-text"
        type="button"
        size="sm"
        iconOnly
        pill
        v-tooltip.bottom="'Setting'"
        @click="visible = true"
        :disabled="disabling"
    >
        <IconAdjustmentsHorizontal size="16" stroke-width="1.25" color="#667085" />
    </Button>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.account_type_setting')"
        class="dialog-xs md:dialog-md"
    >
        <form @submit.prevent="submitForm()">
            <div class="flex flex-col items-center gap-8 self-stretch">
                <div class="flex flex-col items-center gap-3 self-stretch">
                    <div class="self-stretch text-gray-950 text-sm font-bold">
                        {{ $t('public.account_information') }}
                    </div>
                    <div class="w-full flex flex-col gap-1">
                        <div class="grid justify-center items-start content-start gap-3 self-stretch flex-wrap grid-cols-1 md:grid-cols-2 md:gap-5">
                            <div class="flex flex-col items-start gap-1 flex-1">
                                <InputLabel for="account_type_name" :invalid="!!form.errors.account_type_name">
                                    {{ $t('public.account_type_name') }}
                                </InputLabel>
                                <InputText
                                    v-model="account_type.name"
                                    id="account_type_name"
                                    type="text"
                                    class="w-full"
                                    disabled
                                />
                                <InputError :message="form.errors.account_type_name" />
                            </div>
                            <div class="flex flex-col items-start gap-1 flex-1">
                                <InputLabel for="category" :value="$t('public.category')" :invalid="!!form.errors.category" />
                                <Dropdown
                                    v-model="account_type.category"
                                    id="category"
                                    :options="categories"
                                    class="w-full"
                                    :disabled="disabling"
                                />
                                <InputError :message="form.errors.category" />
                            </div>
                            <div class="flex flex-col items-start gap-1 flex-1">
                                <InputLabel for="description" :invalid="!!form.errors.description">
                                    {{ $t('public.description_en') }}
                                </InputLabel>
                                <InputText
                                    v-model="account_type.description_en"
                                    id="description"
                                    type="text"
                                    class="w-full"
                                    placeholder="Tell more about this..."
                                    :disabled="disabling"
                                />
                                <InputError :message="form.errors.descriptions" />
                            </div>
                            <div class="flex flex-col items-start gap-1 flex-1">
                                <InputLabel for="description" :invalid="!!form.errors.description">
                                    {{ $t('public.description_zh') }}
                                </InputLabel>
                                <InputText
                                    v-model="account_type.description_tw"
                                    id="description"
                                    type="text"
                                    class="w-full"
                                    placeholder="Tell more about this..."
                                    :disabled="disabling"
                                />
                                <InputError :message="form.errors.descriptions" />
                            </div>
                        </div>

                        <div class="self-stretch text-gray-500 text-xs">
                            {{ $t('public.description_caption') }}
                        </div>
                    </div>
                </div>
                <div class="flex flex-col items-center gap-3 self-stretch">
                    <div class="self-stretch text-gray-950 text-sm font-bold">
                        {{ $t('public.trading_conditions') }}
                    </div>
                    <div class="flex justify-center items-start content-start gap-5 self-stretch flex-wrap flex-col md:flex-row">
                        <div class="w-full flex flex-col items-start gap-1 flex-1">
                            <InputLabel for="leverage" :value="$t('public.leverage')" :invalid="!!form.errors.leverage" />
                            <Dropdown
                                v-model="account_type.leverage"
                                id="category"
                                :options="leverages"
                                optionLabel="name"
                                optionValue="value"
                                class="w-full"
                                :disabled="disabling"
                            />
                            <InputError :message="form.errors.leverage" />
                        </div>
                        <div class="w-full flex flex-col items-start gap-1 flex-1">
                            <InputLabel for="trade_delay_duration" :value="$t('public.trade_delay_duration')" :invalid="!!form.errors.trade_delay_duration" />
                            <Dropdown
                                v-model="account_type.trade_open_duration"
                                id="trade_delay_duration"
                                :options="trade_delay_duration_dropdown"
                                optionLabel="name"
                                optionValue="value"
                                class="w-full"
                                :disabled="disabling"
                            />
                            <InputError :message="form.errors.trade_delay_duration" />
                        </div>
                    </div>
                </div>
                <div class="flex flex-col items-center gap-3 self-stretch">
                    <div class="self-stretch text-gray-950 text-sm font-bold">
                        {{ $t('public.other_settings') }}
                    </div>
                    <div class="grid justify-center items-start content-start gap-5 self-stretch flex-wrap grid-cols-1 md:grid-cols-2">
                        <div class="flex flex-col items-start gap-1 flex-1">
                            <InputLabel for="max_account" :value="$t('public.maximum_account_creation')" :invalid="!!form.errors.max_account" />
                            <InputText
                                v-model="account_type.maximum_account_number"
                                id="max_account"
                                type="number"
                                class="w-full"
                                placeholder="0"
                                :disabled="disabling"
                            />
                            <InputError :message="form.errors.max_account" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="pt-5 md:pt-7 flex flex-col items-end self-stretch">
                <Button
                    variant="primary-flat"
                    :disabled="form.processing || disabling"
                >
                    {{ $t('public.save') }}
                </Button>
            </div>
        </form>
    </Dialog>
</template>
