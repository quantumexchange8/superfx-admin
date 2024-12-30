<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Password from 'primevue/password';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from "@/Components/Button.vue";

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};

const resetForm = () => {
    form.reset();
}
</script>

<template>
    <div class="p-4 md:py-6 md:px-8 flex flex-col gap-8 items-center self-stretch rounded-2xl shadow-toast w-full">
        <div class="flex flex-col gap-1 items-start justify-center w-full">
            <span class="text-gray-950 font-bold">{{ $t('public.reset_password') }}</span>
            <span class="text-gray-500 text-xs">{{ $t('public.reset_password_caption') }}</span>
        </div>

        <form class="w-full">
            <div class="flex flex-col gap-5 items-center self-stretch w-full">
                <div class="flex flex-col gap-1 w-full">
                    <InputLabel for="current_password" :value="$t('public.current_password')" />
                    <Password
                        ref="currentPasswordInput"
                        v-model="form.current_password"
                        toggleMask
                        :invalid="!!form.errors.current_password"
                    />
                    <InputError :message="form.errors.current_password" />
                </div>

                <div class="flex flex-col gap-1 w-full">
                    <InputLabel for="password" :value="$t('public.password')" />
                    <Password
                        ref="passwordInput"
                        v-model="form.password"
                        toggleMask
                        :invalid="!!form.errors.password"
                    />
                    <InputError :message="form.errors.password" />
                    <span class="text-xs text-gray-500">{{ $t('public.password_desc') }}</span>
                </div>

                <div class="flex flex-col gap-1 w-full">
                    <InputLabel for="password_confirmation" :value="$t('public.confirm_password')" />
                    <Password
                        v-model="form.password_confirmation"
                        toggleMask
                        :invalid="!!form.errors.password_confirmation"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>
            </div>

            <div class="flex justify-end items-center pt-10 md:pt-7 gap-4 self-stretch">
                <Button
                    type="button"
                    variant="gray-tonal"
                    :disabled="form.processing"
                    @click="resetForm"
                >
                    {{ $t('public.cancel') }}
                </Button>
                <Button
                    variant="primary-flat"
                    :disabled="form.processing"
                    @click="updatePassword"
                >
                    {{ $t('public.reset_password') }}
                </Button>
            </div>
        </form>
    </div>
</template>
