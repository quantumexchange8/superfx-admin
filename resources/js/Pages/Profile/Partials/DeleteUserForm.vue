<script setup>
import { ref } from 'vue';
import Button from "@/Components/Button.vue";
import { DeleteAccountIcon } from '@/Components/Icons/solid';
import ConfirmDialog from 'primevue/confirmdialog';
import { DeleteIcon } from "@/Components/Icons/brand.jsx";
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Password from 'primevue/password';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const visible = ref(false);

const openConfirmDialog = () => {
    visible.value = true;
};

const handleDelete = () => {
    form.delete(route('profile.destroy'), {
        onSuccess: () => {
            visible.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <div class="relative flex flex-col justify-end items-end gap-8 px-4 py-6 self-stretch rounded-2xl bg-white shadow-toast md:px-8 w-full h-[400px] md:h-auto">
        <DeleteAccountIcon class="w-80 h-60 md:w-[420px] md:h-[315px] absolute -top-5 left-1/2 transform -translate-x-1/2" />

        <div class="flex flex-col justify-center items-start gap-1 self-stretch">
            <span class="self-stretch text-gray-950 text-sm font-bold md:text-base">{{ $t('public.delete_account') }}</span>
            <span class="self-stretch text-gray-500 text-xs">{{ $t('public.delete_account_desc') }}</span>
        </div>

        <Button
            type="button"
            variant="error-flat"
            size="base"
            @click="openConfirmDialog"
        >
            {{ $t('public.delete_account') }}
        </Button>
    </div>

    <ConfirmDialog
        group="headless-error"
        v-model:visible="visible"
    >
        <template #container>
            <div class="flex flex-col items-center bg-white rounded-3xl w-[320px] h-full md:w-[500px]">
                <div class="relative w-full h-[93px] md:h-[132px] flex">
                    <div
                        class="rounded-tl-3xl rounded-tr-3xl flex w-full p-1 justify-center [clip-path:polygon(0%_0%,_100%_0%,_100%_78%,_50%_100%,_0_78%)] bg-error-600"
                    >
                        <div class="p-5 flex items-center justify-center">
                            <DeleteIcon class="w-16 h-16 md:w-full md:h-auto" />
                        </div>
                    </div>
                </div>
                <div class="pt-2 md:pt-3 pb-6 px-4 md:px-6 w-full flex flex-col items-center gap-5 self-stretch">
                    <div class="flex flex-col gap-1 items-center self-stretch text-center">
                        <span class="text-gray-950 text-sm md:text-base font-semibold">{{ `${$t('public.delete_account')}&nbsp;:(` }}</span>
                        <span class="text-gray-700 text-xs md:text-sm">{{ $t('public.delete_account_message') }}</span>
                    </div>
                    <div class="flex flex-col gap-1 items-start self-stretch text-center">
                        <InputLabel for="password" :value="$t('public.password')" />
                        <Password
                            v-model="form.password"
                            placeholder="••••••••"
                            toggleMask
                            :feedback="false"
                            :invalid="!!form.errors.password"
                        />
                        <InputError :message="form.errors.password" />
                    </div>
                    <div class="flex items-center gap-4 md:gap-5 self-stretch">
                        <Button
                            type="button"
                            variant="gray-tonal"
                            @click="visible = false"
                            class="w-full"
                            size="base"
                        >
                            {{ $t('public.cancel') }}
                        </Button>
                        <Button
                            type="button"
                            variant="error-flat"
                            @click="handleDelete"
                            class="w-full text-nowrap"
                            size="base"
                        >
                            {{ $t('public.delete') }}
                        </Button>
                    </div>
                </div>
            </div>
        </template>
    </ConfirmDialog>
</template>
