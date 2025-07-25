<script setup>
import {computed, onMounted, ref, watch, watchEffect} from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AddMember from "@/Pages/Member/Listing/Partials/AddMember.vue";
import { MemberIcon, IBIcon, UserIcon, } from '@/Components/Icons/solid';
import InputText from 'primevue/inputtext';
import RadioButton from 'primevue/radiobutton';
import Button from '@/Components/Button.vue';
import {usePage} from '@inertiajs/vue3';
import OverlayPanel from 'primevue/overlaypanel';
import Dialog from 'primevue/dialog';
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import {FilterMatchMode} from "primevue/api";
import Loader from "@/Components/Loader.vue";
import Dropdown from "primevue/dropdown";
import {
    IconSearch,
    IconCircleXFilled,
    IconAdjustments,
    IconAlertCircleFilled,
    IconCircleCheckFilled,
    IconDotsVertical,
    IconReportSearch,
    IconPencilMinus,
    IconTrash
} from '@tabler/icons-vue';
import Badge from '@/Components/Badge.vue';
import Vue3Autocounter from 'vue3-autocounter';
import MemberTableActions from "@/Pages/Member/Listing/Partials/MemberTableActions.vue";
import { trans, wTrans } from "laravel-vue-i18n";
import {generalFormat} from "@/Composables/index.js";
import StatusBadge from "@/Components/StatusBadge.vue";
import Empty from "@/Components/Empty.vue";
import debounce from "lodash/debounce.js";

const exportStatus = ref(false);
const total_members = ref(999);
const total_ibs = ref(999);
const total_users = ref(999);
const totalRecords = ref(0);
const rows = ref(10);
const page = ref(0);
const sortField = ref(null);  
const sortOrder = ref(null);  // (1 for ascending, -1 for descending)
const loading = ref(false);
const dt = ref();
const users = ref();
const counterDuration = ref(10);
const { formatRgbaColor } = generalFormat();

// data overview
const dataOverviews = computed(() => [
    {
        icon: MemberIcon,
        total: total_members.value,
        label: trans('public.total_members'),
    },
    {
        icon: IBIcon,
        total: total_ibs.value,
        label: trans('public.total_ibs'),
    },
    {
        icon: UserIcon,
        total: total_users.value,
        label: trans('public.total_users'),
    },
]);

const filters = ref({
    global: '',
    upline_id: null,
    group_id: null,
    role: [],
    status: [],
    kyc_status: [],
});

const upline_id = ref(null)
const group_id = ref(null)

// Watch for changes on the entire 'filters' object and debounce the API call
watch(filters, debounce(() => {
    // Count active filters, excluding null, undefined, empty strings, and empty arrays
    filterCount.value = Object.values(filters.value).filter(filter => {
        if (Array.isArray(filter)) {
            return filter.length > 0;  // Check if the array is not empty
        }
        return filter !== null && filter !== '';  // Check if the value is not null or an empty string
    }).length;

    page.value = 0; // Reset to first page when filters change
    getResults(); // Call getResults function to fetch the data
}, 1000), { deep: true });

// Watch for individual changes in upline_id and group_id and apply them to filters
watch([upline_id, group_id], ([newUplineId, newGroupId]) => {
    if (newUplineId !== null) {
        filters.value['upline_id'] = newUplineId.value;
    }

    if (newGroupId !== null) {
        filters.value['group_id'] = newGroupId.value;
    }

    getResults(); // Trigger getResults after the changes
});

// Function to construct the URL with necessary query parameters
const constructUrl = (exportStatus = false) => {
    let url = `/member/getMemberListingPaginate?rows=${rows.value}&page=${page.value}`;

    // Add filters if present
    if (filters.value.global) {
        url += `&search=${filters.value.global}`;
    }

    if (filters.value.role) {
        url += `&role=${filters.value.role}`;
    }

    if (filters.value.upline_id) {
        url += `&upline_id=${filters.value.upline_id}`;
    }

    if (filters.value.group_id) {
        url += `&group_id=${filters.value.group_id}`;
    }

    if (filters.value.status) {
        url += `&status=${filters.value.status}`;
    }

    if (filters.value.kyc_status) {
        url += `&kyc_status=${filters.value.kyc_status}`;
    }

    if (sortField.value && sortOrder.value !== null) {
        url += `&sortField=${sortField.value}&sortOrder=${sortOrder.value}`;
    }

    // Add exportStatus if export is required
    if (exportStatus) {
        url += `&exportStatus=true`;
    }

    return url;
};

// Optimized getResults function
const getResults = async () => {
    loading.value = true;
    try {
        // Construct the URL dynamically
        const url = constructUrl();

        // Make the API request
        const response = await axios.get(url);
        
        // Update the data and total records with the response
        users.value = response?.data?.data?.data;
        totalRecords.value = response?.data?.data?.total;
        total_members.value = response.data?.total_members;
        total_ibs.value = response.data?.total_ibs;
        total_users.value = response?.data?.data?.total;

        counterDuration.value = 1;
    } catch (error) {
        console.error('Error fetching leads data:', error);
        users.value = [];
    } finally {
        loading.value = false;
    }
};

// Optimized exportMember function
const exportMember = async () => {
    exportStatus.value = true;
    try {
        // Construct the URL dynamically with exportStatus for export
        const url = constructUrl(exportStatus.value);

        // Send the request to trigger the export
        window.location.href = url;  // This will trigger the download directly
    } catch (e) {
        console.error('Error occurred during export:', e);
    } finally {
        loading.value = false;  // Reset loading state
        exportStatus.value = false;  // Reset export status
    }
};

const onPage = async (event) => {
    rows.value = event.rows;
    page.value = event.page;

    getResults();
};

const onSort = (event) => {
    sortField.value = event.sortField;
    sortOrder.value = event.sortOrder;  // Store ascending or descending order

    getResults();
};

onMounted(() => {
    getResults();
});


const getFilterData = async () => {
    try {
        const uplineResponse = await axios.get('/member/getFilterData');
        uplines.value = uplineResponse.data.uplines;
        groups.value = uplineResponse.data.groups;
    } catch (error) {
        console.error('Error changing locale:', error);
    } finally {
        loading.value = false;
    }
};

getFilterData();

// overlay panel
const op = ref();
const uplines = ref()
const groups = ref()
const filterCount = ref(0);

const toggle = (event) => {
    op.value.toggle(event);
}


const clearFilter = () => {
    filters.value = {
        global: '',
        upline_id: null,
        group_id: null,
        role: [],
        status: [],
        kyc_status: [],
    };

    upline_id.value = null;
    group_id.value = null;

};

const clearFilterGlobal = () => {
    filters.value.global = null;
}

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults();
    }
});

const paginator_caption = wTrans('public.paginator_caption');
</script>

<template>
    <AuthenticatedLayout :title="$t('public.member_listing')">
        <div class="flex flex-col gap-5 items-center">
            <div class="flex justify-end w-full">
                <AddMember />
            </div>

            <!-- data overview -->
            <div class="grid grid-cols-1 md:grid-cols-3 w-full gap-3 md:gap-5">
                <div
                    v-for="(item, index) in dataOverviews"
                    :key="index"
                    class="flex justify-center items-center px-6 py-4 md:p-6 gap-5 self-stretch rounded-2xl bg-white shadow-toast"
                >
                    <component :is="item.icon" class="w-12 h-12 grow-0 shrink-0" />
                    <div class="flex flex-col items-start gap-1 w-full">
                        <div class="text-gray-950 text-lg md:text-2xl font-semibold">
                            <vue3-autocounter ref="counter" :startAmount="0" :endAmount="item.total" :duration="counterDuration" separator="," decimalSeparator="." :autoinit="true" />
                        </div>
                        <span class="text-gray-500 text-xs md:text-sm">{{ item.label }}</span>
                    </div>
                </div>
            </div>

            <!-- data table -->
            <div class="py-6 px-4 md:p-6 flex flex-col items-center justify-center self-stretch gap-6 border border-gray-200 bg-white shadow-table rounded-2xl">
                <DataTable
                    :value="users"
                    :paginator="users?.length > 0 && total_users > 0"
                    lazy
                    removableSort
                    :rows="rows"
                    :rowsPerPageOptions="[10, 20, 50, 100]"
                    tableStyle="md:min-width: 50rem"
                    paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
                    :currentPageReportTemplate="paginator_caption"
                    ref="dt"
                    dataKey="id"
                    :totalRecords="totalRecords"
                    :loading="loading"
                    @page="onPage($event)"
                    @sort="onSort($event)"
                >
                    <template #header>
                        <div class="flex flex-col md:flex-row gap-3 items-center self-stretch md:pb-6">
                            <div class="relative w-full md:w-60">
                                <div class="absolute top-2/4 -mt-[9px] left-4 text-gray-400">
                                    <IconSearch size="20" stroke-width="1.25" />
                                </div>
                                <InputText v-model="filters['global']" :placeholder="$t('public.keyword_search')" class="font-normal pl-12 w-full md:w-60" />
                                <div
                                    v-if="filters['global'] !== null && filters['global'] !== ''"
                                    class="absolute top-2/4 -mt-2 right-4 text-gray-300 hover:text-gray-400 select-none cursor-pointer"
                                    @click="clearFilterGlobal"
                                >
                                    <IconCircleXFilled size="16" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 w-full gap-3">
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
                                <div class="w-full flex justify-end">
                                    <Button
                                        variant="primary-outlined"
                                        @click="exportMember"
                                        class="w-full md:w-auto"
                                    >
                                        {{ $t('public.export') }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </template>
                    <template #empty><Empty :title="$t('public.empty_member_title')" :message="$t('public.empty_member_message')" /></template>
                    <template #loading>
                        <div class="flex flex-col gap-2 items-center justify-center">
                            <Loader />
                            <span class="text-sm text-gray-700">{{ $t('public.loading_users_caption') }}</span>
                        </div>
                    </template>
                    <template v-if="users?.length > 0 && total_users > 0">
                        <Column
                            field="id_number"
                            sortable
                            style="width: 15%"
                            class="hidden md:table-cell"
                        >
                            <template #header>
                                <span class="hidden md:block">id</span>
                            </template>
                            <template #body="slotProps">
                                <div class="text-gray-950 text-sm flex flex-wrap gap-1 items-center truncate">
                                    {{ slotProps.data.id_number }}
                                    <IconAlertCircleFilled  
                                        :size="20" 
                                        stroke-width="1.25" 
                                        class="text-error-500 grow-0 shrink-0" 
                                        v-if="slotProps.data.kyc_status == 'pending'"
                                    />
                                    <!-- <IconAlertCircleFilled  
                                        :size="20" 
                                        stroke-width="1.25" 
                                        class="text-error-500 grow-0 shrink-0" 
                                        v-if="slotProps.data.kyc_status == 'pending'"
                                        v-tooltip.top="$t('public.trading_account_inactive_warning')"
                                    /> -->
                                </div>
                            </template>
                        </Column>
                        <Column
                            field="name"
                            sortable
                            :header="$t('public.name')"
                            style="width: 35%"
                            class="hidden md:table-cell"
                        >
                            <template #body="slotProps">
                                <div class="flex items-center gap-3">
                                    <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                                        <template v-if="slotProps.data.profile_photo">
                                            <img :src="slotProps.data.profile_photo" alt="profile_photo">
                                        </template>
                                        <template v-else>
                                            <DefaultProfilePhoto />
                                        </template>
                                    </div>
                                    <div class="flex flex-col items-start">
                                        <div class="font-medium">
                                            {{ slotProps.data.name }}
                                        </div>
                                        <div class="text-gray-500 text-xs flex items-center gap-1">
                                            <span>{{ slotProps.data.email }}</span>
                                            <IconCircleCheckFilled size="16" class="text-success-500" v-if="slotProps.data.email_verified_at" />
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Column>
                        <Column
                            field="role"
                            style="width: 15%"
                            class="hidden md:table-cell"
                        >
                            <template #header>
                                <span class="hidden md:block items-center justify-center w-full text-center">{{ $t('public.role') }}</span>
                            </template>
                            <template #body="slotProps">
                                <div class="flex items-center justify-center">
                                    <StatusBadge :variant="slotProps.data.role" :value="$t('public.' + slotProps.data.role)"/>
                                </div>
                            </template>
                        </Column>
                        <Column
                            field="group"
                            style="width: 20%"
                            class="hidden md:table-cell"
                        >
                            <template #header>
                                <span class="hidden md:block items-center justify-center w-full text-center">{{ $t('public.group') }}</span>
                            </template>
                            <template #body="slotProps">
                                <div class="flex items-center justify-center">
                                    <div
                                        v-if="slotProps.data.group_id"
                                        class="flex items-center gap-2 rounded justify-center py-1 px-2"
                                        :style="{ backgroundColor: formatRgbaColor(slotProps.data.group_color, 0.1) }"
                                    >
                                        <div
                                            class="w-1.5 h-1.5 grow-0 shrink-0 rounded-full"
                                            :style="{ backgroundColor: `#${slotProps.data.group_color}` }"
                                        ></div>
                                        <div
                                            class="text-xs font-semibold"
                                            :style="{ color: `#${slotProps.data.group_color}` }"
                                        >
                                            {{ slotProps.data.group_name }}
                                        </div>
                                    </div>
                                    <div v-else>
                                        -
                                    </div>
                                </div>
                            </template>
                        </Column>
                        <Column
                            field="action"
                            header=""
                            style="width: 15%"
                            class="hidden md:table-cell"
                        >
                            <template #body="slotProps">
                                <MemberTableActions
                                    :member="slotProps.data"
                                />
                            </template>
                        </Column>
                        <Column class="md:hidden">
                            <template #body="slotProps">
                                <div class="flex flex-col items-start gap-1 self-stretch">
                                    <div class="flex items-center gap-2 self-stretch w-full">
                                        <div class="flex items-center gap-3 w-full">
                                            <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                                                <template v-if="slotProps.data.profile_photo">
                                                    <img :src="slotProps.data.profile_photo" alt="profile_photo">
                                                </template>
                                                <template v-else>
                                                    <DefaultProfilePhoto />
                                                </template>
                                            </div>
                                            <div class="flex flex-col items-start">
                                                <div class="font-medium flex items-center justify-between max-w-[120px] xxs:max-w-[140px] min-[390px]:max-w-[180px] xs:max-w-[220px] truncate">
                                                    <span class="truncate">{{ slotProps.data.name }}</span>
                                                    <IconAlertCircleFilled  
                                                        :size="20" 
                                                        stroke-width="1.25" 
                                                        class="text-error-500 flex-shrink-0 ml-2" 
                                                        v-if="slotProps.data.kyc_status == 'pending'"
                                                    />
                                                    <!-- <IconAlertCircleFilled  
                                                        :size="20" 
                                                        stroke-width="1.25" 
                                                        class="text-error-500 flex-shrink-0 ml-2" 
                                                        v-if="slotProps.data.kyc_status == 'pending'"
                                                        v-tooltip.top="$t('public.trading_account_inactive_warning')"
                                                    /> -->
                                                </div>
                                                <div class="text-gray-500 text-xs max-w-[120px] xxs:max-w-[140px] min-[390px]:max-w-[180px] xs:max-w-[220px] truncate">
                                                    {{ slotProps.data.email }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-end">
                                            <MemberTableActions
                                                :member="slotProps.data"
                                            />
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1 h-[26px]">
                                        <StatusBadge :variant="slotProps.data.role" :value="$t('public.' + slotProps.data.role)"/>
                                        <div class="flex items-center justify-center">
                                            <div
                                                v-if="slotProps.data.group_id"
                                                class="flex items-center gap-2 rounded justify-center py-1 px-2"
                                                :style="{ backgroundColor: formatRgbaColor(slotProps.data.group_color, 0.1) }"
                                            >
                                                <div
                                                    class="w-1.5 h-1.5 grow-0 shrink-0 rounded-full"
                                                    :style="{ backgroundColor: `#${slotProps.data.group_color}` }"
                                                ></div>
                                                <div
                                                    class="text-xs font-semibold"
                                                    :style="{ color: `#${slotProps.data.group_color}` }"
                                                >
                                                    {{ slotProps.data.group_name }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Column>
                    </template>
                </DataTable>
            </div>
        </div>
    </AuthenticatedLayout>

    <OverlayPanel ref="op">
        <div class="flex flex-col gap-8 w-60 py-5 px-4">
            <!-- Filter Role-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_role') }}
                </div>
                <div class="flex flex-col gap-1 self-stretch">
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton v-model="filters['role']" inputId="role_member" value="member" class="w-4 h-4" />
                        <label for="role_member">{{ $t('public.member') }}</label>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton v-model="filters['role']" inputId="role_ib" value="ib" class="w-4 h-4" />
                        <label for="role_ib">{{ $t('public.ib') }}</label>
                    </div>
                </div>
            </div>

            <!-- Filter Group-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_group') }}
                </div>
                <Dropdown
                    v-model="group_id"
                    :options="groups"
                    filter
                    :filterFields="['name']"
                    optionLabel="name"
                    :placeholder="$t('public.select_group_placeholder')"
                    class="w-full"
                    scroll-height="236px"
                >
                    <template #value="slotProps">
                        <div v-if="slotProps.value" class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 rounded-full overflow-hidden grow-0 shrink-0" :style="{ backgroundColor: `#${slotProps.value.color}` }"></div>
                                <div>{{ slotProps.value.name }}</div>
                            </div>
                        </div>
                        <span v-else class="text-gray-400">{{ slotProps.placeholder }}</span>
                    </template>
                    <template #option="slotProps">
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 rounded-full overflow-hidden grow-0 shrink-0" :style="{ backgroundColor: `#${slotProps.option.color}` }"></div>
                            <div>{{ slotProps.option.name }}</div>
                        </div>
                    </template>
                </Dropdown>
            </div>

            <!-- Filter Upline-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_upline') }}
                </div>
                <Dropdown
                    v-model="upline_id"
                    :options="uplines"
                    filter
                    :filterFields="['name']"
                    optionLabel="name"
                    :placeholder="$t('public.select_upline')"
                    class="w-full"
                    scroll-height="236px"
                >
                    <template #value="slotProps">
                        <div v-if="slotProps.value" class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <div class="w-5 h-5 rounded-full overflow-hidden">
                                    <template v-if="slotProps.value.profile_photo">
                                        <img :src="slotProps.value.profile_photo" alt="profile_picture" />
                                    </template>
                                    <template v-else>
                                        <DefaultProfilePhoto />
                                    </template>
                                </div>
                                <div>{{ slotProps.value.name }}</div>
                            </div>
                        </div>
                        <span v-else class="text-gray-400">{{ slotProps.placeholder }}</span>
                    </template>
                    <template #option="slotProps">
                        <div class="flex items-center gap-2">
                            <div class="w-5 h-5 rounded-full overflow-hidden">
                                <template v-if="slotProps.option.profile_photo">
                                    <img :src="slotProps.option.profile_photo" alt="profile_picture" />
                                </template>
                                <template v-else>
                                    <DefaultProfilePhoto />
                                </template>
                            </div>
                            <div>{{ slotProps.option.name }}</div>
                        </div>
                    </template>
                </Dropdown>
            </div>

            <!-- Filter Status-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_status') }}
                </div>
                <div class="flex flex-col gap-1 self-stretch">
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton v-model="filters['status']" inputId="status_active" value="active" class="w-4 h-4" />
                        <label for="status_active">{{ $t('public.active') }}</label>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton v-model="filters['status']" inputId="status_inactive" value="inactive" class="w-4 h-4" />
                        <label for="status_inactive">{{ $t('public.inactive') }}</label>
                    </div>
                </div>
            </div>

            <!-- Filter kyc-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_kyc_status') }}
                </div>
                <div class="flex flex-col gap-1 self-stretch">
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton v-model="filters['kyc_status']" inputId="kyc_status_approved" value="approved" class="w-4 h-4" />
                        <label for="kyc_status_approved">{{ $t('public.approved') }}</label>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton v-model="filters['kyc_status']" inputId="kyc_status_rejected" value="rejected" class="w-4 h-4" />
                        <label for="kyc_status_rejected">{{ $t('public.rejected') }}</label>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton v-model="filters['kyc_status']" inputId="kyc_status_pending" value="pending" class="w-4 h-4" />
                        <label for="kyc_status_pending">{{ $t('public.pending') }}</label>
                    </div>
                </div>
            </div>

            <div class="flex w-full">
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
