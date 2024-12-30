<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Button from "@/Components/Button.vue"
import { useForm, usePage } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import {ref, watch} from "vue";

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});

const resetForm = () => {
    form.reset();
}

const submitForm = () => {
    form.post(route('profile.update'), {
        onSuccess: () => {
            visible.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <form class="p-4 md:py-6 md:px-8 flex flex-col gap-8 md:gap-0 md:justify-between items-center self-stretch rounded-2xl shadow-toast w-full">
        <div class="flex flex-col gap-8 items-center self-stretch">
            <div class="flex flex-col gap-1 items-start justify-center w-full">
                <span class="text-gray-950 font-bold">{{ $t('public.account_details') }}</span>
                <span class="text-gray-500 text-xs">{{ $t('public.account_details_caption') }}</span>
            </div>

            <div class="flex flex-col gap-5 items-center self-stretch w-full">
                <div class="flex flex-col gap-1 w-full">
                    <InputLabel for="name">
                        {{ $t('public.your_name') }}
                    </InputLabel>
                    <InputText
                        id="name"
                        type="text"
                        class="block w-full"
                        v-model="form.name"
                        :placeholder="$t('public.enter_name')"
                        :invalid="!!form.errors.name"
                        autocomplete="name"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="flex flex-col gap-1 w-full">
                    <InputLabel for="email">
                        {{ $t('public.email') }}
                    </InputLabel>
                    <InputText
                        id="email"
                        type="email"
                        class="block w-full"
                        v-model="form.email"
                        :placeholder="$t('public.enter_email')"
                        :invalid="!!form.errors.email"
                        autocomplete="email"
                    />
                    <InputError :message="form.errors.email" />
                </div>

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
                @click="submitForm"
            >
                {{ $t('public.save_changes') }}
            </Button>
        </div>
    </form>
</template>
