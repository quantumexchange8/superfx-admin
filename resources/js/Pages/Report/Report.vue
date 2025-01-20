<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Button from "@/Components/Button.vue";
import { ref, h, watch, onMounted, computed } from "vue";
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import RebateReport from '@/Pages/Report/Rebate/RebateReport.vue';
import GroupTransaction from '@/Pages/Report/GroupTransaction/GroupTransaction.vue';
import { usePage, useForm } from "@inertiajs/vue3";
import { trans, wTrans } from "laravel-vue-i18n";
import RebateHistory from "@/Pages/Report/RebateHistory/RebateHistory.vue";

const props = defineProps({
  uplines: Array,
});

// Initialize the form with user data
const user = usePage().props.auth.user;

const tabs = ref([
    { title: wTrans('public.rebate'), component: h(RebateHistory), type: 'rebate' },
    // { title: wTrans('public.summary'), component: h(RebateReport), type: 'summary' },
    // { title: wTrans('public.group_transaction'), component: h(GroupTransaction), type: 'group_transaction' },
]);

const selectedType = ref('rebate');
const activeIndex = ref(tabs.value.findIndex(tab => tab.type === selectedType.value));

// Watch for changes in selectedType and update the activeIndex accordingly
watch(selectedType, (newType) => {
    const index = tabs.value.findIndex(tab => tab.type === newType);
    if (index >= 0) {
        activeIndex.value = index;
    }
});

function updateType(event) {
    const selectedTab = tabs.value[event.index];
    selectedType.value = selectedTab.type;
}
</script>

<template>
    <AuthenticatedLayout :title="$t('public.report')">
        <div class="flex flex-col items-center gap-5 self-stretch">
            <div class="flex items-center self-stretch">
                <TabView class="flex flex-col" :activeIndex="activeIndex" @tab-change="updateType">
                    <TabPanel v-for="(tab, index) in tabs" :key="index" :header="tab.title" />
                </TabView>
            </div>
            <component 
                :is="tabs[activeIndex]?.component" 
                v-bind="selectedType === 'rebate' ? { uplines: props.uplines } : {}" 
            />
        </div>
    </AuthenticatedLayout>

</template>
