<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import {h, ref, watch} from "vue";
import PendingWithdrawal from "@/Pages/Pending/Withdrawal/PendingWithdrawal.vue";

const props = defineProps({
    paymentGateways: Array,
});

const tabs = ref([
    {
        title: 'withdrawal_pending',
        component: h(PendingWithdrawal)
    },
]);

const selectedType = ref('withdrawal_pending');
const activeIndex = ref(tabs.value.findIndex(tab => tab.title === selectedType.value));

// Watch for changes in selectedType and update the activeIndex accordingly
watch(selectedType, (newType) => {
    const index = tabs.value.findIndex(tab => tab.title === newType);
    if (index >= 0) {
        activeIndex.value = index;
    }
});

// Update selectedType on tab change
const updateType = (event) => {
    const selectedTab = tabs.value[event.index];
    selectedType.value = selectedTab.title;
};
</script>

<template>
    <AuthenticatedLayout :title="$t('public.pending')">
        <TabView
            class="flex flex-col gap-5 self-stretch"
            :activeIndex="activeIndex"
            @tab-change="updateType"
        >
            <TabPanel v-for="(tab, index) in tabs" :key="index" :header="$t(`public.${tab.title}`)">
                <component :is="tab.component" v-if="activeIndex === index" :paymentGateways="props.paymentGateways" />
            </TabPanel>
        </TabView>
    </AuthenticatedLayout>
</template>
