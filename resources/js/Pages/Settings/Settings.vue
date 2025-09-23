<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SiteSetting from "@/Pages/Settings/SiteSetting/SiteSetting.vue";
import TradingPlatform from "@/Pages/Settings/TradingPlatform/TradingPlatform.vue";
import PaymentPlatform from "@/Pages/Settings/PaymentPlatform/PaymentPlatform.vue";

</script>

<template>
    <AuthenticatedLayout :title="$t('public.system')">
        <div class="grid grid-cols-12 gap-5">
            <div class="col-span-12 md:col-span-6">
                <div class="grid gap-5">
                    <SiteSetting />
                    <TradingPlatform />
                </div>
            </div>
            <div class="col-span-12 md:col-span-6">
                <PaymentPlatform />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
