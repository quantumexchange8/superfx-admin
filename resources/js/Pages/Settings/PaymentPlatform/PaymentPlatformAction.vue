<script setup>
import Button from "@/Components/Button.vue";
import {IconAdjustmentsDollar} from "@tabler/icons-vue";
import {ref} from "vue";
import Dialog from "primevue/dialog";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import InputSwitch from "primevue/inputswitch";
import InputText from "primevue/inputtext";
import toast from "@/Composables/toast.js";

const props = defineProps({
    paymentGateway: Object
});

const visible = ref(false);

const openDialog = () => {
    visible.value = true;
}

const form = ref({
    name: props.paymentGateway.name,
    can_deposit: props.paymentGateway.can_deposit,
    can_withdraw: props.paymentGateway.can_withdraw,
    status: props.paymentGateway.status,
});

const paymentStatus = ref(props.paymentGateway.status === 'active');
const formProcessing = ref(false);
const emit = defineEmits(['updated:payment']);

const submitForm = async () => {
    formProcessing.value = true;
    form.errors = {};

    try {
        const response = await axios.patch(route('system.updatePaymentPlatform', props.paymentGateway.id), form.value);

        emit('updated:payment', response.data.payment);

        closeDialog();

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
    visible.value = false;
}

const handleStatus = () => {
    if (paymentStatus.value) {
        form.value.status = 'inactive';
    } else {
        form.value.status = 'active';
    }
}
</script>

<template>
    <Button
        type="button"
        variant="gray-text"
        size="sm"
        icon-only
        pill
        @click.stop="openDialog"
        aria-haspopup="true"
        aria-controls="overlay_tmenu"
    >
        <IconAdjustmentsDollar size="16" stroke-width="1.25" />
    </Button>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.settings')"
        class="dialog-xs sm:dialog-sm"
    >
        <form class="flex flex-col gap-5 items-center self-stretch">
            <div class="flex flex-col items-start gap-1 self-stretch">
                <InputLabel for="name" :value="$t('public.name')" />
                <InputText
                    v-model="form.name"
                    id="name"
                    type="text"
                    class="w-full"
                    :invalid="form?.errors?.name"
                />
                <InputError :message="form?.errors?.name?.[0]" />
            </div>

            <div class="flex flex-col items-start gap-1 self-stretch">
                <div class="flex items-center justify-between w-full">
                    <InputLabel for="name" :value="$t('public.deposit')" />
                    <InputSwitch
                        v-model="form.can_deposit"
                        :disabled="formProcessing"
                        :invalid="form?.errors?.can_deposit"
                    />
                </div>
                <InputError :message="form?.errors?.can_deposit?.[0]" />
            </div>

            <div class="flex flex-col items-start gap-1 self-stretch">
                <div class="flex items-center justify-between w-full">
                    <InputLabel for="name" :value="$t('public.withdrawal')" />
                    <InputSwitch
                        v-model="form.can_withdraw"
                        :disabled="formProcessing"
                        :invalid="form?.errors?.can_withdraw"
                    />
                </div>
                <InputError :message="form?.errors?.can_withdraw?.[0]" />
            </div>

            <div class="flex flex-col items-start gap-1 self-stretch">
                <div class="flex items-center justify-between w-full">
                    <InputLabel for="name" :value="$t('public.status')" />
                    <InputSwitch
                        v-model="paymentStatus"
                        :disabled="formProcessing"
                        :invalid="form?.errors?.status"
                        @click="handleStatus"
                    />
                </div>
                <InputError :message="form?.errors?.status?.[0]" />
            </div>

            <div class="flex justify-end items-center gap-3 md:gap-4 self-stretch">
                <Button
                    type="button"
                    variant="gray-tonal"
                    class="w-full"
                    :disabled="formProcessing"
                    @click.prevent="closeDialog"
                >
                    {{ $t('public.cancel') }}
                </Button>
                <Button
                    type="submit"
                    variant="primary-flat"
                    class="w-full"
                    :disabled="formProcessing"
                    @click.prevent="submitForm"
                >
                    {{ $t('public.confirm') }}
                </Button>
            </div>
        </form>
    </Dialog>
</template>
