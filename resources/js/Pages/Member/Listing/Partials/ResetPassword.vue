<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Password from 'primevue/password';
import { useForm } from "@inertiajs/vue3";
import Button from "@/Components/Button.vue";

const props = defineProps({
    member: Object,
})

const emit = defineEmits(['update:visible'])

const form = useForm({
    id: props.member.id,
    password: '',
    password_confirmation: '',
})

const closeDialog = () => {
    emit('update:visible', false);
}

const submitForm = () => {
    form.post(route('member.resetPassword'), {
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
                <InputLabel for="password" :value="$t('public.password')" :invalid="!!form.errors.password" />
                <Password
                    v-model="form.password"
                    toggleMask
                    :feedback="false"
                    placeholder="••••••••"
                    :invalid="!!form.errors.password"
                />
                <InputError :message="form.errors.password" />
                <span class="self-stretch text-gray-500 text-xs">{{ $t('public.password_desc') }}</span>
            </div>
            <div class="flex flex-col items-start gap-2 self-stretch">
                <InputLabel for="password_confirmation" :value="$t('public.confirm_password')" :invalid="!!form.errors.password_confirmation" />
                <Password
                    v-model="form.password_confirmation"
                    toggleMask
                    :feedback="false"
                    placeholder="••••••••"
                    :invalid="!!form.errors.password_confirmation"
                />
                <InputError :message="form.errors.password_confirmation" />
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
