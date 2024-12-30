<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputText from 'primevue/inputtext';
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import { ref, onMounted, onBeforeUnmount } from 'vue';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submitted = ref(false);
const countdown = ref(60);
let interval;
const submittedEmail = ref('');

const startCountdown = () => {
    countdown.value = 60;
    interval = setInterval(() => {
        if (countdown.value > 0) {
            countdown.value -= 1;
        } else {
            clearInterval(interval);
            submitted.value = false;
        }
    }, 1000);
};

const submit = () => {
    form.post(route('password.email'), {
        onSuccess: () => {
            submittedEmail.value = form.email;
            submitted.value = true;
            startCountdown();
        },
    });
};

const goToLoginPage = () => {
    window.location.href = '/login'; // Redirect to the login page URL
};

onBeforeUnmount(() => {
    clearInterval(interval);
});

</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />
        
        <div v-if="!submitted" class="w-full flex flex-col items-center justify-center gap-8 pt-12">
            <div class="flex flex-col items-start gap-3 self-stretch">
                <div class="self-stretch text-center text-gray-950 text-xl font-semibold">{{ $t('public.forgot_password') }}</div>
                <div class="self-stretch text-center text-gray-500">{{ $t('public.forgot_password_caption') }}</div>
            </div>
            <form @submit.prevent="submit" class="flex flex-col items-center gap-6 self-stretch">
                <div class="flex flex-col items-start gap-1 self-stretch">
                    <InputLabel for="email" :value="$t('public.email')" />

                    <InputText
                        id="email"
                        type="email"
                        class="block w-full"
                        v-model="form.email"
                        autofocus
                        :placeholder="$t('public.enter_your_email')"
                        :invalid="!!form.errors.email"
                        autocomplete="username"
                    />

                    <InputError :message="form.errors.email" />
                </div>
                <div class="flex flex-col items-center gap-4 self-stretch">
                    <Button size="base" variant="primary-flat" class="w-full" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click.prevent="submit">{{ $t('public.send_reset_password_link') }}</Button>
                    <Button size="base" variant="gray-text" class="w-full" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click.prevent="goToLoginPage">{{ $t('public.back_to_login') }}</Button>
                </div>
            </form>
        </div>

        <div v-else class="w-full flex flex-col items-center justify-center gap-8 pt-12">
            <div class="flex flex-col items-start gap-3 self-stretch">
                <div class="self-stretch text-center text-gray-950 text-xl font-semibold">{{ $t('public.check_your_email') }}</div>
                <div class="self-stretch text-center text-gray-500">{{ $t('public.check_your_email_caption') }} <br/><span class="text-gray-900 font-medium">{{ submittedEmail }}</span> </div>
            </div>
            <div class="flex flex-col items-center justify-center gap-6 self-stretch">
                <Button size="base" variant="primary-flat" class="w-full" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click.prevent="goToLoginPage">{{ $t('public.back_to_login') }}</Button>
                <div class="flex justify-between items-center self-stretch">
                    <div class="text-gray-700 text-sm font-medium">{{ $t('public.not_receive_email') }}</div>
                    <div class="text-gray-300 text-right text-sm font-semibold">{{ $t('public.resend_in') }} {{ countdown }}s</div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
