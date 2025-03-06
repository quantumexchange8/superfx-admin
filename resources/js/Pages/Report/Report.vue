<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { HandIcon, CoinsIcon, RocketIcon } from '@/Components/Icons/solid';
import Button from "@/Components/Button.vue";
import Vue3Autocounter from 'vue3-autocounter';
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

const totalVolume = ref(999);
const totalRebateAmount = ref(999);
const counterDuration = ref(10);

// data overview
const dataOverviews = computed(() => [
    {
        icon: HandIcon,
        total: totalVolume.value,
        label: 'total_volume',
    },
    {
        icon: CoinsIcon,
        total: totalRebateAmount.value,
        label: 'total_rebate',
    },
]);

const handleUpdateTotals = (data) => {
  totalVolume.value = data.totalVolume;
  totalRebateAmount.value = data.totalRebateAmount;
  counterDuration.value = 1;
};

</script>

<template>
    <AuthenticatedLayout :title="$t('public.report')">
        <div class="flex flex-col items-center gap-5 self-stretch">
            <div class="flex items-center self-stretch">
                <TabView class="flex flex-col" :activeIndex="activeIndex" @tab-change="updateType">
                    <TabPanel v-for="(tab, index) in tabs" :key="index" :header="tab.title" />
                </TabView>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 w-full gap-5">
                <div
                    v-for="(item, index) in dataOverviews"
                    :key="index"
                    class="flex justify-center items-center py-4 px-6 gap-5 self-stretch rounded-2xl bg-white shadow-toast md:flex-col md:flex-grow md:py-6 md:gap-3"
                >
                    <component :is="item.icon" class="w-12 h-12 grow-0 shrink-0" />
                    <div class="flex flex-col items-center gap-1 flex-grow md:flex-grow-0 md:self-stretch">
                        <div class="self-stretch text-gray-950 text-lg font-semibold md:text-xl md:text-center">
                            <vue3-autocounter
                                ref="counter"
                                :startAmount="0"
                                :endAmount="item.total"
                                :duration="counterDuration"
                                separator=","
                                decimalSeparator="."
                                :decimals="item.label === 'total_transaction' ? 0 : (item.label === 'total_payout_amount' ? 3 : 2)"
                                :autoinit="true"
                            />
                        </div>
                        <span class="self-stretch text-gray-500 text-xs md:text-sm md:text-center">{{ $t('public.' + item.label) }}</span>
                    </div>
                </div>
            </div>

            <component 
                :is="tabs[activeIndex]?.component" 
                v-bind="selectedType === 'rebate' ? { uplines: props.uplines } : {}"
                @update-totals="handleUpdateTotals"
            />
        </div>
    </AuthenticatedLayout>

</template>
