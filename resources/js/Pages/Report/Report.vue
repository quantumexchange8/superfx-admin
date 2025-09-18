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
// import Select from "primevue/select";
import Dropdown from "primevue/dropdown";

const props = defineProps({
  uplines: Array,
  downlines: Array
});

const tabs = ref([
    { title: wTrans('public.details'), component: h(RebateHistory), type: 'rebate' },
    { title: wTrans('public.summary'), component: h(RebateReport), type: 'summary' },
    // { title: wTrans('public.group_transaction'), component: h(GroupTransaction), type: 'group_transaction' },
]);

const selectedType = ref('rebate');
const selectedGroup = ref('dollar');
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

const groups = ref(['dollar', 'cent']);
</script>

<template>
    <AuthenticatedLayout :title="$t('public.rebate')">
        <div class="flex flex-col items-center gap-5 self-stretch">
            <div class="flex flex-col md:flex-row gap-5 items-center self-stretch justify-between">
                <TabView class="flex flex-col" :activeIndex="activeIndex" @tab-change="updateType">
                    <TabPanel v-for="(tab, index) in tabs" :key="index" :header="tab.title" />
                </TabView>
                <!-- <div v-if="selectedType === 'summary'">test</div> -->
                <Dropdown
                    v-if="selectedType === 'summary'"
                    v-model="selectedGroup"
                    :options="groups"
                    :placeholder="$t('public.select_group_placeholder')"
                    class="w-full md:w-60 font-normal truncate"
                    scroll-height="236px"
                >
                    <template #value="slotProps">
                        <div v-if="slotProps.value" class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <div>{{ $t('public.' + slotProps.value) }}</div>
                            </div>
                        </div>
                    </template>
                    <template #option="slotProps">
                        <div class="flex items-center gap-2">
                            <div>{{ $t('public.' + slotProps.option) }}</div>
                        </div>
                    </template>
                </Dropdown>
            </div>
            <component
                :is="tabs[activeIndex]?.component"
                v-bind="{
                    ...(selectedType === 'rebate' ? { uplines: props.uplines } : {}),
                    ...(selectedType === 'summary' ? { group: selectedGroup, uplines: props.uplines } : {})
                }"
            />
        </div>
    </AuthenticatedLayout>

</template>
