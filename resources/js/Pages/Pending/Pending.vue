<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import {h, ref} from "vue";
import PendingWithdrawal from "@/Pages/Pending/Withdrawal/PendingWithdrawal.vue";
import PendingRevokePamm from "@/Pages/Pending/RevokePamm/PendingRevokePamm.vue";

const tabs = ref([
    {
        title: 'withdrawal_pending',
        component: h(PendingWithdrawal)
    },
    {
        title: 'revoke_pamm',
        component: h(PendingRevokePamm),
    }
]);
</script>

<template>
    <AuthenticatedLayout :title="$t('public.pending')">
        <TabView
            class="flex flex-col gap-5 self-stretch"
        >
            <TabPanel v-for="(tab, index) in tabs" :key="index" :header="$t(`public.${tab.title}`)">
                <component :is="tab.component" />
            </TabPanel>
        </TabView>
    </AuthenticatedLayout>
</template>
