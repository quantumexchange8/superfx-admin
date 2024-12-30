<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import {h, ref, watch} from "vue";
import AllAccount from "@/Pages/Member/Account/AllAccount.vue";
import DeletedAccount from "@/Pages/Member/Account/DeletedAccount.vue";
import Button from "@/Components/Button.vue"
import {IconRefresh} from "@tabler/icons-vue"
import {router} from "@inertiajs/vue3";
import dayjs from "dayjs";

defineProps({
    last_refresh_datetime: Number
})

const tabs = ref([
    {
        title: 'all_accounts',
        component: h(AllAccount),
        type: 'all_accounts'
    },
    {
        title: 'deleted_accounts',
        component: h(DeletedAccount),
        type: 'deleted_accounts'
    },
]);

const selectedType = ref('all_accounts');
const activeIndex = ref(tabs.value.findIndex(tab => tab.type === selectedType.value));

// Watch for changes in selectedType and update the activeIndex accordingly
watch(selectedType, (newType) => {
    const index = tabs.value.findIndex(tab => tab.type === newType);
    if (index >= 0) {
        activeIndex.value = index;
    }
});

const updateType = (event) => {
    const selectedTab = tabs.value[event.index];
    selectedType.value = selectedTab.type;
}

const refreshAll = () => {
    router.post(route('member.refreshAllAccount'))
}
</script>

<template>
    <AuthenticatedLayout :title="$t('public.account_listing')">
        <div class="flex flex-col gap-5 items-center self-stretch">
            <div class="flex justify-end w-full">
                <div class="flex flex-col gap-1 items-center md:items-end w-full md:w-auto">
                    <Button
                        type="button"
                        variant="primary-flat"
                        class="flex justify-center w-full md:w-auto"
                        @click="refreshAll"
                    >
                        <IconRefresh color="white" size="20" stroke-width="1.25" />
                        <span>{{ $t('public.refresh_all') }}</span>
                    </Button>
                    <span class="text-gray-500 text-sm">{{ $t('public.last_refreshed') }}: {{ last_refresh_datetime ? dayjs(last_refresh_datetime).format('YYYY/MM/DD HH:mm:ss') : '-' }}</span>
                </div>
            </div>
            <div class="py-6 px-4 md:p-6 flex flex-col items-center self-stretch border border-gray-200 bg-white shadow-table rounded-2xl">
                <TabView
                    class="flex flex-col w-full gap-6"
                    :activeIndex="activeIndex"
                    @tab-change="updateType"
                >
                    <TabPanel
                        v-for="(tab, index) in tabs"
                        :key="index"
                        :header="$t(`public.${tab.title}`)"
                    >
                        <component :is="tabs[activeIndex]?.component" />
                    </TabPanel>
                </TabView>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
