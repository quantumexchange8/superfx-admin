<script setup>
import InputLabel from "@/Components/InputLabel.vue";
import Button from "@/Components/Button.vue"
import InputError from "@/Components/InputError.vue";
import Dropdown from "primevue/dropdown";
import {ref} from "vue";
import {transactionFormat} from "@/Composables/index.js";
import toast from "@/Composables/toast.js";

const props = defineProps({
    account: Object,
})

const leverages = ref([]);
const emit = defineEmits(['update:visible'])
const {formatAmount} = transactionFormat()

const getOptions = async () => {
    try {
        const response = await axios.get('/getLeverages');
        leverages.value = response.data.leverages;
    } catch (error) {
        console.error('Error changing locale:', error);
    }
};

getOptions();

const form = ref({
    meta_login: props.account.meta_login,
    leverage: props.account.leverage,
})

const formProcessing = ref(false);

const submitForm = async () => {
    formProcessing.value = true;
    form.errors = {};

    try {
        const response = await axios.post(route('member.updateLeverage'), form.value);

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

const closeDialog = () => {
    emit('update:visible', false)
}
</script>

<template>
    <form>
        <div class="flex flex-col items-center gap-8 self-stretch md:gap-10">
            <div class="flex flex-col items-center gap-5 self-stretch">
                <div class="flex flex-col justify-center items-center py-4 px-8 gap-2 self-stretch bg-gray-200">
                    <span class="w-full text-gray-500 text-center text-xs font-medium">#{{ account.meta_login }} - {{ $t('public.available_account_balance') }}</span>
                    <span class="w-full text-gray-950 text-center text-xl font-semibold">$ {{ formatAmount(account?.trading_account.balance ?? 0) }}</span>
                </div>

                <!-- input fields -->
                <div class="flex flex-col items-start gap-1 self-stretch">
                    <InputLabel for="leverage" :value="$t('public.leverage')" />
                    <Dropdown
                        v-model="form.leverage"
                        :options="leverages"
                        optionLabel="name"
                        optionValue="value"
                        :placeholder="$t('public.leverages_placeholder')"
                        class="w-full"
                        scroll-height="236px"
                        :invalid="!!form?.errors?.leverage"
                        :disabled="!leverages.length"
                    />
                    <InputError :message="form?.errors?.leverage?.[0]" />
                </div>
            </div>
        </div>
        <div class="flex justify-end items-center pt-5 gap-4 self-stretch sm:pt-7">
            <Button
                type="button"
                variant="gray-tonal"
                class="w-full md:w-[120px]"
                @click.prevent="closeDialog()"
                :disabled="formProcessing"
            >
                {{ $t('public.cancel') }}
            </Button>
            <Button
                variant="primary-flat"
                class="w-full md:w-[120px]"
                @click.prevent="submitForm"
                :disabled="formProcessing"
            >
                {{ $t('public.confirm') }}
            </Button>
        </div>
    </form>
</template>
