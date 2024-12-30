<script setup>
import { ref, watch, watchEffect } from "vue";
import { transactionFormat } from "@/Composables/index.js";
import { usePage } from "@inertiajs/vue3";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import {
    IconSearch,
    IconCircleXFilled,
    IconUserDollar,
    IconPremiumRights,
    IconAdjustments,
    IconScanEye,
    IconAlertCircleFilled,
    IconHeartFilled,
    IconHeart,
} from '@tabler/icons-vue';
import InputText from 'primevue/inputtext';
import Button from '@/Components/Button.vue';
import Badge from '@/Components/Badge.vue';
import Dropdown from "primevue/dropdown";
import Action from "@/Pages/PammAllocate/Partials/Action.vue";
import OverlayPanel from 'primevue/overlaypanel';
import { NoAssetMasterIcon } from '@/Components/Icons/solid';
import Empty from '@/Components/Empty.vue';
import MultiSelect from 'primevue/multiselect';
import RadioButton from 'primevue/radiobutton';
import Paginator from 'primevue/paginator';
import Loader from "@/Components/Loader.vue";
import debounce from "lodash/debounce.js";
import Checkbox from "primevue/checkbox";

const { formatAmount } = transactionFormat();

const props = defineProps({
    groupsOptions: Array,
})
const loading = ref(false);
const masters = ref([]);
const groupsOptions = ref(props.groupsOptions);
const selectedGroups = ref([]);
const checked = ref(false);
const adminUser = ref();
const tag = ref();
const status = ref();
const search = ref();
const currentPage = ref(1);
const rowsPerPage = ref(12);
const totalRecords = ref(0);

// Define sort options
const sortOptions = ref([
    { name: 'latest', value: 'latest' },
    { name: 'popular', value: 'popular' },
    { name: 'largest_fund', value: 'largest_fund' },
    { name: 'most_investor', value: 'most_investors' },
]);

// Initialize sortType with the default value
const sortType = ref(sortOptions.value[0]);


const getResults = async (page = 1, rowsPerPage = 12) => {
    loading.value = true;

    try {
        let url = `/pamm_allocate/getMasters?page=${page}&limit=${rowsPerPage}`;

        if (sortType.value && sortType.value.value) {
            url += `&sortType=${sortType.value.value}`;
        }

        if (selectedGroups.value.length > 0) {
            const groupNames = selectedGroups.value.map(item => item.value).join(',');
            url += `&groups=${groupNames}`;
        }

        if (adminUser.value) {
            url += `&adminUser=${adminUser.value}`;
        }

        if (tag.value) {
            url += `&tag=${tag.value}`;
        }

        if (status.value) {
            url += `&status=${status.value}`;
        }

        if (search.value) {
            url += `&search=${search.value}`;
        }

        const response = await axios.get(url);
        masters.value = response.data.masters;
        totalRecords.value = response.data.totalRecords;
        currentPage.value = response.data.currentPage;
    } catch (error) {
        console.error('Error getting masters:', error);
    } finally {
        loading.value = false;
    }
};

// Initial call to populate data
getResults(currentPage.value, rowsPerPage.value);

const onPageChange = (event) => {
    currentPage.value = event.page + 1;
    getResults(currentPage.value, rowsPerPage.value);
};

watch(() => [props.groupsOptions], ([newgroups]) => {
        groupsOptions.value = newgroups;
    }, { immediate: true }
);


const clearSearch = () => {
    search.value = '';
};

// overlay panel
const op = ref();
const filterCount = ref(0);

const toggle = (event) => {
    op.value.toggle(event);
}

const clearFilter = (event) => {
    selectedGroups.value = [];
    adminUser.value = '';
    tag.value = '';
    status.value = '';

    op.value.toggle(event);
};

// Watchers for search and sortType
watch(search, debounce(() => {
    getResults(currentPage.value, rowsPerPage.value);
}, 300));

// Watchers for sortType
watch(sortType, () => {
    getResults(currentPage.value, rowsPerPage.value);
});

// Watchers for filters
watch([selectedGroups, adminUser, tag, status], () => {
    filterCount.value = [selectedGroups, adminUser, tag, status].reduce((count, ref) => {
        if (Array.isArray(ref.value) ? ref.value.length > 0 : ref.value) count++;
        return count;
    }, 0);

    getResults(currentPage.value, rowsPerPage.value);
});

// Define constants and reactive data
const PUBLIC_OPTION = { value: 'public', name: 'Public', color: 'ffffff' };

// Check if 'Public' is selected
function isPublicChecked() {
    return selectedGroups.value.some(item => item.value === PUBLIC_OPTION.value);
}

// Handle checkbox change and div click
function togglePublicSelection() {
    const isCurrentlyChecked = isPublicChecked();

    if (isCurrentlyChecked) {
        // Remove 'Public' from selection if it's currently selected
        selectedGroups.value = selectedGroups.value.filter(item => item.value !== PUBLIC_OPTION.value);
    } else {
        // Add 'Public' to selection and remove all other selections
        selectedGroups.value = [PUBLIC_OPTION];
    }

    // Update `checked` state based on the new state
    checked.value = !isCurrentlyChecked;
}

// Watch for changes in `selectedGroups`
watch(selectedGroups, (newValue) => {
    // If another option is selected, remove 'Public'
    if (newValue.length > 1 && isPublicChecked()) {
        selectedGroups.value = newValue.filter(item => item.value !== PUBLIC_OPTION.value);
    }

    // Update `checked` state based on 'Public' selection
    checked.value = isPublicChecked();
}, { deep: true });

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults(currentPage.value, rowsPerPage.value);
    }
});

const likeCounts = ref({});
const likeDeltas = ref({});

const handleLikesCount = (masterId) => {
    if (!likeCounts.value[masterId]) {
        likeCounts.value[masterId] = 0;
        likeDeltas.value[masterId] = 0;
    }
    likeCounts.value[masterId] += 1;
    likeDeltas.value[masterId] += 1;

    saveLikesDebounced(masterId);
};

// Debounced function to save likes
const saveLikesDebounced = debounce((masterId) => {
    if (likeDeltas.value[masterId] > 0) {
        const deltaToSend = likeDeltas.value[masterId];

        likeDeltas.value[masterId] = 0;

        axios.post(route('pamm_allocate.updateLikeCounts'), {
            master_id: masterId,
            likeCounts: deltaToSend,
        }).catch((error) => {
            likeDeltas.value[masterId] += deltaToSend;
            console.error('Failed to update likes:', error);
        });
    }
}, 300);
</script>

<template>
    <!-- toolbar -->
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
        <div class="w-full flex justify-between items-center self-stretch gap-3">
            <Button
                variant="gray-outlined"
                @click="toggle"
                size="sm"
                class="flex gap-3 items-center justify-center py-3 w-full md:w-[130px]"
            >
                <IconAdjustments size="20" color="#0C111D" stroke-width="1.25" />
                <div class="text-sm text-gray-950 font-medium">
                    {{ $t('public.filter') }}
                </div>
                <Badge class="w-5 h-5 text-xs text-white" variant="numberbadge">
                    {{ filterCount }}
                </Badge>
            </Button>
            <Dropdown
                v-model="sortType"
                :options="sortOptions"
                optionLabel="name"
                class="w-full md:w-40"
                scroll-height="236px"
            >
                <template #value="slotProps">
                    <div v-if="slotProps.value" class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <div>{{ $t('public.' + slotProps.value.name) }}</div>
                        </div>
                    </div>
                </template>
                <template #option="slotProps">
                    {{ $t('public.' + slotProps.option.name) }}
                </template>
            </Dropdown>
        </div>
    </div>

    <!-- Combine loading and content check -->
    <div v-if="loading" class="w-full">
        <div class="flex flex-col gap-2 items-center justify-center">
            <Loader />
            <span class="text-sm text-gray-700">{{ $t('public.loading_asset_master_caption') }}</span>
        </div>
    </div>
    <div v-else class="w-full">
        <div v-if="masters.length > 0">
            <div class="grid grid-cols-1 md:grid-cols-2 3xl:grid-cols-4 gap-5 self-stretch">
                <div
                    v-for="(master, index) in masters"
                    :key="index"
                    class="w-full relative p-6 flex flex-col items-center gap-4 rounded-2xl bg-white shadow-toast "
                >
                    <div
                        v-if="master.asset_distribution_counts <= 3"
                        class="absolute -top-3 -left-3 text-error-500 animate-ping"
                    >
                        <IconAlertCircleFilled size="32" stroke-width="1.25" />
                    </div>
                    <!-- Profile Section -->
                    <div class="w-full flex items-center gap-4 self-stretch">
                        <div class="w-[42px] h-[42px] shrink-0 grow-0 rounded-full overflow-hidden">
                            <div v-if="master.master_profile_photo">
                                <img :src="master.master_profile_photo" alt="Profile Photo" />
                            </div>
                            <div v-else>
                                <DefaultProfilePhoto />
                            </div>
                        </div>
                        <div class="flex flex-col items-start max-w-[100px] xxs:max-w-[124px] xs:max-w-full 3xl:max-w-[134px]">
                            <div class="self-stretch truncate text-gray-950 font-bold">
                                {{ master.asset_name }}
                            </div>
                            <div class="self-stretch truncate text-gray-500 text-sm">
                                {{ master.trader_name }}
                            </div>
                        </div>
                        <div class="flex gap-3 items-center w-full justify-end">
                            <Action
                                :master="master"
                                :groupsOptions="groupsOptions"
                            />
                        </div>
                    </div>

                    <!-- StatusBadge Section -->
                    <div class="flex items-center gap-2 self-stretch">
                        <StatusBadge value="info">
                            $ {{ formatAmount(master.minimum_investment) }}
                        </StatusBadge>
                        <StatusBadge value="gray">
                            <div v-if="master.minimum_investment_period !== 0">
                                {{ master.minimum_investment_period }} {{ $t('public.months') }}
                            </div>
                            <div v-else>
                                {{ $t('public.lock_free') }}
                            </div>
                        </StatusBadge>
                        <StatusBadge value="gray">
                            {{ master.performance_fee > 0 ? formatAmount(master.performance_fee, 0) + '%&nbsp;' + $t('public.fee') : $t('public.zero_fee') }}
                        </StatusBadge>
                    </div>

                    <!-- Performance Section -->
                    <div class="py-2 flex justify-center items-center gap-2 self-stretch border-y border-solid border-gray-200">
                        <div class="w-full flex flex-col items-center">
                            <div class="self-stretch text-gray-950 text-center font-semibold">
                                {{ formatAmount(master.total_gain) }}%
                            </div>
                            <div class="self-stretch text-gray-500 text-center text-xs">
                                {{ $t('public.total_gain') }}
                            </div>
                        </div>
                        <div class="w-full flex flex-col items-center">
                            <div class="self-stretch text-gray-950 text-center font-semibold">
                                {{ formatAmount(master.monthly_gain) }}%
                            </div>
                            <div class="self-stretch text-gray-500 text-center text-xs">
                                {{ $t('public.monthly_gain') }}
                            </div>
                        </div>
                        <div class="w-full flex flex-col items-center">
                            <div class="self-stretch text-center font-semibold">
                                <div
                                    v-if="master.latest_profit !== 0"
                                    :class="(master.latest_profit < 0) ? 'text-error-500' : 'text-success-500'"
                                >
                                    {{ formatAmount(master.latest_profit) }}%
                                </div>
                                <div
                                    v-else
                                    class="text-gray-950"
                                >
                                    -
                                </div>
                            </div>
                            <div class="self-stretch text-gray-500 text-center text-xs">
                                {{ $t('public.latest') }}
                            </div>
                        </div>
                    </div>

                    <!-- Details Section -->
                    <div class="flex items-end justify-between self-stretch">
                        <div class="flex flex-col items-center gap-1 self-stretch">
                            <div class="py-1 flex items-center gap-3 self-stretch">
                                <IconUserDollar size="20" stroke-width="1.25" />
                                <div class="w-full text-gray-950 text-sm font-medium">
                                    {{ master.total_real_investors }} {{ $t('public.real_investors') }}
                                </div>
                            </div>
                            <div class="py-1 flex items-center gap-3 self-stretch">
                                <IconPremiumRights size="20" stroke-width="1.25" />
                                <div class="w-full text-gray-950 text-sm font-medium">
                                    {{ $t('public.real_fund_of') }}
                                    <span class="text-primary-500">$ {{ formatAmount(master.total_real_fund) }}</span>
                                </div>
                            </div>
                            <div class="py-1 flex items-center gap-3 self-stretch">
                                <IconScanEye size="20" stroke-width="1.25" />
                                <div class="w-full text-gray-950 text-sm font-medium max-w-[128px] xxs:max-w-[180px] xs:max-w-[220px] sm:max-w-full md:max-w-[180px] xl:max-w-md 3xl:max-w-[180px] truncate">
                                    {{ master.visible_to === 'public' ? $t(`public.${master.visible_to}`) : master.group_names }}
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div
                                class="select-none cursor-pointer p-1 transition ease-in-out duration-300 hover:scale-125"
                                @click="handleLikesCount(master.id)"
                            >
                                <IconHeartFilled v-if="likeCounts[master.id] > 0 || master.total_likes_count > 0" size="20" color="#FF2D58" />
                                <IconHeart v-else size="20" color="#667085" stroke-width="1.25" />
                            </div>
                            <span class="max-w-8 text-gray-950 font-medium">{{ likeCounts[master.id] > 0 ? master.total_likes_count + likeCounts[master.id] : master.total_likes_count }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <Paginator
                :first="(currentPage - 1) * rowsPerPage"
                :rows="rowsPerPage"
                :totalRecords="totalRecords"
                @page="onPageChange"
            />
        </div>
        <div v-else>
            <Empty :title="$t('public.empty_asset_master_title')" :message="$t('public.empty_asset_master_message')">
                <template #image>
                    <NoAssetMasterIcon class="w-60 h-[180px]" />
                </template>
            </Empty>
        </div>
    </div>

    <OverlayPanel ref="op">
        <div class="w-60 flex flex-col items-center">
            <div class="flex flex-col gap-8 w-60 py-5 px-4">
                <!-- Filter Visible To -->
                <div class="flex flex-col items-center gap-2 self-stretch">
                    <span class="self-stretch text-gray-950 text-xs font-bold">{{ $t('public.filter_visible_to') }}</span>
                    <MultiSelect
                        v-model="selectedGroups"
                        :showToggleAll="false"
                        :options="groupsOptions"
                        class="w-full h-full"
                    >
                        <template #value="slotProps">
                            <!-- Check if slotProps.value is an array and display names, otherwise show placeholder -->
                            <span v-if="slotProps.value.length > 0">
                                        {{ slotProps.value.map(item => item.name).join(', ') }}
                                    </span>
                            <span v-else>
                                        {{ $t('public.select_group_placeholder') }}
                                    </span>
                        </template>
                        <template #option="slotProps">
                                    <span v-for="slotProp in slotProps">
                                    {{ slotProp.name }}
                                    </span>
                        </template>
                        <template #header>
                            <div class="flex items-center border-b rounded-tl-lg rounded-tr-lg text-gray-950 bg-white border-gray-200">
                                <div
                                    class="w-full flex items-center p-3 gap-2 rounded-tl-md rounded-tr-md cursor-pointer"
                                    :class="{
                                                'hover:bg-gray-100': !checked,
                                                'hover:bg-gray-50': checked
                                            }"
                                    @click="togglePublicSelection"
                                >
                                    <Checkbox v-model="checked" :binary="true" />
                                    <span class="text-gray-950">{{ $t('public.public') }}</span>
                                </div>
                            </div>
                        </template>
                    </MultiSelect>
                </div>
                <!-- Filter Admin User’s Access -->
                <div class="flex flex-col items-center gap-2 self-stretch">
                    <span class="self-stretch text-gray-950 text-xs font-bold">{{ $t('public.filter_admin_access') }}</span>
                    <Dropdown
                        v-model="adminUser"
                        filter
                        :filterFields="['name']"
                        optionLabel="name"
                        :placeholder="$t('public.select_admin_user_placeholder')"
                        class="w-full"
                        scroll-height="236px"
                    >
                        <template #value="slotProps">
                            <div v-if="slotProps.value" class="flex items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <div>{{ slotProps.value.name }}</div>
                                </div>
                            </div>
                            <span v-else class="text-gray-400">{{ slotProps.placeholder }}</span>
                        </template>
                        <template #option="slotProps">
                            <div class="flex items-center gap-2">
                                <div>{{ slotProps.option.name }}</div>
                            </div>
                        </template>
                    </Dropdown>
                </div>
                <!-- Filter Tags -->
                <div class="flex flex-col items-center gap-2 self-stretch">
                    <span class="self-stretch text-gray-950 text-xs font-bold">{{ $t('public.filter_tags') }}</span>
                    <div class="flex flex-col gap-1 self-stretch">
                        <div class="flex items-center gap-2 text-sm text-gray-950">
                            <RadioButton v-model="tag" inputId="no_min_investment" value="no_min_investment" class="w-4 h-4" />
                            <label for="no_min_investment">{{ `${$t('public.no_min_investment')} $` }}</label>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-950">
                            <RadioButton v-model="tag" inputId="lock_free_radio" value="lock_free" class="w-4 h-4" />
                            <label for="lock_free_radio">{{ $t('public.lock_free_radio') }}</label>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-950">
                            <RadioButton v-model="tag" inputId="zero_fee" value="zero_fee" class="w-4 h-4" />
                            <label for="zero_fee">{{ $t('public.zero_fee') }}</label>
                        </div>
                    </div>

                </div>
                <!-- Filter Status -->
                <div class="flex flex-col items-center gap-2 self-stretch">
                    <span class="self-stretch text-gray-950 text-xs font-bold">{{ $t('public.filter_status') }}</span>
                    <div class="flex flex-col gap-1 self-stretch">
                        <div class="flex items-center gap-2 text-sm text-gray-950">
                            <RadioButton v-model="status" inputId="status_active" value="active" class="w-4 h-4" />
                            <label for="status_active">{{ $t('public.active') }}</label>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-950">
                            <RadioButton v-model="status" inputId="status_inactive" value="inactive" class="w-4 h-4" />
                            <label for="status_inactive">{{ $t('public.inactive') }}</label>
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
