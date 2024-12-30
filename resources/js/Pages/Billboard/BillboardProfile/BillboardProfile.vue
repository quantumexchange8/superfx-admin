<script setup>
import {
    IconCircleXFilled,
    IconSearch,
    IconChartArcs,
    IconCalendarStats, IconAdjustments,
} from "@tabler/icons-vue";
import InputText from "primevue/inputtext";
import {ref, watch, watchEffect} from "vue";
import AddBonusProfile from "@/Pages/Billboard/BillboardProfile/AddBonusProfile.vue";
import Empty from "@/Components/Empty.vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import ProfileAction from "@/Pages/Billboard/BillboardProfile/ProfileAction.vue";
import {transactionFormat} from "@/Composables/index.js";
import ProgressBar from 'primevue/progressbar';
import {usePage} from "@inertiajs/vue3";
import Paginator from "primevue/paginator";
import StatusBadge from "@/Components/StatusBadge.vue";
import Badge from "@/Components/Badge.vue";
import Button from "@/Components/Button.vue";
import OverlayPanel from "primevue/overlaypanel";
import RadioButton from "primevue/radiobutton";
import debounce from "lodash/debounce.js";

const props = defineProps({
    profileCount: Number
})

const search = ref('');
const salesMode = ref('');
const salesCategory = ref('');
const isLoading = ref(true);
const bonusProfiles = ref([]);
const currentPage = ref(1);
const rowsPerPage = ref(6);
const totalRecords = ref(0);
const {formatAmount} = transactionFormat();

const clearSearch = () => {
    search.value = '';
}

const getResults = async (page = 1, filterRowsPerPage = rowsPerPage.value) => {
    isLoading.value = true;

    try {
        let url = `/billboard/getBonusProfiles?page=${page}&paginate=${filterRowsPerPage}`;

        if (salesMode.value) {
            url += `&sales_calculation_mode=${salesMode.value}`;
        }

        if (salesCategory.value) {
            url += `&sales_category=${salesCategory.value}`;
        }

        if (search.value) {
            url += `&search=${search.value}`;
        }

        const response = await axios.get(url);
        bonusProfiles.value = response.data.bonusProfiles;
        totalRecords.value = response.data.totalRecords;
        currentPage.value = response.data.currentPage;
    } catch (error) {
        console.error('Error getting masters:', error);
    } finally {
        isLoading.value = false;
    }
}

getResults();

// overlay panel
const op = ref();
const filterCount = ref(0);

const toggle = (event) => {
    op.value.toggle(event);
}

const clearFilter = (event) => {
    salesCategory.value = '';
    salesMode.value = '';
    search.value = '';

    op.value.toggle(event);
};

watch(search, debounce(() => {
    getResults(currentPage.value, rowsPerPage.value);
}, 300));

watch([salesMode, salesCategory], () => {
    filterCount.value = [salesMode, salesCategory].reduce((count, ref) => {
        if (Array.isArray(ref.value) ? ref.value.length > 0 : ref.value) count++;
        return count;
    }, 0);

    getResults(currentPage.value, rowsPerPage.value);
});

const onPageChange = (event) => {
    currentPage.value = event.page + 1;
    getResults(currentPage.value, rowsPerPage.value);
};

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults();
    }
});
</script>

<template>
    <div class="flex flex-col md:flex-row gap-3 items-center self-stretch">
        <div class="relative w-full md:w-60">
            <div class="absolute top-2/4 -mt-[9px] left-4 text-gray-400">
                <IconSearch size="20" stroke-width="1.25" />
            </div>
            <InputText v-model="search" :placeholder="$t('public.keyword_search')" class="font-normal pl-12 w-full md:w-60" />
            <div
                v-if="search"
                class="absolute top-2/4 -mt-2 right-4 text-gray-300 hover:text-gray-400 select-none cursor-pointer"
                @click="clearSearch"
            >
                <IconCircleXFilled size="16" />
            </div>
        </div>

        <div class="w-full flex md:justify-between items-center self-stretch gap-3">
            <Button
                variant="gray-outlined"
                @click="toggle"
                size="sm"
                class="flex gap-3 items-center justify-center py-3 w-[60px] xxs:w-[130px]"
            >
                <IconAdjustments size="20" color="#0C111D" stroke-width="1.25" />
                <div class="text-sm text-gray-950 font-medium hidden xxs:block">
                    {{ $t('public.filter') }}
                </div>
                <Badge class="w-5 h-5 text-xs text-white hidden xxs:block" variant="numberbadge">
                    {{ filterCount }}
                </Badge>
            </Button>

            <!-- Add Profile -->
            <AddBonusProfile />
        </div>

    </div>

    <div v-if="profileCount === 0 && !bonusProfiles.length">
        <Empty
            :title="$t('public.no_bonus_profiles_created_yet')"
            :message="$t('public.no_bonus_profiles_created_yet_caption')"
        >
            <template #image>
                <img src="/img/no_data/no_bonus_profiles_created.svg" alt="no data" />
            </template>
        </Empty>
    </div>

    <div
        v-else
        :class="{
            'grid md:grid-cols-2': isLoading
        }"
    >
        <div
            v-if="isLoading"
            class="py-4 px-6 md:p-6 flex flex-col items-center gap-3 md:gap-4 self-stretch rounded-2xl shadow-toast bg-white"
        >
            <div class="flex flex-col items-center self-stretch">
                <div class="flex items-start w-full h-9">
                    <StatusBadge value="gray">{{ 'category' }}</StatusBadge>
                </div>
                <div class="flex flex-col items-center gap-2 self-stretch -mt-3">
                    <div class="w-[42px] h-[42px] shrink-0 grow-0 rounded-full overflow-hidden">
                        <DefaultProfilePhoto />
                    </div>
                    <div class="flex flex-col items-center self-stretch animate-pulse">
                        <div class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                        <div class="h-2 bg-gray-200 rounded-full w-36 my-1.5"></div>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 py-2 items-center justify-center w-full">
                <!-- Target amount -->
                <div class="flex flex-col items-center w-full animate-pulse">
                    <div class="h-3 bg-gray-200 rounded-full w-20 mt-2 mb-1"></div>
                    <div class="h-2 bg-gray-200 rounded-full w-14 my-1.5"></div>
                </div>

                <!-- Target amount -->
                <div class="flex flex-col items-center w-full animate-pulse">
                    <div class="h-3 bg-gray-200 rounded-full w-20 mt-2 mb-1"></div>
                    <div class="h-2 bg-gray-200 rounded-full w-14 my-1.5"></div>
                </div>
            </div>

            <!-- Progress bar -->
            <div class="flex flex-col gap-1.5 items-center self-stretch w-full">
                <ProgressBar
                    class="w-full"
                    :value="0"
                    :show-value="false"
                />
                <div class="flex justify-between items-center self-stretch text-gray-950 font-medium animate-pulse">
                    <div class="h-2 bg-gray-200 rounded-full w-14 my-2"></div>
                    <div class="h-2 bg-gray-200 rounded-full w-14 my-2"></div>
                </div>
            </div>

            <div class="flex flex-col gap-1 items-center self-stretch w-full animate-pulse">
                <div class="py-1 flex gap-3 items-center self-stretch">
                    <IconChartArcs size="20" stroke-width="1.25" color="#667085" />
                    <div class="h-2 bg-gray-200 rounded-full w-40 my-1.5"></div>
                </div>
                <div class="py-1 flex gap-3 items-center self-stretch">
                    <IconCalendarStats size="20" stroke-width="1.25" color="#667085" />
                    <div class="h-2 bg-gray-200 rounded-full w-40 my-1.5"></div>
                </div>
            </div>
        </div>

        <div v-else-if="!bonusProfiles.length">
            <Empty
                :title="$t('public.no_bonus_profiles_created_yet')"
                :message="$t('public.no_bonus_profiles_created_yet_caption')"
            >
                <template #image>
                    <img src="/img/no_data/no_bonus_profiles_created.svg" alt="no data" />
                </template>
            </Empty>
        </div>

        <div
            v-else
            class="grid grid-cols-1 md:grid-cols-2 gap-5 self-stretch"
        >
            <div
                v-for="profile in bonusProfiles"
                class="py-4 px-6 md:p-6 flex flex-col items-center gap-3 md:gap-4 self-stretch rounded-2xl shadow-toast bg-white"
            >
                <div class="flex flex-col items-center self-stretch">
                    <ProfileAction
                        :profile="profile"
                    />
                    <div class="flex flex-col items-center gap-2 self-stretch -mt-3">
                        <div class="w-[42px] h-[42px] shrink-0 grow-0 rounded-full overflow-hidden">
                            <div v-if="profile.profile_photo">
                                <img :src="profile.profile_photo" alt="Profile Photo" />
                            </div>
                            <div v-else>
                                <DefaultProfilePhoto />
                            </div>
                        </div>
                        <div class="flex flex-col items-center self-stretch">
                            <span class="text-gray-950 text-base font-bold">{{ profile.name }}</span>
                            <span class="text-gray-500 text-sm">{{ profile.email }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 py-2 items-center justify-center w-full">
                    <!-- Target amount -->
                    <div class="flex flex-col items-center w-full">
                        <span class="text-lg font-semibold text-primary-500">{{ profile.bonus_amount > 0 ? ('$ ') + (profile.bonus_amount % 1 === 0 ? formatAmount(profile.bonus_amount, 0) : formatAmount(profile.bonus_amount)) : '-' }}</span>
                        <span class="text-xs text-gray-500">{{ $t('public.bonus') }}</span>
                    </div>

                    <!-- Target amount -->
                    <div class="flex flex-col items-center w-full">
                        <span class="text-lg font-semibold text-gray-950">{{ profile.achieved_percentage > 0 ? formatAmount(profile.achieved_percentage) + '%' : '-' }}</span>
                        <span class="text-xs text-gray-500">{{ $t('public.achieved') }}</span>
                    </div>
                </div>

                <!-- Progress bar -->
                <div class="flex flex-col gap-1.5 items-center self-stretch w-full">
                    <ProgressBar
                        class="w-full"
                        :value="profile.achieved_percentage"
                        :show-value="false"
                    />
                    <div class="flex justify-between items-center self-stretch text-gray-950 font-medium">
                        <div>
                            <span v-if="profile.sales_category !== 'trade_volume'">$</span>
                            {{ profile.achieved_amount % 1 === 0 ? formatAmount(profile.achieved_amount, 0) : formatAmount(profile.achieved_amount) }} <span v-if="profile.sales_category === 'trade_volume'">Ł</span>
                        </div>
                        <div>
                            <span v-if="profile.sales_category !== 'trade_volume'">$</span>
                            {{ profile.target_amount % 1 === 0 ? formatAmount(profile.target_amount, 0) : formatAmount(profile.target_amount) }} <span v-if="profile.sales_category === 'trade_volume'">Ł</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-1 items-center self-stretch w-full">
                    <div class="py-1 flex gap-3 items-center self-stretch">
                        <IconChartArcs size="20" stroke-width="1.25" color="#667085" />
                        <span class="text-sm text-gray-950 font-medium">{{ $t(`public.${profile.sales_category}`) }}</span>
                    </div>
                    <div class="py-1 flex gap-3 items-center self-stretch">
                        <IconCalendarStats size="20" stroke-width="1.25" color="#667085" />
                        <span class="text-sm text-gray-950 font-medium">{{ $t(`public.${profile.calculation_period}`) }}</span>
                    </div>
                </div>
            </div>
        </div>
        <Paginator
            v-if="!isLoading"
            :first="(currentPage - 1) * rowsPerPage"
            :rows="rowsPerPage"
            :totalRecords="totalRecords"
            @page="onPageChange"
        />
    </div>

    <OverlayPanel ref="op">
        <div class="w-60 flex flex-col items-center">
            <div class="flex flex-col gap-8 w-60 py-5 px-4">
                <!-- Filter sales mode -->
                <div class="flex flex-col items-center gap-2 self-stretch">
                    <span class="self-stretch text-gray-950 text-xs font-bold">{{ $t('public.filter_sales_calculation_mode') }}</span>
                    <div class="flex flex-col gap-1 self-stretch">
                        <div class="flex items-center gap-2 text-sm text-gray-950">
                            <RadioButton v-model="salesMode" inputId="personal_sales" value="personal_sales" class="w-4 h-4" />
                            <label for="personal_sales">{{ $t('public.personal_sales') }}</label>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-950">
                            <RadioButton v-model="salesMode" inputId="group_sales" value="group_sales" class="w-4 h-4" />
                            <label for="group_sales">{{ $t('public.group_sales') }}</label>
                        </div>
                    </div>
                </div>

                <!-- Filter sales category -->
                <div class="flex flex-col items-center gap-2 self-stretch">
                    <span class="self-stretch text-gray-950 text-xs font-bold">{{ $t('public.filter_sales_category') }}</span>
                    <div class="flex flex-col gap-1 self-stretch">
                        <div class="flex items-center gap-2 text-sm text-gray-950">
                            <RadioButton v-model="salesCategory" inputId="gross_deposit" value="gross_deposit" class="w-4 h-4" />
                            <label for="gross_deposit">{{ `${$t('public.gross_deposit')}` }}</label>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-950">
                            <RadioButton v-model="salesCategory" inputId="net_deposit" value="net_deposit" class="w-4 h-4" />
                            <label for="net_deposit">{{ $t('public.net_deposit') }}</label>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-950">
                            <RadioButton v-model="salesCategory" inputId="trade_volume" value="trade_volume" class="w-4 h-4" />
                            <label for="trade_volume">{{ $t('public.trade_volume') }}</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full flex justify-center items-center py-5 px-4 border-t border-gray-200">
                <Button
                    type="button"
                    variant="primary-outlined"
                    class="flex justify-center w-full"
                    @click="clearFilter()"
                >
                    {{ $t('public.clear_all') }}
                </Button>
            </div>
        </div>
    </OverlayPanel>
</template>
