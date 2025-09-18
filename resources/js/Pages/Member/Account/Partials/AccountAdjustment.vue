<script setup>
import {computed, onMounted, ref} from "vue";
import {transactionFormat} from "@/Composables/index.js";
import RadioButton from "primevue/radiobutton";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputNumber from "primevue/inputnumber";
import Textarea from "primevue/textarea";
import Chip from "primevue/chip";
import Button from "@/Components/Button.vue";
import toast from "@/Composables/toast.js";

const props = defineProps({
    account: Object,
    dialogType: String,
})

const isLoading = ref(false);
const data = ref([]);
const {formatAmount} = transactionFormat();
const emit = defineEmits(['update:visible', 'updated:account']);

const getTradingAccountData = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(route('member.getFreshTradingAccountData', {
            meta_login: props.account.meta_login,
            account_type_id: props.account.account_type.id
        }));
        data.value = response.data.data;
        emit('updated:account', data.value);
    } catch (error) {
        console.error('Error update account:', error);
    } finally {
        isLoading.value = false;
    }
}

onMounted(getTradingAccountData);

const form = ref({
    meta_login: props.account.meta_login,
    action: '',
    amount: 0,
    remarks: '',
    type: props.dialogType,
})

const radioOptions = computed(() => {
    const options = {
        account_balance: [
            { label: 'balance_in', value: 'balance_in' },
            { label: 'balance_out', value: 'balance_out' }
        ],
        account_credit: [
            { label: 'credit_in', value: 'credit_in' },
            { label: 'credit_out', value: 'credit_out' }
        ]
    };

    // Return the correct options based on dialogType
    return options[props.dialogType] || [];
});

// Computed Property for Chips
const chips = computed(() => {
    const chipsMapping = {
        account_balance: [
            { label: 'Fix account balance' },
            { label: '修改帳戶餘額' },
        ],
        account_credit: [
            { label: 'Fix account credit' },
            { label: '修改信用餘額' },
        ],
    };

    return chipsMapping[props.dialogType] || [];
});

// Computed Property for Placeholder
const placeholderText = computed(() => {
    const placeholderMapping = {
        account_balance: 'Account balance adjustment',
        account_credit: 'Account credit adjustment',
    };

    return placeholderMapping[props.dialogType] || 'Enter remarks here';
});

const handleChipClick = (label) => {
    form.remarks = label;
};

const closeDialog = () => {
    emit('update:visible', false);
}

const formProcessing = ref(false);

const submitForm = async () => {
    formProcessing.value = true;
    form.errors = {};

    if (form.value.remarks === '') {
        form.value.remarks = placeholderText.value;
    }

    try {
        const response = await axios.post(route('member.accountAdjustment'), form.value);

        closeDialog();

        emit('updated:account', response.data.account);

        toast.add({
            type: 'success',
            title: response.data.title,
            message: response.data.message,
        });
    } catch (error) {
        if (error.response?.status === 422) {
            form.value.errors = error.response.data.errors;
        } else {
            closeDialog();

            const message = error.response?.data?.message || error.message || 'Something went wrong.';
            toast.add({
                type: 'error',
                title: message,
            });
        }
    } finally {
        formProcessing.value = false;
    }
}
</script>

<template>
    <form>
        <div class="flex flex-col gap-5 items-center self-stretch">
            <div class="flex flex-col justify-center items-center px-8 py-4 gap-2 self-stretch bg-gray-200">
                <div class="text-gray-500 text-center text-xs font-medium">
                    #{{ account.meta_login }} - {{ dialogType === 'account_balance' ? $t('public.available_account_balance') : $t('public.available_account_credit') }}
                </div>
                <div v-if="isLoading" class="text-gray-950 text-center text-xl font-semibold">
                    {{ $t('public.loading') }}..
                </div>
                <div v-else class="text-gray-950 text-center text-xl font-semibold">
                    <span v-if="dialogType === 'account_balance'">{{ formatAmount(data.balance) }}</span>
                    <span v-else-if="dialogType === 'account_credit'">{{ formatAmount(data.credit) }}</span>
                </div>
            </div>

            <!-- action -->
            <div class="flex flex-col items-start gap-1 self-stretch">
                <InputLabel for="action" :value="$t('public.action')" />
                <div class="flex items-center gap-10">
                    <div
                        v-for="(action, index) in radioOptions"
                        :key="index"
                        class="flex items-center gap-2 text-sm text-gray-950"
                    >
                        <div class="flex w-8 h-8 p-2 justify-center items-center rounded-full grow-0 shrink-0 hover:bg-gray-100">
                            <RadioButton
                                v-model="form.action"
                                :inputId="action.value"
                                :name="action.value"
                                :value="action.value"
                                class="w-4 h-4"
                            />
                        </div>
                        <label :for="action.value">{{ $t(`public.${action.label}`) }}</label>
                    </div>
                </div>
                <InputError :message="form?.errors?.action?.[0]" />
            </div>

            <!-- amount -->
            <div class="flex flex-col items-start gap-1 self-stretch">
                <InputLabel for="amount" :value="$t('public.amount')" />
                <InputNumber
                    v-model="form.amount"
                    inputId="currency-us"
                    prefix="$ "
                    class="w-full"
                    inputClass="py-3 px-4"
                    :min="0"
                    :step="100"
                    :minFractionDigits="2"
                    fluid
                    autofocus
                    :invalid="!!form?.errors?.amount"
                />
                <InputError :message="form?.errors?.amount?.[0]" />
            </div>

            <!-- remarks -->
            <div class="flex flex-col items-start gap-3 self-stretch">
                <InputLabel for="remarks">Remarks (optional)</InputLabel>
                <div class="flex items-center content-center gap-2 self-stretch flex-wrap">
                    <div v-for="(chip, index) in chips" :key="index">
                        <Chip
                            :label="chip.label"
                            class="hover:bg-gray-50"
                            :class="{
                                    'border-primary-300 bg-primary-50 hover:bg-primary-25 text-primary-500': form.remarks === chip.label,
                                    'text-gray-950': form.remarks !== chip.label,
                                }"
                            @click="handleChipClick(chip.label)"
                        />
                    </div>
                </div>
                <Textarea
                    id="remarks"
                    type="text"
                    class="flex flex-1 self-stretch"
                    v-model="form.remarks"
                    :placeholder="placeholderText"
                    :invalid="!!form?.errors?.remarks"
                    rows="5"
                    cols="30"
                />
                <InputError :message="form?.errors?.remarks?.[0]" />
            </div>
        </div>

        <div class="flex justify-end items-center pt-10 md:pt-7 gap-3 md:gap-4 self-stretch">
            <Button
                type="button"
                variant="gray-tonal"
                class="flex flex-1 md:flex-none md:w-[120px]"
                :disabled="formProcessing"
                @click.prevent="closeDialog"
            >
                {{ $t('public.cancel') }}
            </Button>
            <Button
                type="submit"
                variant="primary-flat"
                class="flex flex-1 md:flex-none md:w-[120px]"
                :disabled="formProcessing || isLoading"
                @click.prevent="submitForm"
            >
                {{ $t('public.confirm') }}
            </Button>
        </div>
    </form>
</template>
