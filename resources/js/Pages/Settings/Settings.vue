<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SiteSetting from "@/Pages/Settings/SiteSetting/SiteSetting.vue";
import TradingPlatform from "@/Pages/Settings/TradingPlatform/TradingPlatform.vue";

</script>

<template>
    <AuthenticatedLayout :title="$t('public.system')">
        <div class="grid grid-cols-12 gap-5">
            <div class="col-span-12 md:col-span-6">
                <SiteSetting />
            </div>
            <div class="col-span-12 md:col-span-6">
                <TradingPlatform />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
