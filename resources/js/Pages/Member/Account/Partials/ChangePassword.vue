<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Password from 'primevue/password';
import { useForm } from "@inertiajs/vue3";
import Button from "@/Components/Button.vue";

const props = defineProps({
    account: Object,
})

const emit = defineEmits(['update:visible'])

const form = useForm({
    id: props.account.id,
    master_password: '',
    investor_password: '',
    meta_login: props.account.meta_login,
    user_id: props.account.user_id,
})

const closeDialog = () => {
    emit('update:visible', false);
}

const submitForm = () => {
    form.post(route('member.change_password'), {
        onSuccess: () => {
            closeDialog();
            form.reset();
        },
    });
}
</script>

<template>
    <form>
        <div class="flex flex-col gap-5 items-center self-stretch py-4 md:py-6">
            <div class="flex flex-col items-start gap-2 self-stretch">
                <InputLabel for="master_password" :value="$t('public.master_password')" :invalid="!!form.errors.master_password" />
                <Password
                    v-model="form.master_password"
                    toggleMask
                    :feedback="false"
                    placeholder="••••••••"
                    :invalid="!!form.errors.master_password"
                />
                <InputError :message="form.errors.master_password" />
                <span class="self-stretch text-gray-500 text-xs">{{ $t('public.password_desc') }}</span>
            </div>
            <div class="flex flex-col items-start gap-2 self-stretch">
                <InputLabel for="investor_password" :value="$t('public.investor_password')" :invalid="!!form.errors.investor_password" />
                <Password
                    v-model="form.investor_password"
                    toggleMask
                    :feedback="false"
                    placeholder="••••••••"
                    :invalid="!!form.errors.investor_password"
                />
                <InputError :message="form.errors.investor_password" />
                <span class="self-stretch text-gray-500 text-xs">{{ $t('public.password_desc') }}</span>
            </div>
        </div>

        <div class="flex justify-end items-center pt-6 gap-4 self-stretch">
            <Button
                variant="gray-outlined"
                class="w-full"
                :disabled="form.processing"
                @click.prevent="closeDialog"
            >
                {{ $t('public.cancel') }}
            </Button>
            <Button
                variant="primary-flat"
                class="w-full"
                :disabled="form.processing"
                @click.prevent="submitForm"
            >
                {{ $t('public.reset') }}
            </Button>
        </div>
    </form>
</template>
