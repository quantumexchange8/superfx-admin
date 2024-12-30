;<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import {onMounted, ref, watchEffect, watch, reactive} from 'vue';
import { transactionFormat } from '@/Composables/index.js';
import {IconX, IconUserFilled, IconRefresh, IconDotsVertical} from '@tabler/icons-vue';
import Button from '@/Components/Button.vue';
import Calendar from 'primevue/calendar';
import DefaultProfilePhoto from '@/Components/DefaultProfilePhoto.vue';
import NewGroup from '@/Pages/Group/Partials/NewGroup.vue';
import GroupMenu from '@/Pages/Group/Partials/GroupMenu.vue';
import Vue3Autocounter from 'vue3-autocounter';
import Empty from '@/Components/Empty.vue';
import { usePage } from '@inertiajs/vue3';
import SettlementReport from "@/Pages/Group/Partials/SettlementReport.vue";

defineProps({
    groupCount: Number
})

const { formatAmount, formatDate } = transactionFormat();

const counterDuration = ref(10);
const totalNetBalance = ref(0.00);
const totalDeposit = ref(0.00);
const totalWithdrawal = ref(0.00);
const totalFeeCharges = ref(0.00);
const groups = ref([]);
const total = ref();
const isLoading = ref(false);

// Get current date
const today = new Date();

// Define minDate and maxDate
const minDate = ref(new Date(today.getFullYear(), today.getMonth(), 1));
const maxDate = ref(today);

// Reactive variable for selected date range
const selectedDate = ref([minDate.value, maxDate.value]);

// Clear date selection
const clearDate = () => {
    selectedDate.value = [];
};

watch(selectedDate, (newDateRange) => {
    if (Array.isArray(newDateRange)) {
        const [startDate, endDate] = newDateRange;

        if (startDate && endDate) {
            getGroups([startDate, endDate]);
        } else if (startDate || endDate) {
            getGroups([startDate || endDate, endDate || startDate]);
        } else {
            getGroups([]);
        }
    } else {
        console.warn('Invalid date range format:', newDateRange);
    }
});

const getGroups = async (selectedDate = []) => {
    isLoading.value = true
    try {
        let response;
        const [startDate, endDate] = selectedDate;

        let url = `/group/getGroups`;

        if (startDate && endDate) {
            url += `?startDate=${formatDate(startDate)}&endDate=${formatDate(endDate)}`;
        }

        response = await axios.get(url);
        groups.value = response.data.groups;

        total.value = response.data.total;
        totalNetBalance.value = total.value.total_net_balance;
        totalDeposit.value = total.value.total_deposit;
        totalWithdrawal.value = total.value.total_withdrawal;
        totalFeeCharges.value = total.value.total_charges;
        counterDuration.value = 1;
    } catch (error) {
        console.error('Error fetching group:', error);
    } finally {
        isLoading.value = false
    }
};

onMounted(() => {
    getGroups(selectedDate.value);
})

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getGroups(selectedDate.value);
    }
});

const refreshingGroup = reactive({});

const refreshGroup = async (groupId) => {
    try {
        refreshingGroup[groupId] = true;
        let response;
        const [startDate, endDate] = selectedDate.value;

        let url = `/group/refreshGroup`;

        if (selectedDate.value) {
            url += `?startDate=${formatDate(startDate)}&endDate=${formatDate(endDate)}`;
        }

        if (groupId) {
            url += `&group_id=${groupId}`;
        }

        response = await axios.get(url);
        const refreshedGroup = response.data.refreshed_group;

        // Find the index of the group to be replaced
        const groupIndex = groups.value.findIndex(group => group.id === refreshedGroup.id);

        if (groupIndex !== -1) {
            // Replace the group data with the refreshed group data
            groups.value[groupIndex] = refreshedGroup;
        }

        total.value = response.data.total;
        totalNetBalance.value = total.value.total_net_balance;
        totalDeposit.value = total.value.total_deposit;
        totalWithdrawal.value = total.value.total_withdrawal;
        totalFeeCharges.value = total.value.total_charges;
        counterDuration.value = 1;
    } catch (error) {
        console.error('Error fetching group:', error);
    } finally {
        refreshingGroup[groupId] = false;
    }
}
</script>

<template>
    <AuthenticatedLayout title="Group">
        <div class="w-full flex flex-col items-center gap-5">
            <div class="w-full p-4 md:py-6 md:px-8 flex flex-col items-center gap-5 self-stretch rounded-2xl bg-white shadow-toast">
                <div class="w-full flex flex-col justify-center items-start md:flex-row md:items-center md:gap-3 md:self-stretch">
                    <div class="flex flex-col justify-center shrink-0 self-stretch text-gray-950 text-sm font-semibold md:flex-1 md:text-base">
                        {{ $t('public.total_net_balance') }} ($)
                    </div>
                    <div class="self-stretch text-gray-950 text-xxl font-semibold md:flex-1 md:text-right">
                        <vue3-autocounter ref="counter" :startAmount="0" :endAmount="Number(totalNetBalance)" :duration="counterDuration" separator="," decimalSeparator="." :decimals="2" :autoinit="true" />
                    </div>
                </div>

                <div class="w-full flex flex-col items-center gap-3 self-stretch md:flex-row md:gap-5">
                    <div class="py-4 px-6 flex flex-col items-center gap-2 self-stretch md:gap-3 md:flex-1 border-b-4 border-green bg-gradient-to-t from-[#06d00114] to-[#ffffff14]">
                        <div class="self-stretch text-gray-950 text-lg font-semibold md:text-xl">
                            <vue3-autocounter ref="counter" :startAmount="0" :endAmount="Number(totalDeposit)" :duration="counterDuration" separator="," decimalSeparator="." :decimals="2" :autoinit="true" />
                        </div>
                        <div class="text-left w-full text-gray-500 text-sm md:text-xs xl:text-sm">
                            {{ $t('public.total_deposit') }} ($)
                        </div>
                    </div>

                    <div class="py-4 px-6 flex flex-col items-center gap-2 self-stretch md:gap-3 md:flex-1 border-b-4 border-pink bg-gradient-to-t from-[#ff2d5814] to-[#ffffff14]">
                        <div class="self-stretch text-gray-950 text-lg font-semibold md:text-xl">
                            <vue3-autocounter ref="counter" :startAmount="0" :endAmount="Number(totalWithdrawal)" :duration="counterDuration" separator="," decimalSeparator="." :decimals="2" :autoinit="true" />
                        </div>
                        <div class="text-left w-full text-gray-500 text-sm md:text-xs xl:text-sm">
                            {{ $t('public.total_withdrawal') }} ($)
                        </div>
                    </div>

                    <div class="py-4 px-6 flex flex-col items-center gap-2 self-stretch md:gap-3 md:flex-1 border-b-4 border-gray-500 bg-gradient-to-t from-[#F2F4F7] to-[#ffffff14]">
                        <div class="self-stretch text-gray-950 text-lg font-semibold md:text-xl">
                            <vue3-autocounter ref="counter" :startAmount="0" :endAmount="Number(totalFeeCharges)" :duration="counterDuration" separator="," decimalSeparator="." :decimals="2" :autoinit="true" />
                        </div>
                        <div class="text-left w-full text-gray-500 text-sm md:text-xs xl:text-sm">
                            {{ $t('public.total_fee_charges') }} ($)
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-full py-6 px-4 md:p-6 flex flex-col items-center gap-6 self-stretch rounded-2xl border border-solid border-gray-200 bg-white shadow-table">
                <div class="flex flex-col items-center gap-3 self-stretch md:flex-row md:justify-between">
                    <div class="relative w-full md:w-[272px]">
                        <Calendar
                            v-model="selectedDate"
                            selectionMode="range"
                            :manualInput="false"
                            :maxDate="maxDate"
                            dateFormat="dd/mm/yy"
                            showIcon
                            iconDisplay="input"
                            placeholder="yyyy/mm/dd - yyyy/mm/dd"
                            class="w-full md:w-[272px]"
                        />
                        <div
                            v-if="selectedDate && selectedDate.length > 0"
                            class="absolute top-2/4 -mt-2.5 right-4 text-gray-400 select-none cursor-pointer bg-white"
                            @click="clearDate"
                        >
                            <IconX size="20" />
                        </div>
                    </div>
                    <div class="flex flex-col-reverse md:flex-row gap-3 items-center self-stretch">
                        <SettlementReport />
                        <NewGroup />
                    </div>
                </div>

                <template v-if="groupCount === 0 && !groups.length">
                    <Empty :title="$t('public.no_group_header')" :message="$t('public.no_group_caption')" />
                </template>

                <template v-else>
                    <div
                        v-if="isLoading"
                        class="flex flex-col items-center self-stretch"
                    >
                        <div
                            class="py-2 px-4 flex items-center gap-3 self-stretch bg-primary-300"
                        >
                            <div class="flex-1 text-white font-semibold animate-pulse">
                                <div class="h-2.5 bg-primary-200 rounded-full w-40 my-1"></div>
                            </div>
                            <div class="flex items-center gap-2">
                                <IconUserFilled size="16" stroke-width="1.25" color="white" />
                                <div class="text-white text-right text-sm font-medium animate-pulse">
                                    <div class="h-2 bg-primary-200 rounded-full w-8 my-1.5"></div>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 flex flex-col items-center gap-2 self-stretch">
                            <div class="min-w-[240px] pb-3 flex items-center gap-3 self-stretch border-b border-solid border-gray-200">
                                <div class="flex items-center gap-3 flex-1">
                                    <div class="w-7 h-7 rounded-full overflow-hidden">
                                        <DefaultProfilePhoto />
                                    </div>
                                    <div class="flex flex-col items-start flex-1">
                                        <div class="max-w-40 self-stretch overflow-hidden whitespace-nowrap text-gray-950 text-ellipsis text-sm font-medium md:max-w-[500px] xl:max-w-3xl">
                                            <div class="h-2.5 bg-gray-200 rounded-full w-28 my-1"></div>
                                        </div>
                                        <div class="max-w-40 self-stretch overflow-hidden whitespace-nowrap text-gray-500 text-ellipsis text-xs md:max-w-[500px] xl:max-w-3xl">
                                            <div class="h-2 bg-gray-200 rounded-full w-36 my-1.5"></div>
                                        </div>
                                    </div>
                                    <Button
                                        variant="gray-text"
                                        size="sm"
                                        type="button"
                                        iconOnly
                                        pill
                                    >
                                        <IconRefresh size="16" stroke-width="1.25" color="#667085" />
                                    </Button>
                                    <Button
                                        variant="gray-text"
                                        size="sm"
                                        type="button"
                                        iconOnly
                                        pill
                                    >
                                        <IconDotsVertical size="16" stroke-width="1.25" color="#667085" />
                                    </Button>
                                </div>
                            </div>
                            <div class="py-3 grid grid-cols-2 items-start content-start gap-y-3 gap-x-5 self-stretch flex-wrap md:grid-cols-3 md:gap-2 xl:grid-cols-6">
                                <div class="min-w-[100px] flex flex-col items-start gap-1 flex-1 md:min-w-[160px] xl:min-w-max">
                                    <div class="text-gray-500 text-xs">
                                        {{ $t('public.deposit') }} ($)
                                    </div>
                                    <div class="text-gray-950 font-semibold animate-pulse">
                                        <div class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                                    </div>
                                </div>
                                <div class="min-w-[100px] flex flex-col items-start gap-1 flex-1 md:min-w-[160px] xl:min-w-max">
                                    <div class="text-gray-500 text-xs">
                                        {{ $t('public.withdrawal') }} ($)
                                    </div>
                                    <div class="text-gray-950 font-semibold animate-pulse">
                                        <div class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                                    </div>
                                </div>
                                <div class="min-w-[100px] flex flex-col items-start gap-1 flex-1 md:min-w-[160px] xl:min-w-max">
                                    <div class="text-gray-500 text-xs">
                                        {{ $t('public.fee_charges') }} ($)
                                    </div>
                                    <div class="text-gray-950 font-semibold animate-pulse">
                                        <div class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                                    </div>
                                </div>
                                <div class="min-w-[100px] flex flex-col items-start gap-1 flex-1 md:min-w-[160px] xl:min-w-max">
                                    <div class="text-gray-500 text-xs">
                                        {{ $t('public.net_balance') }} ($)
                                    </div>
                                    <div class="text-gray-950 font-semibold animate-pulse">
                                        <div class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                                    </div>
                                </div>
                                <div class="min-w-[100px] flex flex-col items-start gap-1 flex-1 md:min-w-[160px] xl:min-w-max">
                                    <div class="text-gray-500 text-xs">
                                        {{ $t('public.account_balance') }} ($)
                                    </div>
                                    <div class="text-gray-950 font-semibold animate-pulse">
                                        <div class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                                    </div>
                                </div>
                                <div class="min-w-[100px] flex flex-col items-start gap-1 flex-1 md:min-w-[160px] xl:min-w-max">
                                    <div class="text-gray-500 text-xs">
                                        {{ $t('public.account_equity') }} ($)
                                    </div>
                                    <div class="text-gray-950 font-semibold animate-pulse">
                                        <div class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="w-full">
                        <div
                            v-for="(group, index) in groups"
                            :key="index"
                            class="flex flex-col items-center self-stretch"
                        >
                            <div
                                class="py-2 px-4 flex items-center gap-3 self-stretch"
                                :style="{'backgroundColor': `#${group.color}`}"
                            >
                                <div class="flex-1 text-white font-semibold">
                                    {{ group.name }}
                                </div>
                                <div class="flex items-center gap-2">
                                    <IconUserFilled size="16" stroke-width="1.25" color="white" />
                                    <div class="text-white text-right text-sm font-medium">
                                        {{ formatAmount(group.member_count, 0) }}
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 flex flex-col items-center gap-2 self-stretch">
                                <div class="min-w-[240px] pb-3 flex items-center gap-3 self-stretch border-b border-solid border-gray-200">
                                    <div class="flex items-center gap-3 flex-1">
                                        <div class="w-7 h-7 rounded-full overflow-hidden">
                                            <template v-if="group.profile_photo">
                                                <img :src="group.profile_photo" alt="group_leader_profile_photo">
                                            </template>
                                            <template v-else>
                                                <DefaultProfilePhoto />
                                            </template>
                                        </div>
                                        <div class="flex flex-col items-start flex-1">
                                            <div class="max-w-40 self-stretch overflow-hidden whitespace-nowrap text-gray-950 text-ellipsis text-sm font-medium md:max-w-[500px] xl:max-w-3xl">
                                                {{ group.leader_name }}
                                            </div>
                                            <div class="max-w-40 self-stretch overflow-hidden whitespace-nowrap text-gray-500 text-ellipsis text-xs md:max-w-[500px] xl:max-w-3xl">
                                                {{ group.leader_email }}
                                            </div>
                                        </div>
                                        <Button
                                            variant="gray-text"
                                            size="sm"
                                            type="button"
                                            iconOnly
                                            pill
                                            @click="refreshGroup(group.id)"
                                        >
                                            <div :class="{ 'animate-spin': refreshingGroup[group.id] }">
                                                <IconRefresh size="16" stroke-width="1.25" color="#667085" />
                                            </div>
                                        </Button>
                                        <GroupMenu :group="group" :date="selectedDate" />
                                    </div>
                                </div>
                                <div class="py-3 grid grid-cols-2 items-start content-start gap-y-3 gap-x-5 self-stretch flex-wrap md:grid-cols-3 md:gap-2 xl:grid-cols-6">
                                    <div class="min-w-[100px] flex flex-col items-start gap-1 flex-1 md:min-w-[160px] xl:min-w-max">
                                        <div class="text-gray-500 text-xs">
                                            {{ $t('public.deposit') }} ($)
                                        </div>
                                        <div
                                            class="text-gray-950 font-semibold"
                                            :class="{'animate-pulse': refreshingGroup[group.id] }"
                                        >
                                            <div v-if="refreshingGroup[group.id]" class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                                            <span v-else>{{ formatAmount(group.deposit) }}</span>
                                        </div>
                                    </div>
                                    <div class="min-w-[100px] flex flex-col items-start gap-1 flex-1 md:min-w-[160px] xl:min-w-max">
                                        <div class="text-gray-500 text-xs">
                                            {{ $t('public.withdrawal') }} ($)
                                        </div>
                                        <div
                                            class="text-gray-950 font-semibold"
                                            :class="{'animate-pulse': refreshingGroup[group.id] }"
                                        >
                                            <div v-if="refreshingGroup[group.id]" class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                                            <span v-else>{{ formatAmount(group.withdrawal) }}</span>
                                        </div>
                                    </div>
                                    <div class="min-w-[100px] flex flex-col items-start gap-1 flex-1 md:min-w-[160px] xl:min-w-max">
                                        <div class="text-gray-500 text-xs">
                                            {{ group.fee_charges }}% {{ $t('public.fee_charges') }} ($)
                                        </div>
                                        <div
                                            class="text-gray-950 font-semibold"
                                            :class="{'animate-pulse': refreshingGroup[group.id] }"
                                        >
                                            <div v-if="refreshingGroup[group.id]" class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                                            <span v-else>{{ formatAmount(group.transaction_fee_charges) }}</span>
                                        </div>
                                    </div>
                                    <div class="min-w-[100px] flex flex-col items-start gap-1 flex-1 md:min-w-[160px] xl:min-w-max">
                                        <div class="text-gray-500 text-xs">
                                            {{ $t('public.net_balance') }} ($)
                                        </div>
                                        <div
                                            class="text-gray-950 font-semibold"
                                            :class="{'animate-pulse': refreshingGroup[group.id] }"
                                        >
                                            <div v-if="refreshingGroup[group.id]" class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                                            <span v-else>{{ formatAmount(group.net_balance) }}</span>
                                        </div>
                                    </div>
                                    <div class="min-w-[100px] flex flex-col items-start gap-1 flex-1 md:min-w-[160px] xl:min-w-max">
                                        <div class="text-gray-500 text-xs">
                                            {{ $t('public.account_balance') }} ($)
                                        </div>
                                        <div
                                            class="text-gray-950 font-semibold"
                                            :class="{'animate-pulse': refreshingGroup[group.id] }"
                                        >
                                            <div v-if="refreshingGroup[group.id]" class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                                            <span v-else>{{ formatAmount(group.account_balance) }}</span>
                                        </div>
                                    </div>
                                    <div class="min-w-[100px] flex flex-col items-start gap-1 flex-1 md:min-w-[160px] xl:min-w-max">
                                        <div class="text-gray-500 text-xs">
                                            {{ $t('public.account_equity') }} ($)
                                        </div>
                                        <div
                                            class="text-gray-950 font-semibold"
                                            :class="{'animate-pulse': refreshingGroup[group.id] }"
                                        >
                                            <div v-if="refreshingGroup[group.id]" class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                                            <span v-else>{{ formatAmount(group.account_equity) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
