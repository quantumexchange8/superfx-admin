<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Password from 'primevue/password';
import Button from "@/Components/Button.vue";
import {ref} from "vue";
import toast from "@/Composables/toast.js";

const props = defineProps({
    account: Object,
})

const emit = defineEmits(['update:visible'])

const form = ref({
    id: props.account.id,
    master_password: '',
    investor_password: '',
    meta_login: props.account.meta_login,
    user_id: props.account.user_id,
})

const closeDialog = () => {
    emit('update:visible', false);
}

const formProcessing = ref(false);

const submitForm = async () => {
    formProcessing.value = true;
    form.errors = {};

    try {
        const response = await axios.post(route('member.change_password'), form.value);

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
        <div class="flex flex-col gap-5 items-center self-stretch py-4 md:py-6">
            <div class="flex flex-col items-start gap-2 self-stretch">
                <InputLabel for="master_password" :value="$t('public.master_password')" :invalid="!!form?.errors?.master_password" />
                <Password
                    v-model="form.master_password"
                    toggleMask
                    :feedback="false"
                    placeholder="••••••••"
                    :invalid="!!form?.errors?.master_password"
                />
                <InputError :message="form?.errors?.master_password?.[0]" />
                <span class="self-stretch text-gray-500 text-xs">{{ $t('public.password_desc') }}</span>
            </div>
            <div class="flex flex-col items-start gap-2 self-stretch">
                <InputLabel for="investor_password" :value="$t('public.investor_password')" :invalid="!!form?.errors?.investor_password" />
                <Password
                    v-model="form.investor_password"
                    toggleMask
                    :feedback="false"
                    placeholder="••••••••"
                    :invalid="!!form?.errors?.investor_password"
                />
                <InputError :message="form?.errors?.investor_password?.[0]" />
                <span class="self-stretch text-gray-500 text-xs">{{ $t('public.password_desc') }}</span>
            </div>
        </div>

        <div class="flex justify-end items-center pt-6 gap-4 self-stretch">
            <Button
                variant="gray-outlined"
                class="w-full"
                :disabled="formProcessing"
                @click.prevent="closeDialog"
            >
                {{ $t('public.cancel') }}
            </Button>
            <Button
                variant="primary-flat"
                class="w-full"
                :disabled="formProcessing"
                @click.prevent="submitForm"
            >
                {{ $t('public.reset') }}
            </Button>
        </div>
    </form>
</template>
