<script setup>
import { ref, h, watch } from "vue";
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import BonusPayout from "@/Pages/Billboard/BillboardProfile/BonusPayout.vue";
import BonusWithdrawalReport from "@/Pages/Billboard/BillboardProfile/BonusWithdrawalReport.vue";

const props = defineProps({
    profile: Object
})

const tabs = ref([
    {
        component: h(BonusPayout),
        type: 'bonus_payout',
    },
    {
        component: h(BonusWithdrawalReport),
        type: 'withdrawal',
    },
]);

const selectedType = ref('bonus_payout');
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
    <div class="flex flex-col items-center gap-4 flex-grow self-stretch">
        <div class="flex flex-col gap-4 items-center self-stretch lg:flex-row">
            <TabView
                class="flex items-center self-stretch"
                :activeIndex="activeIndex"
                @tab-change="updateType"
            >
                <TabPanel
                    v-for="(tab, index) in tabs"
                    :key="index"
                    :header="$t(`public.${tab.type}`)"
                />
            </TabView>
        </div>
        <component
            :is="tabs[activeIndex]?.component"
            :profile="profile"
        />
    </div>
</template>
