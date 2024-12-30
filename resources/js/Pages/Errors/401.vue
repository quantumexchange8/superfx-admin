<script setup>
import {Head} from "@inertiajs/vue3";
import Button from '@/Components/Button.vue'
import ErrorLayout from '@/Layouts/ErrorLayout.vue';

</script>

<template>
    <ErrorLayout>
        <Head :title="$t('public.please_log_in')" />
        <div class="flex flex-col justify-center items-center self-stretch">
            <img src="/img/errors/illustration-401.svg" class="" alt="">
            <div class="flex flex-col gap-8 items-center self-stretch">
                <div class="flex flex-col items-center gap-3 text-center">
                    <div class="text-gray-950 text-xl font-bold">
                        {{$t('public.please_log_in')}}
                    </div>
                    <div class="text-gray-500">
                        {{$t('public.please_log_in_desc')}}
                    </div>
                </div>
                <Button
                    type="button"
                    variant="primary-flat"
                    class="w-full md:w-auto text-nowrap justify-center"
                    external
                    :href="route('login')"
                >
                    {{$t('public.login')}}
                </Button>
            </div>
        </div>
    </ErrorLayout>
</template>
