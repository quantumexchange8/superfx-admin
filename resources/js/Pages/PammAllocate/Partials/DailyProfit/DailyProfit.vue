<script setup>
import { ref, h, watch } from "vue";
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import { wTrans } from "laravel-vue-i18n";
import DailyProfitAllocation from "@/Pages/PammAllocate/Partials/DailyProfit/DailyProfitAllocation.vue";
import DailyProfitHistory from "@/Pages/PammAllocate/Partials/DailyProfit/DailyProfitHistory.vue";

const props = defineProps({
    master: Object
})

const emit = defineEmits(['update:visible'])

const closeDialog = () => {
    emit('update:visible', false);
}

const tabs = ref([
    {
        title: wTrans('public.allocation'),
        component: h(DailyProfitAllocation),
        type: 'allocation'
    },
    {
        title: wTrans('public.history'),
        component: h(DailyProfitHistory),
        type: 'history'
    },
]);

const selectedType = ref('allocation');
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
    <div class="flex flex-col items-center gap-5 flex-grow self-stretch">
        <TabView class="flex items-center self-stretch" :activeIndex="activeIndex" @tab-change="updateType">
            <TabPanel v-for="(tab, index) in tabs" :key="index" :header="tab.title" />
        </TabView>
        <component
            :is="tabs[activeIndex]?.component"
            :master="props.master"
            @update:visible="closeDialog()"
        />
    </div>
</template>
