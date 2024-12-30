<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import {ref, watchEffect} from "vue";
import EditRebateDetails from "@/Pages/RebateAllocate/Partials/EditRebateDetails.vue";
import {transactionFormat} from "@/Composables/index.js";
import {usePage} from "@inertiajs/vue3";
import RebateStructureTable from "@/Pages/RebateAllocate/Partials/RebateStructureTable.vue";

const accountType = ref(1)
const companyProfile = ref();
const rebateDetails = ref();
const loading = ref(false);
const {formatAmount} = transactionFormat()

const getResults = async () => {
    loading.value = true;

    try {
        const response = await axios.get(`/rebate_allocate/getCompanyProfileData?account_type_id=${accountType.value}`);
        companyProfile.value = response.data.companyProfile;
        rebateDetails.value = response.data.rebateDetails;
    } catch (error) {
        console.error('Error fetch company profile:', error);
    } finally {
        loading.value = false;
    }
};

getResults();

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
                <div class="flex flex-col items-center justify-center gap-8 py-6 px-8 self-stretch bg-white shadow-toast rounded-2xl w-full">
                    <div class="flex flex-col items-center justify-center gap-2 pb-6 border-b border-gray-200 w-full">
                        <span v-if="companyProfile" class="text-xl font-semibold text-gray-950">{{ companyProfile.user.name }}</span>
                        <div v-else class="h-4 bg-gray-200 rounded-full w-48 my-2"></div>
                        <span v-if="companyProfile" class="text-gray-700">{{ companyProfile.user.id_number }}</span>
                        <div v-else class="h-2 bg-gray-200 rounded-full w-20 my-2"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-5 w-full">
                        <div class="flex flex-col items-start justify-center gap-2">
                            <span class="text-xs text-gray-500">{{ $t('public.direct_agent') }}</span>
                            <span v-if="companyProfile" class="text-sm font-medium text-gray-950">{{ companyProfile.user.direct_agent }}</span>
                            <div v-else class="h-2.5 bg-gray-200 rounded-full w-10 mt-1 mb-1.5"></div>
                        </div>
                        <div class="flex flex-col items-start justify-center gap-2">
                            <span class="text-xs text-gray-500">{{ $t('public.group_agent') }}</span>
                            <span v-if="companyProfile" class="text-sm font-medium text-gray-950">{{ companyProfile.user.group_agent }}</span>
                            <div v-else class="h-2.5 bg-gray-200 rounded-full w-10 mt-1 mb-1.5"></div>
                        </div>
                        <div class="flex flex-col items-start justify-center gap-2">
                            <span class="text-xs text-gray-500">{{ $t('public.minimum_level') }}</span>
                            <span v-if="companyProfile" class="text-sm font-medium text-gray-950">{{ companyProfile.user.minimum_level }}</span>
                            <div v-else class="h-2.5 bg-gray-200 rounded-full w-10 mt-1 mb-1.5"></div>
                        </div>
                        <div class="flex flex-col items-start justify-center gap-2">
                            <span class="text-xs text-gray-500">{{ $t('public.maximum_level') }}</span>
                            <span v-if="companyProfile" class="text-sm font-medium text-gray-950">{{ companyProfile.user.maximum_level }}</span>
                            <div v-else class="h-2.5 bg-gray-200 rounded-full w-10 mt-1 mb-1.5"></div>
                        </div>
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
                            v-for="index in 5"
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
            <RebateStructureTable />
        </div>
    </AuthenticatedLayout>
</template>
