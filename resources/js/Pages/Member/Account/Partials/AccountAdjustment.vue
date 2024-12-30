<script setup>
import {computed, onMounted, ref} from "vue";
import {transactionFormat} from "@/Composables/index.js";
import RadioButton from "primevue/radiobutton";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import {useForm} from "@inertiajs/vue3";
import InputNumber from "primevue/inputnumber";
import Textarea from "primevue/textarea";
import Chip from "primevue/chip";
import Button from "@/Components/Button.vue";
import {IconAlertCircle} from "@tabler/icons-vue"

const props = defineProps({
    account: Object,
    dialogType: String,
})

const currentAmount = ref(null);
const {formatAmount} = transactionFormat();
const emit = defineEmits(['update:visible'])
const getTradingAccountData = async () => {
    try {
        const response = await axios.get(`/member/getTradingAccountData?meta_login=${props.account.meta_login}`);
        currentAmount.value = response.data.currentAmount;
    } catch (error) {
        console.error('Error update account:', error);
    }
}

onMounted(getTradingAccountData);

const form = useForm({
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

const submitForm = () => {
    if (form.remarks === '') {
        form.remarks = placeholderText.value;
    }

    form.post(route('member.accountAdjustment'), {
        onSuccess: () => {
            closeDialog();
            form.reset();
        },
    });
}
</script>

<template>
    <form>
        <div class="flex flex-col gap-5 items-center self-stretch">
            <div class="flex flex-col justify-center items-center px-8 py-4 gap-2 self-stretch bg-gray-200">
                <div class="text-gray-500 text-center text-xs font-medium">
                    #{{ account.meta_login }} - {{ dialogType === 'account_balance' ? $t('public.available_account_balance') : $t('public.available_account_credit') }}
                </div>
                <div v-if="currentAmount === null" class="animate-pulse">
                    <div class="h-3 bg-gray-400 rounded-full w-28 my-1"></div>
                </div>
                <div v-else class="text-gray-950 text-center text-xl font-semibold">
                    <span v-if="dialogType === 'account_balance'">$ {{ formatAmount(currentAmount['account_balance'] - currentAmount['account_credit']) }}</span>
                    <span v-else>$ {{ formatAmount(currentAmount[dialogType]) }}</span>
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
                <InputError :message="form.errors.action" />
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
                    :invalid="!!form.errors.amount"
                />
                <InputError :message="form.errors.amount" />
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
                    :invalid="!!form.errors.remarks"
                    rows="5"
                    cols="30"
                />
                <InputError :message="form.errors.remarks" />
            </div>
        </div>

        <div class="flex justify-end items-center pt-10 md:pt-7 gap-3 md:gap-4 self-stretch">
            <Button
                variant="gray-tonal"
                class="flex flex-1 md:flex-none md:w-[120px]"
                :disabled="form.processing"
                @click.prevent="closeDialog"
            >
                Cancel
            </Button>
            <Button
                variant="primary-flat"
                class="flex flex-1 md:flex-none md:w-[120px]"
                :disabled="form.processing || currentAmount === null"
                @click.prevent="submitForm"
            >
                Confirm
            </Button>
        </div>
    </form>
</template>
