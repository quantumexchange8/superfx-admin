<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import {ref, watchEffect, computed} from "vue";
import {
    IBIcon,
    GroupIBIcon,
    MinIcon,
    MaxIcon,
} from '@/Components/Icons/outline.jsx';
import {transactionFormat} from "@/Composables/index.js";
import {usePage} from "@inertiajs/vue3";
import { wTrans, trans } from "laravel-vue-i18n";
import EditRebateDetails from "@/Pages/RebateAllocate/Partials/EditRebateDetails.vue";
import RebateStructureTable from "@/Pages/RebateAllocate/Partials/RebateStructureTable.vue";

const props = defineProps({
    accountTypes: Array,
})

const accountType = ref(1)
const companyProfile = ref();
const rebateDetails = ref();
const loading = ref(false);
const {formatAmount} = transactionFormat()

const totalDirectIB = ref(0.00);
const totalGroupIB = ref(0.00);
const minimumLevel = ref(0.00);
const maximumLevel = ref(0.00);

// data overview
const dataOverviews = computed(() => [
    {
        icon: IBIcon,
        total: totalDirectIB.value,
        label: trans('public.direct_ib'),
    },
    {
        icon: GroupIBIcon,
        total: totalGroupIB.value,
        label: trans('public.group_ib'),
    },
    {
        icon: MinIcon,
        total: minimumLevel.value,
        label: trans('public.minimum_level'),
    },
    {
        icon: MaxIcon,
        total: maximumLevel.value,
        label: trans('public.maximum_level'),
    },
]);

const getResults = async () => {
    loading.value = true;

    try {
        const response = await axios.get(`/rebate_allocate/getCompanyProfileData?account_type_id=${accountType.value}`);
        companyProfile.value = response.data.companyProfile;
        rebateDetails.value = response.data.rebateDetails;

        // Directly updating values without a separate function
        minimumLevel.value = companyProfile.value.user.minimum_level || 0;
        maximumLevel.value = companyProfile.value.user.maximum_level || 0;
        totalDirectIB.value = companyProfile.value.user.direct_ib || 0;
        totalGroupIB.value = companyProfile.value.user.group_ib || 0;

    } catch (error) {
        console.error('Error fetch company profile:', error);
    } finally {
        loading.value = false;
    }
};

getResults();

const handleAccountTypeChange = (newType) => {
    accountType.value = newType
    getResults();
};

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults();
    }
});
</script>

<template>
    <AuthenticatedLayout :title="$t('public.rebate_allocate')">
        <div class="flex flex-col gap-8 items-center">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 w-full">

                <!-- data overview -->
                <div class="w-full grid grid-cols-2 gap-3 md:gap-5 self-stretch overflow-x-auto">
                    <div 
                        v-for="(item, index) in dataOverviews"
                        :key="index"
                        class="flex flex-col justify-center items-center py-5 px-3 md:p-0 gap-3 rounded-lg bg-white shadow-card"
                    >
                        <component :is="item.icon" class="w-8 h-8 grow-0 shrink-0 text-primary-600" />
                        <span class="text-gray-500 text-sm">{{ item.label }}</span>
                        <span class="self-stretch text-gray-950 text-center text-lg font-semibold">{{ item.total }}</span>
                    </div>
                </div>

                <!-- Rebate detail -->
                <div class="flex flex-col items-center justify-center gap-5 py-4 px-8 self-stretch bg-white shadow-toast rounded-2xl w-full">
                    <div class="flex justify-between items-center w-full">
                        <div class="text-gray-950 font-semibold">
                            {{ $t('public.rebate_details') }}
                        </div>

                        <EditRebateDetails
                            :rebateDetails="rebateDetails"
                        />
                    </div>

                    <div class="flex flex-col items-center gap-3 self-stretch">
                        <div class="flex items-center w-full self-stretch py-2 text-gray-950 bg-gray-100">
                            <span class="uppercase text-xs font-semibold px-2 w-full">{{ $t('public.product') }}</span>
                            <span class="uppercase text-xs font-semibold px-2 w-full">{{ $t('public.rebate') }} / Ł ($)</span>
                        </div>

                        <!-- symbol groups -->
                        <div
                            v-if="rebateDetails"
                            v-for="rebateDetail in rebateDetails"
                            class="flex items-center w-full self-stretch py-2 text-gray-950"
                        >
                            <div class="text-sm px-2 w-full">{{ $t(`public.${rebateDetail.symbol_group.display}`) }}</div>
                            <div class="text-sm px-2 w-full">{{ formatAmount(rebateDetail.amount, 0) }}</div>
                        </div>
                        <div
                            v-else
                            v-for="index in 6"
                            class="flex items-center w-full self-stretch py-2 text-gray-950"
                        >
                            <div class="w-full">
                                <div class="h-2.5 bg-gray-200 rounded-full w-36 mt-1 mb-1.5"></div>
                            </div>
                            <div class="w-full">
                                <div class="h-2.5 bg-gray-200 rounded-full w-10 mt-1 mb-1.5"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- data table -->
            <RebateStructureTable :accountTypes="accountTypes" @update:accountType="handleAccountTypeChange" />
        </div>
    </AuthenticatedLayout>
</template>
