<script setup>
import {Head} from "@inertiajs/vue3";
import Button from '@/Components/Button.vue'
import ErrorLayout from '@/Layouts/ErrorLayout.vue';

const refreshPage = () => {
    location.reload();
}
</script>

<template>
    <ErrorLayout>
        <Head :title="$t('public.request_timed_out')" />
        <div class="flex flex-col justify-center items-center self-stretch">
            <img src="/img/errors/illustration-504.svg" class="" alt="">
            <div class="flex flex-col gap-8 items-center self-stretch">
                <div class="flex flex-col items-center gap-3 text-center">
                    <div class="text-gray-950 text-xl font-bold">
                        {{$t('public.request_timed_out')}}
                    </div>
                    <div class="text-gray-500">
                        {{$t('public.request_timed_out_desc')}}
                    </div>
                </div>
                <Button
                    type="button"
                    variant="primary-flat"
                    class="w-full md:w-auto text-nowrap justify-center"
                    @click="refreshPage"
                >
                    {{$t('public.refresh_page')}}
                </Button>
            </div>
        </div>
    </ErrorLayout>
</template>