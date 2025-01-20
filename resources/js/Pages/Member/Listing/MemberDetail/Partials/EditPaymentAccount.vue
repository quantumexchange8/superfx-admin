<script setup>
import Button from "@/Components/Button.vue";
import Dropdown from "primevue/dropdown";
import {computed, onMounted, ref, watch} from "vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import InputText from "primevue/inputtext";
import {useForm} from "@inertiajs/vue3";
import {IconCircleCheckFilled} from "@tabler/icons-vue";

const props = defineProps({
    paymentAccount: Object,
});

const emit = defineEmits(['update:visible'])

const closeDialog = () => {
    emit('update:visible', false);
}

const banks = ref([]);
const selectedBank = ref({ bank_name: '', bank_code: '' });
const loadingBank = ref(false);

const getResults = async () => {
    loadingBank.value = true;
    try {
        const response = await axios.get('/member/getFilterData');
        banks.value = response.data.banks;

        if (props.paymentAccount.payment_platform === 'bank') {
            selectedBank.value = banks.value.find(
                (bank) => bank.bank_code === props.paymentAccount.bank_code
            );
        }

    } catch (error) {
        console.error('Error changing locale:', error);
    } finally {
        loadingBank.value = false;
    }
};

getResults();

const form = useForm({
    payment_account_id: props.paymentAccount.id,
    payment_platform: '',
    payment_account_name: props.paymentAccount.payment_account_name,
    payment_platform_name: '',
    payment_account_type: '',
    account_no: props.paymentAccount.account_no,
    country: null,
    currency: '',
    bank_code: '',
});

const selectedPaymentAccountType = ref(props.paymentAccount.payment_platform);
const paymentAccountTypes = ref([
    'bank',
    'crypto'
]);
const selectPaymentAccountType = (type) => {
    if (selectedPaymentAccountType !== type) {
        form.payment_account_name = '';
        form.account_no = '';
        form.bank_code = '';
    }
    selectedPaymentAccountType.value = type;
}

// bank
const selectedType = ref('')
const accountTypes = ref([
    'account',
    'card'
]);

// bank
const selectType = (type) => {
    selectedType.value = type;
};

// crypto
const selectedCryptoNetwork = ref('');
const cryptoNetworks = ref(['erc20', 'trc20']);
const selectCryptoNetwork = (type) => {
    selectedCryptoNetwork.value = type;
};

const selectValue = (type) => {
    if (selectedPaymentAccountType.value === 'bank') {
        selectType(type);
    } else {
        selectCryptoNetwork(type);
    }
};

onMounted(() => {
    selectValue(props.paymentAccount.payment_account_type);
});

const submit = () => {
    form.payment_platform = selectedPaymentAccountType.value;

    if (selectedPaymentAccountType.value === 'bank') {
        form.bank_code = selectedBank.value.bank_code;
        form.payment_platform_name = selectedBank.value.bank_name;
        form.payment_account_type = selectedType.value;
    } else {
        form.payment_platform_name = selectedCryptoNetwork.value;
        form.payment_account_type = selectedCryptoNetwork.value;
    }

    form.post(route('member.updatePaymentAccount'), {
        onSuccess: () => {
            closeDialog();
            form.reset();
        },
        preserveScroll: true
    })
}
</script>

<template>
    <form>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 md:gap-5">
            <!-- Account type-->
            <div class="flex flex-col items-start gap-1 self-stretch md:col-span-2">
                <InputLabel for="payment_account_type" :value="$t('public.payment_account_type')" />
                <div class="flex items-start gap-5 self-stretch">
                    <div
                        v-for="type in paymentAccountTypes"
                        :key="type"
                        @click="selectPaymentAccountType(type)"
                        class="group col-span-1 items-start py-3 px-4 gap-1 self-stretch rounded-lg border shadow-input transition-colors duration-300 select-none cursor-pointer w-full"
                        :class="{
                                'bg-primary-50 border-primary-500': selectedPaymentAccountType === type,
                                'bg-white border-gray-300 hover:bg-primary-50 hover:border-primary-500': selectedPaymentAccountType !== type,
                            }"
                    >
                        <div class="flex items-center gap-3 self-stretch">
                                <span
                                    class="flex-grow text-sm font-semibold transition-colors duration-300 group-hover:text-primary-700"
                                    :class="{
                                        'text-primary-700': selectedPaymentAccountType === type,
                                        'text-gray-950': selectedPaymentAccountType !== type
                                    }"
                                >
                                    {{ $t(`public.${type}`) }}
                                </span>
                            <IconCircleCheckFilled v-if="selectedPaymentAccountType === type" size="20" stroke-width="1.25" color="#06D001" />
                        </div>
                    </div>
                </div>
                <InputError :message="form.errors.payment_platform" />
            </div>

            <!-- Bank Account type-->
            <div
                v-if="selectedPaymentAccountType === 'bank'"
                class="flex flex-col items-start gap-1 self-stretch md:col-span-2"
            >
                <InputLabel for="bank_account_type" :value="$t('public.bank_account_type')" />
                <div class="flex items-start gap-5 self-stretch">
                    <div
                        v-for="type in accountTypes"
                        :key="type"
                        @click="selectType(type)"
                        class="group col-span-1 items-start py-3 px-4 gap-1 self-stretch rounded-lg border shadow-input transition-colors duration-300 select-none cursor-pointer w-full"
                        :class="{
                                'bg-primary-50 border-primary-500': selectedType === type,
                                'bg-white border-gray-300 hover:bg-primary-50 hover:border-primary-500': selectedType !== type,
                            }"
                    >
                        <div class="flex items-center gap-3 self-stretch">
                                <span
                                    class="flex-grow text-sm font-semibold transition-colors duration-300 group-hover:text-primary-700"
                                    :class="{
                                        'text-primary-700': selectedType === type,
                                        'text-gray-950': selectedType !== type
                                    }"
                                >
                                    {{ $t(`public.${type}`) }}
                                </span>
                            <IconCircleCheckFilled v-if="selectedType === type" size="20" stroke-width="1.25" color="#06D001" />
                        </div>
                    </div>
                </div>
                <InputError :message="form.errors.payment_account_type" />
            </div>

            <!-- Crypto Account type-->
            <div
                v-if="selectedPaymentAccountType === 'crypto'"
                class="flex flex-col items-start gap-1 self-stretch md:col-span-2"
            >
                <InputLabel for="crypto_network" :value="$t('public.crypto_network')" />
                <div class="flex items-start gap-5 self-stretch">
                    <div
                        v-for="type in cryptoNetworks"
                        :key="type"
                        @click="selectCryptoNetwork(type)"
                        class="group col-span-1 items-start py-3 px-4 gap-1 self-stretch rounded-lg border shadow-input transition-colors duration-300 select-none cursor-pointer w-full"
                        :class="{
                                'bg-primary-50 border-primary-500': selectedCryptoNetwork === type,
                                'bg-white border-gray-300 hover:bg-primary-50 hover:border-primary-500': selectedCryptoNetwork !== type,
                            }"
                    >
                        <div class="flex items-center gap-3 self-stretch">
                                <span
                                    class="flex-grow text-sm font-semibold transition-colors duration-300 group-hover:text-primary-700"
                                    :class="{
                                        'text-primary-700': selectedCryptoNetwork === type,
                                        'text-gray-950': selectedCryptoNetwork !== type
                                    }"
                                >
                                    <span class="uppercase">{{ type }}</span>
                                </span>
                            <IconCircleCheckFilled v-if="selectedCryptoNetwork === type" size="20" stroke-width="1.25" color="#06D001" />
                        </div>
                    </div>
                </div>
                <InputError v-if="form.errors.payment_account_type" :message="form.errors.payment_account_type" />
            </div>

            <!-- Bank Name -->
            <div
                v-if="selectedPaymentAccountType === 'bank'"
                class="flex flex-col gap-1 items-start self-stretch"
            >
                <InputLabel
                    for="bank_name"
                    :value="$t('public.bank_name')"
                />
                <Dropdown
                    v-model="selectedBank"
                    :options="banks"
                    filter
                    :filterFields="['bank_name', 'bank_code']"
                    optionLabel="bank_name"
                    :placeholder="$t('public.bank_name')"
                    class="w-full"
                    scroll-height="236px"
                    :loading="loadingBank"
                    :invalid="!!form.errors.payment_platform_name"
                >
                    <template #value="slotProps">
                        <div v-if="slotProps.value.bank_name" class="flex items-center">
                            <div>{{ slotProps.value.bank_name }}</div>
                        </div>
                        <span v-else>
                                {{ $t('public.bank_placeholder') }}
                            </span>
                    </template>
                    <template #option="slotProps">
                        <div class="flex items-center w-[250px] md:w-full overflow-x-auto">
                            <div>{{ slotProps.option.bank_name }} <span class="text-gray-500">( {{ slotProps.option.bank_code }} )</span></div>
                        </div>
                    </template>
                </Dropdown>
                <InputError :message="form.errors.payment_platform_name" />
            </div>

            <!-- Bank Code -->
            <div
                v-if="selectedPaymentAccountType === 'bank'"
                class="flex flex-col gap-1 items-start self-stretch"
            >
                <InputLabel
                    for="bank_code"
                    :value="$t('public.bank_code')"
                />
                <InputText
                    id="bank_code"
                    type="text"
                    class="block w-full"
                    v-model="selectedBank.bank_code"
                    :placeholder="$t('public.bank_code')"
                    disabled
                />
            </div>

            <!-- Account name -->
            <div class="flex flex-col gap-1 items-start self-stretch">
                <InputLabel
                    for="payment_account_name"
                    :value="selectedPaymentAccountType === 'crypto'
                            ? $t('public.wallet_name')
                            : (selectedType === 'account' ? $t('public.account_name') : $t('public.card_name'))"
                />
                <InputText
                    id="payment_account_name"
                    type="text"
                    class="block w-full"
                    v-model="form.payment_account_name"
                    :invalid="!!form.errors.payment_account_name"
                />
                <InputError :message="form.errors.payment_account_name" />
            </div>

            <!-- Account no -->
            <div class="flex flex-col gap-1 items-start self-stretch">
                <InputLabel
                    for="account_no"
                    :value="selectedPaymentAccountType === 'crypto'
                            ? $t('public.token_address')
                            : (selectedType === 'account' ? $t('public.account_no') : $t('public.card_no'))"
                />
                <InputText
                    id="account_no"
                    type="text"
                    class="block w-full"
                    v-model="form.account_no"
                    :invalid="!!form.errors.account_no"
                />
                <InputError :message="form.errors.account_no" />
            </div>
        </div>

        <div class="pt-5 flex justify-end">
            <Button
                type="submit"
                class="flex justify-center"
                variant="primary-flat"
                :disabled="form.processing"
                @click="submit"
            >
                {{ $t('public.save') }}
            </Button>
        </div>
    </form>
</template>
