<script setup>
import {ref} from "vue";
import { CreditCardEdit01Icon } from '@/Components/Icons/outline';
import Button from '@/Components/Button.vue';
import Dialog from 'primevue/dialog';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputNumber from 'primevue/inputnumber';
import RadioButton from 'primevue/radiobutton';
import Chip from 'primevue/chip';
import Textarea from 'primevue/textarea';
import {useForm} from '@inertiajs/vue3';
import { transactionFormat } from "@/Composables/index.js";

const props = defineProps({
    type: String,
    rebateWallet: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    id: '',
    action: 'rebate_in',
    amount: 0,
    remarks: '',
});

const { formatAmount } = transactionFormat();

const visible = ref(false);

const openDialog = () => {
    visible.value = true
};

const closeDialog = () => {
    visible.value = false;
    form.reset();
};

const submit = () => {
    if (form.remarks === '') {
        form.remarks = 'Rebate balance adjustment.'
    }

    if (props.rebateWallet) {
        form.id = props.rebateWallet.id;
    }
    form.post(route('member.walletAdjustment'), {
        onSuccess: () => {
            closeDialog();
        },
    });
};

const chips = ref([
    { label: 'Fix rebate balance' },
    { label: '修改返傭餘額' },
]);

const handleChipClick = (label) => {
    form.remarks = label;
};
</script>

<template>
    <Button
        type="button"
        iconOnly
        size="sm"
        variant="gray-outlined"
        pill
        @click="openDialog"
    >
        <CreditCardEdit01Icon class="w-4 h-4 text-gray-950" />
    </Button>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.rebate_adjustment')"
        class="dialog-xs md:dialog-sm"
    >
        <form>
            <div class="flex flex-col gap-5">
                <div class="flex flex-col justify-center items-center px-8 py-4 gap-2 self-stretch bg-logo">
                    <div class="text-gray-100 text-center text-xs font-medium">{{ $t('public.rebate_balance') }}</div>
                    <div class="text-white text-center text-xl font-semibold">$ {{ formatAmount(rebateWallet.balance) }}</div>
                </div>

                <!-- action -->
                <div class="flex flex-col items-start gap-1 self-stretch">
                    <InputLabel for="action" :value="$t('public.action')" />
                    <div class="flex items-center gap-5">
                        <div class="flex items-center gap-2 text-sm text-gray-950">
                            <div class="flex p-2 justify-center items-center">
                                <RadioButton v-model="form.action" inputId="rebate_in" :name="$t('public.rebate_in')" value="rebate_in" class="w-4 h-4" />
                            </div>
                            <label for="rebate_in">{{ $t('public.rebate_in') }}</label>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-950">
                            <div class="flex p-2 justify-center items-center">
                                <RadioButton v-model="form.action" inputId="rebate_out" :name="$t('public.rebate_out')" value="rebate_out" class="w-4 h-4" />
                            </div>
                            <label for="rebate_out">{{ $t('public.rebate_out') }}</label>
                        </div>
                    </div>
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
                    <InputLabel for="remarks">{{ $t('public.remarks_optional') }}</InputLabel>
                    <div class="flex items-center content-center gap-2 self-stretch flex-wrap">
                        <div v-for="(chip, index) in chips" :key="index">
                            <Chip
                                :label="chip.label"
                                class="text-gray-950"
                                :class="{
                                    'border-primary-300 bg-primary-50 text-primary-500 hover:bg-primary-50': form.remarks === chip.label,
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
                        :placeholder="$t('public.rebate_balance_adjustment')"
                        :invalid="!!form.errors.remarks"
                        rows="5"
                        cols="30"
                    />
                </div>
            </div>
            <div class="flex justify-end items-center pt-10 md:pt-7 gap-3 md:gap-4 self-stretch">
                <Button
                    type="button"
                    variant="gray-tonal"
                    class="flex flex-1 md:flex-none md:w-[120px]"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                    @click.prevent="closeDialog"
                >
                    {{ $t('public.cancel') }}
                </Button>
                <Button
                    variant="primary-flat"
                    class="flex flex-1 md:flex-none md:w-[120px]"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ $t('public.confirm') }}
                </Button>
            </div>
        </form>
    </Dialog>
</template>
