<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import IllustrationGreetings from '@/Pages/Dashboard/Partials/IllustrationGreetings.vue';
import Button from '@/Components/Button.vue';
import { IconRefresh, IconChevronRight, IconChevronDown } from '@tabler/icons-vue';
import { transactionFormat } from '@/Composables/index.js';
import { DepositIcon, WithdrawalIcon, RebateIcon } from '@/Components/Icons/solid';
import Badge from '@/Components/Badge.vue';
import Vue3Autocounter from 'vue3-autocounter';
import { ref, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Dropdown from "primevue/dropdown";
import dayjs from "dayjs";
import DashboardForum from "@/Pages/Dashboard/Partials/DashboardForum.vue";

const props = defineProps({
    postCounts: Number
})

const page = usePage();

const { formatAmount } = transactionFormat();

const counterDuration = ref(10);
const accountBalanceDuration = ref(10);
const balance = ref(99999.00)
const equity = ref(99999.00)
const pendingWithdrawal = ref(0)
const netAsset = ref(99999.00)
const totalDeposit = ref(99999.00)
const totalWithdrawal = ref(99999.00)
const totalRebate = ref(99999.00)

const pendingWithdrawalCount = ref(0)
const counterEquity = ref(null);
const counterBalance = ref(null);

const selectedMonth = ref('');
const currentYear = dayjs().year();
const transactionMonth = ref([]);

// Populate historyPeriodOptions with all months of the current year
for (let month = 1; month <= 12; month++) {
    transactionMonth.value.push({
        value: dayjs().month(month - 1).year(currentYear).format('MM/YYYY')
    });
}

selectedMonth.value = dayjs().format('MM/YYYY');

const updateBalEquity = () => {
    counterEquity.value.reset();
    counterBalance.value.reset();
    getAccountData();
}

const getAccountData = async () => {
    try {
        const response = await axios.get('/getAccountData');
        balance.value = response.data.totalBalance;
        equity.value = response.data.totalEquity;

        accountBalanceDuration.value = 1
    } catch (error) {
        console.error('Error accounts data:', error);
    }
};

const getPendingData = async () => {
    try {
        const response = await axios.get('/getPendingData');
        pendingWithdrawal.value = parseFloat(response.data.pendingAmount);
        pendingWithdrawalCount.value = parseFloat(response.data.pendingCounts);
    } catch (error) {
        console.error('Error pending data:', error);
    } finally {
        counterDuration.value = 1
    }
};

const getAssetData = async (selectedMonth) => {
    try {
        // Base URL
        let url = '/getAssetData';

        // Append month query parameter if selectedMonth is provided
        if (selectedMonth) {
            url += `?month=${(selectedMonth)}`;
        }

        // Make the GET request
        const response = await axios.get(url);

        // Process the response
        totalDeposit.value = parseFloat(response.data.totalDeposit);
        totalWithdrawal.value = parseFloat(response.data.totalWithdrawal);
        totalRebate.value = parseFloat(response.data.totalRebatePayout);
        netAsset.value = parseFloat(totalDeposit.value - totalWithdrawal.value);
    } catch (error) {
        console.error('Error fetching asset data:', error);
    } finally {
        counterDuration.value = 1
    }
};


// Watch for changes to selectedMonth
watch(selectedMonth, (newMonth) => {
    getAssetData(newMonth);
});

getAccountData();
getPendingData();
getAssetData(selectedMonth.value);

const goToTransactionPage = (type) => {
    // Navigate to the transaction page with a query parameter
    window.location.href = `/transaction?type=${type}`;
};

</script>

<template>
    <AuthenticatedLayout :title="$t('public.dashboard')">
        <div class="w-full flex flex-col items-center gap-5">
            <div
                class="w-full h-40 py-6 pl-4 md:p-8 flex justify-between self-stretch rounded-2xl bg-primary-100 shadow-toast relative bg-[left_-1px] bg-no-repeat xl:bg-[length:1440px]"
                style="background-image: url('/img/background-greetings.svg');"
            >
                <div class="w-3/4 md:w-full flex flex-col items-start gap-1">
                    <div class="self-stretch text-primary-900 text-base md:text-xxl font-bold">
                        {{ $t('public.greeting') }}
                    </div>
                    <div class="self-stretch text-primary-900 text-xs md:text-base">
                        {{ $t('public.greeting_caption') }}
                    </div>
                </div>

                <div class="absolute right-0 bottom-0">
                    <IllustrationGreetings />
                </div>
            </div>

            <div class="w-full flex flex-col md:flex-row justify-center items-center gap-5 self-stretch">
                <div class="p-4 md:py-6 md:px-8 flex flex-col items-center gap-8 flex-1 self-stretch rounded-2xl shadow-toast bg-white">
                    <div class="flex items-center self-stretch">
                        <div class="flex-1 text-gray-950 font-semibold">
                            {{ $t('public.account_balance_equity') }}
                        </div>
                        <Button
                            variant="gray-text"
                            size="sm"
                            type="button"
                            iconOnly
                            v-slot="{ iconSizeClasses }"
                            @click="updateBalEquity()"
                        >
                            <IconRefresh size="16" stroke-width="1.25" color="#667085" />
                        </Button>
                    </div>

                    <div class="flex items-center gap-3 self-stretch">
                        <div class="flex flex-col items-start gap-2 flex-1">
                            <div class="self-stretch text-gray-500 text-xs md:text-sm">
                                {{ $t('public.balance') }} ($)
                            </div>
                            <div class="text-gray-950 text-lg md:text-xl font-semibold">
                                <vue3-autocounter ref="counterBalance" :startAmount="0" :endAmount="Number(balance)" :duration="accountBalanceDuration" separator="," decimalSeparator="." :decimals="2" :autoinit="true" />
                            </div>
                        </div>

                        <div class="w-px h-14 rounded-xl bg-gray-300"></div>

                        <div class="flex flex-col items-end gap-2 flex-1">
                            <div class="self-stretch text-gray-500 text-right text-xs md:text-sm">
                                {{ $t('public.equity') }} ($)
                            </div>
                            <div class="text-gray-950 text-lg md:text-xl font-semibold">
                                <vue3-autocounter ref="counterEquity" :startAmount="0" :endAmount="Number(equity)" :duration="accountBalanceDuration" separator="," decimalSeparator="." :decimals="2" :autoinit="true" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 md:py-6 md:px-8 flex flex-col items-start gap-4 flex-1 self-stretch rounded-2xl shadow-toast bg-white">
                    <div class="flex items-center self-stretch">
                        <div class="flex-1 text-gray-950 font-semibold text-sm md:text-base">
                            {{ $t('public.pending_withdrawal') }} ($)
                        </div>
                        <Button
                            external
                            variant="gray-text"
                            size="sm"
                            type="button"
                            iconOnly
                            :href="route('pending')"
                        >
                            <IconChevronRight size="16" stroke-width="1.25" color="#667085"/>
                        </Button>
                    </div>

                    <div class="self-stretch text-gray-950 text-xl md:text-xxl font-semibold">
                        <vue3-autocounter ref="counter" :startAmount="0" :endAmount="pendingWithdrawal" :duration="1" separator="," decimalSeparator="." :decimals="2" :autoinit="true" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Badge class="text-white text-sm">
                            {{ pendingWithdrawalCount }}
                        </Badge>
                        <div class="text-gray-500 text-xs md:text-sm">
                            {{ $t('public.pending_withdrawal_caption') }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-full h-full flex flex-col md:flex-row justify-center items-center gap-5 self-stretch">
                <div class="p-4 md:py-6 md:px-8 flex flex-col items-start gap-5 flex-1 self-stretch rounded-2xl bg-white shadow-toast">
                    <div class="flex items-start gap-5 self-stretch">
                        <div class="flex flex-col items-start flex-1">
                            <div class="py-2 text-gray-500 text-sm">
                                {{ $t('public.net_asset') }} ($)
                            </div>
                            <div class="text-gray-950 text-xl md:text-xxl font-semibold">
                                <vue3-autocounter ref="counter" :startAmount="0" :endAmount="netAsset" :duration="counterDuration" separator="," decimalSeparator="." :decimals="2" :autoinit="true" />
                            </div>
                        </div>
                        <Dropdown
                            v-model="selectedMonth"
                            :options="transactionMonth"
                            optionLabel="value"
                            optionValue="value"
                            :placeholder="$t('public.month_placeholder')"
                            scroll-height="236px"
                            :pt="{
                                root: 'inline-flex items-center justify-center relative rounded-lg bg-gray-100 px-3 py-2 gap-3 cursor-pointer overflow-hidden overflow-ellipsis whitespace-nowrap appearance-none',
                                input: 'text-sm font-medium block flex-auto relative focus:outline-none',
                                trigger: 'w-4 h-4 flex items-center justify-center shrink-0',
                            }"
                        />
                    </div>

                    <div class="flex flex-col justify-center items-center gap-4 self-stretch">
                        <div class="py-3 flex items-center gap-4 self-stretch rounded-xl">
                            <DepositIcon />

                            <div class="flex flex-col items-start gap-1 flex-1">
                                <div class="self-stretch text-gray-500 text-xs">
                                    {{ $t('public.total_deposit') }} ($)
                                </div>
                                <div class="self-stretch text-gray-950 text-base md:text-lg font-semibold">
                                    <vue3-autocounter ref="counter" :startAmount="0" :endAmount="totalDeposit" :duration="counterDuration" separator="," decimalSeparator="." :decimals="2" :autoinit="true" />
                                </div>
                            </div>
                            <Button
                                variant="gray-outlined"
                                size="sm"
                                type="button"
                                @click="goToTransactionPage('deposit')"
                            >
                                {{ $t('public.details') }}
                            </Button>
                        </div>

                        <div class="py-3 flex items-center gap-4 self-stretch rounded-xl">
                            <WithdrawalIcon />

                            <div class="flex flex-col items-start gap-1 flex-1">
                                <div class="self-stretch text-gray-500 text-xs">
                                    {{ $t('public.total_withdrawal') }} ($)
                                </div>
                                <div class="self-stretch text-gray-950 text-base md:text-lg font-semibold">
                                    <vue3-autocounter ref="counter" :startAmount="0" :endAmount="totalWithdrawal" :duration="counterDuration" separator="," decimalSeparator="." :decimals="2" :autoinit="true" />
                                </div>
                            </div>
                            <Button
                                variant="gray-outlined"
                                size="sm"
                                type="button"
                                @click="goToTransactionPage('withdrawal')"
                            >
                                {{ $t('public.details') }}
                            </Button>
                        </div>

                        <div class="py-3 flex items-center gap-4 self-stretch rounded-xl">
                            <RebateIcon />

                            <div class="flex flex-col items-start gap-1 flex-1">
                                <div class="self-stretch text-gray-500 text-xs">
                                    {{ $t('public.total_rebate_payout') }} ($)
                                </div>
                                <div class="self-stretch text-gray-950 text-base md:text-lg font-semibold">
                                    <vue3-autocounter ref="counter" :startAmount="0" :endAmount="totalRebate" :duration="counterDuration" separator="," decimalSeparator="." :decimals="2" :autoinit="true" />
                                </div>
                            </div>
                            <Button
                                variant="gray-outlined"
                                size="sm"
                                type="button"
                                @click="goToTransactionPage('payout')"
                            >
                                {{ $t('public.details') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="p-4 md:py-6 md:px-8 flex flex-col gap-5 flex-1 self-stretch items-stretch rounded-2xl bg-white shadow-toast">
                    <div class="flex items-center self-stretch">
                        <div class="flex-1 text-gray-950 font-semibold">
                            {{ $t('public.recent_posts') }}
                        </div>
                        <Button
                            variant="gray-text"
                            size="sm"
                            type="button"
                            iconOnly
                            v-slot="{ iconSizeClasses }"
                            external
                            :href="route('member.forum')"
                        >
                            <IconChevronRight size="16" stroke-width="1.25" color="#667085" />
                        </Button>
                    </div>

                    <DashboardForum
                        :postCounts="postCounts"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
