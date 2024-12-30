<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Button from '@/Components/Button.vue';
import { IconChevronRight } from '@tabler/icons-vue';
import {ref, h, watchEffect} from "vue";
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import {usePage} from '@inertiajs/vue3';
import MemberFinancialInfo from '@/Pages/Member/Listing/Partials/MemberFinancialInfo.vue';
import MemberTradingAccounts from '@/Pages/Member/Listing/Partials/MemberTradingAccounts.vue';
import ProfileInfo from "@/Pages/Member/Listing/MemberDetail/ProfileInfo.vue";
import KycVerification from "@/Pages/Member/Listing/MemberDetail/KycVerification.vue";
import CryptoWalletInfo from "@/Pages/Member/Listing/MemberDetail/CryptoWalletInfo.vue";
import {wTrans} from "laravel-vue-i18n";
import AdjustmentHistory from "@/Pages/Member/Listing/MemberDetail/AdjustmentHistory.vue";

const props = defineProps({
    user: Object
})

const userDetail = ref();
const paymentAccounts = ref();

const getUserData = async () => {
    try {
        const response = await axios.get(`/member/getUserData?id=` + props.user.id);

        userDetail.value = response.data.userDetail;
        paymentAccounts.value = response.data.paymentAccounts;
    } catch (error) {
        console.error('Error get network:', error);
    }
};

getUserData();

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getUserData();
    }
});

const tabs = ref([
      {
          title: wTrans('public.financial_info'),
          component: h(MemberFinancialInfo, {user_id: props.user.id}),
      },
      {
          title: wTrans('public.trading_accounts'),
          component: h(MemberTradingAccounts, {user_id: props.user.id}),
      },
      {
          title: wTrans('public.adjustment_history'),
          component: h(AdjustmentHistory, {user_id: props.user.id}),
      },
]);
</script>

<template>
    <AuthenticatedLayout :title="$t('public.member_listing')">
        <div class="flex flex-col gap-5">

            <!-- Breadcrumb -->
            <div class="flex flex-wrap md:flex-nowrap items-center gap-2 self-stretch">
                <Button
                    external
                    type="button"
                    variant="primary-text"
                    size="sm"
                    :href="route('member.listing')"
                >
                    {{ $t('public.member_listing') }}
                </Button>
                <IconChevronRight
                    :size="16"
                    stroke-width="1.25"
                />
                <span class="flex px-4 py-2 text-gray-400 items-center justify-center text-sm font-medium">{{ user.name }} - {{ $t('public.view_member_details') }}</span>
            </div>

            <!-- Profile Info -->
            <div class="flex flex-col xl:flex-row items-center w-full gap-5 self-stretch">
                <ProfileInfo
                    :userDetail="userDetail"
                />
                <div class="flex flex-col w-full gap-5 self-stretch">
                    <KycVerification
                        :userDetail="userDetail"
                    />
                    <CryptoWalletInfo
                        :userDetail="userDetail"
                        :paymentAccounts="paymentAccounts"
                    />
                </div>
            </div>

            <TabView class="flex flex-col gap-5">
                <TabPanel v-for="(tab, index) in tabs" :key="index" :header="tab.title">
                    <component :is="tab.component" />
                </TabPanel>
            </TabView>
        </div>
    </AuthenticatedLayout>
</template>
