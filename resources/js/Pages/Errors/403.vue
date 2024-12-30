<script setup>
import {Head, router} from "@inertiajs/vue3";
import Button from '@/Components/Button.vue'
import ErrorLayout from '@/Layouts/ErrorLayout.vue';

const handleLogOut = () => {
    router.post(route('logout'))
}

</script>

<template>
    <ErrorLayout>
        <Head :title="$t('public.access_denied')" />
        <div class="flex flex-col justify-center items-center self-stretch">
            <img src="/img/errors/illustration-403.svg" class="" alt="">
            <div class="flex flex-col gap-8 items-center self-stretch">
                <div class="flex flex-col items-center gap-3 text-center">
                    <div class="text-gray-950 text-xl font-bold">
                        {{$t('public.access_denied')}}
                    </div>
                    <div class="text-gray-500">
                        {{$t('public.access_denied_desc')}}
                    </div>
                </div>
                <Button
                    type="button"
                    variant="primary-flat"
                    class="w-full md:w-auto text-nowrap justify-center"
                    @click="handleLogOut"
                >
                    {{$t('public.logout')}}
                </Button>
            </div>
        </div>
    </ErrorLayout>
</template>
