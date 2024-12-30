<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputText from 'primevue/inputtext';
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import { ref } from 'vue';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const isResetSuccessful = ref(false);

const submit = async () => {
    form.post(route('password.store'), {
        onSuccess: () => {
            isResetSuccessful.value = true;
            form.reset('password', 'password_confirmation');
        },
        onError: (error) => {
            console.error('Password reset failed:', error);
        },
    });
};

const goToLoginPage = () => {
    window.location.href = '/login'; // Redirect to the login page URL
};

</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />

        <div v-if="!isResetSuccessful" class="w-full flex flex-col justify-center items-center gap-8 pt-12">
            <div class="flex flex-col items-start gap-3 self-stretch">
                <div class="text-gray-950 text-center text-xl font-semibold self-stretch">Choose a password</div>
                <div class="text-gray-500 text-center self-stretch">Make sure your password fulfills the criteria.</div>
            </div>
            <div class="flex flex-col items-center gap-6 self-stretch">
                <form @submit.prevent="submit" class="flex flex-col items-start gap-5 self-stretch">
                    <div class="flex flex-col items-start gap-1 self-stretch">
                        <InputLabel for="password" value="Password" />

                        <InputText
                            id="password"
                            type="password"
                            class="block w-full"
                            v-model="form.password"
                            :invalid="!!form.errors.password"
                            placeholder="••••••••"
                            autocomplete="current-password"
                        />

                        <InputError :message="form.errors.password" />
                        
                        <span class="text-gray-500 text-xs self-stretch">Must be at least 8 characters containing uppercase letters, lowercase letters, numbers, and symbols.</span>
                    </div>

                    <div class="flex flex-col items-start gap-1 self-stretch">
                        <InputLabel for="password_confirmation" value="Confirm Password" />

                        <InputText
                            id="password_confirmation"
                            type="password"
                            class="block w-full"
                            v-model="form.password_confirmation"
                            :invalid="!!form.errors.password_confirmation"
                            placeholder="••••••••"
                            autocomplete="new-password"
                        />

                        <InputError :message="form.errors.password_confirmation" />
                    </div>
                </form>
                <div class="flex flex-col items-center gap-4 self-stretch">
                    <Button size="base" variant="primary-flat" class="w-full" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click.prevent="submit">Reset Password</Button>
                    <Button size="base" variant="gray-text" class="w-full" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click.prevent="goToLoginPage">Back to Log In</Button>
                </div>
            </div>
        </div>

        <div v-else class="w-full flex flex-col justify-center items-center pt-12">
            <div class="flex flex-col items-center justify-center">
                <img src="/img/reset-password.svg" alt="no data" class="w-[360px]">
            </div>
            <div class="flex flex-col justify-center items-center gap-8 self-stretch">
                <div class="flex flex-col items-start gap-3 self-stretch">
                    <div class="text-gray-950 text-center text-xl font-semibold self-stretch">Password Reset!</div>
                    <div class="text-gray-500 text-center self-stretch">Your password has been successfully reset. You can now log in with your new password.</div>
                </div>
                <Button size="base" variant="primary-flat" class="w-full" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click.prevent="goToLoginPage">Back to Log In</Button>
            </div>
        </div>
    </GuestLayout>
</template>
