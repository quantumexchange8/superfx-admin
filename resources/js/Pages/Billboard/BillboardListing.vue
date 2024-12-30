<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import TabPanel from "primevue/tabpanel";
import TabView from "primevue/tabview";
import {h, ref, watchEffect} from "vue";
import BillboardProfile from "@/Pages/Billboard/BillboardProfile/BillboardProfile.vue";
import BonusWithdrawal from "@/Pages/Billboard/BonusWithdrawal/BonusWithdrawal.vue";
import {usePage} from "@inertiajs/vue3";

const props = defineProps({
    profileCount: Number
})

const tabs = ref([
    {
        title: 'profile',
        component: h(BillboardProfile, {
            profileCount: props.profileCount
        }),
        rowCount: null
    },
    {
        title: 'bonus_withdrawal',
        component: h(BonusWithdrawal),
        rowCount: 0
    },
]);

const activeIndex = ref(tabs.value.findIndex(tab => tab.title === 'profile'));

const updateType = (event) => {
    activeIndex.value = event.index;
}

const getPendingCounts = async () => {
    try {
        const response = await axios.get('/getPendingCounts');
        tabs.value[1].rowCount = response.data.pendingBonusWithdrawal
    } catch (error) {
        console.error('Error pending counts:', error);
    }
};

getPendingCounts();

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getPendingCounts();
    }
});
</script>

<template>
    <AuthenticatedLayout :title="$t('public.billboard')">
        <div class="flex flex-col gap-5 md:gap-8">
            <TabView class="flex flex-col" :activeIndex="activeIndex" @tab-change="updateType">
                <TabPanel
                    v-for="(tab, index) in tabs"
                    :key="index"
                    :header="$t(`public.${tab.title}`) + (tab.rowCount !== null ? ` (${tab.rowCount})` : '' )"
                />
            </TabView>
            <component
                :is="tabs[activeIndex]?.component"
            />
        </div>
    </AuthenticatedLayout>
</template>
