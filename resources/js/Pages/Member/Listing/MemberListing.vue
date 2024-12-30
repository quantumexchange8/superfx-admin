<script setup>
import {computed, onMounted, ref, watch, watchEffect} from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AddMember from "@/Pages/Member/Listing/Partials/AddMember.vue";
import { MemberIcon, AgentIcon, UserIcon, } from '@/Components/Icons/solid';
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

const total_members = ref(999);
const total_agents = ref(999);
const total_users = ref(999);
const loading = ref(false);
const dt = ref();
const users = ref();
const counterDuration = ref(10);
const { formatRgbaColor } = generalFormat();

onMounted(() => {
    getResults();
})

// data overview
const dataOverviews = computed(() => [
    {
        icon: MemberIcon,
        total: total_members.value,
        label: trans('public.total_members'),
    },
    {
        icon: AgentIcon,
        total: total_agents.value,
        label: trans('public.total_agents'),
    },
    {
        icon: UserIcon,
        total: total_users.value,
        label: trans('public.total_users'),
    },
]);

const getResults = async () => {
    loading.value = true;

    try {
        const response = await axios.get('/member/getMemberListingData');
        users.value = response.data.users;
        total_members.value = users.value.filter(user => user.role === 'member').length;
        total_agents.value = users.value.filter(user => user.role === 'agent').length;
        total_users.value = users.value.length;

        counterDuration.value = 1;
    } catch (error) {
        console.error('Error changing locale:', error);
    } finally {
        loading.value = false;
    }
};

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

const exportCSV = () => {
    dt.value.exportCSV();
};

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    name: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
    upline_id: { value: null, matchMode: FilterMatchMode.EQUALS },
    group_id: { value: null, matchMode: FilterMatchMode.EQUALS },
    role: { value: null, matchMode: FilterMatchMode.EQUALS },
    status: { value: null, matchMode: FilterMatchMode.EQUALS },
});

// overlay panel
const op = ref();
const uplines = ref()
const groups = ref()
const upline_id = ref(null)
const group_id = ref(null)
const filterCount = ref(0);

const toggle = (event) => {
    op.value.toggle(event);
}

watch([upline_id, group_id], ([newUplineId, newGroupId]) => {
    if (upline_id.value !== null) {
        filters.value['upline_id'].value = newUplineId.value
    }

    if (group_id.value !== null) {
        filters.value['group_id'].value = newGroupId.value
    }
})

const recalculateTotals = () => {
    const filteredUsers = users.value.filter(user => {
        return (
            (!filters.value.name.value || user.name.startsWith(filters.value.name.value)) &&
            (!filters.value.upline_id.value || user.upline_id === filters.value.upline_id.value) &&
            (!filters.value.group_id.value || user.group_id === filters.value.group_id.value) &&
            (!filters.value.role.value || user.role === filters.value.role.value) &&
            (!filters.value.status.value || user.status === filters.value.status.value)
        );
    });

    total_members.value = filteredUsers.filter(user => user.role === 'member').length;
    total_agents.value = filteredUsers.filter(user => user.role === 'agent').length;
    total_users.value = filteredUsers.length;
};

watch(filters, () => {
    recalculateTotals();

    // Count active filters
    filterCount.value = Object.values(filters.value).filter(filter => filter.value !== null).length;
}, { deep: true });

const clearFilter = () => {
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        name: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
        upline_id: { value: null, matchMode: FilterMatchMode.EQUALS },
        group_id: { value: null, matchMode: FilterMatchMode.EQUALS },
        role: { value: null, matchMode: FilterMatchMode.EQUALS },
        status: { value: null, matchMode: FilterMatchMode.EQUALS },
    };

    upline_id.value = null;
    group_id.value = null;
};

const clearFilterGlobal = () => {
    filters.value['global'].value = null;
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
                    v-model:filters="filters"
                    :value="users"
                    :paginator="users?.length > 0 && total_users > 0"
                    removableSort
                    :rows="10"
                    :rowsPerPageOptions="[10, 20, 50, 100]"
                    tableStyle="md:min-width: 50rem"
                    paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
                    :currentPageReportTemplate="paginator_caption"
                    :globalFilterFields="['name']"
                    ref="dt"
                    :loading="loading"
                >
                    <template #header>
                        <div class="flex flex-col md:flex-row gap-3 items-center self-stretch md:pb-6">
                            <div class="relative w-full md:w-60">
                                <div class="absolute top-2/4 -mt-[9px] left-4 text-gray-400">
                                    <IconSearch size="20" stroke-width="1.25" />
                                </div>
                                <InputText v-model="filters['global'].value" :placeholder="$t('public.keyword_search')" class="font-normal pl-12 w-full md:w-60" />
                                <div
                                    v-if="filters['global'].value !== null"
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
                                        @click="exportCSV($event)"
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
                                {{ slotProps.data.id_number }}
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
                                        <div class="text-gray-500 text-xs">
                                            {{ slotProps.data.email }}
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
                                    <StatusBadge :value="slotProps.data.role">{{ $t(`public.${slotProps.data.role}`) }}</StatusBadge>
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
                                                <div class="font-medium max-w-[120px] xxs:max-w-[140px] min-[390px]:max-w-[180px] xs:max-w-[220px] truncate">
                                                    {{ slotProps.data.name }}
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
                                        <StatusBadge :value="slotProps.data.role">{{ $t(`public.${slotProps.data.role}`) }}</StatusBadge>
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
                        <RadioButton v-model="filters['role'].value" inputId="role_member" value="member" class="w-4 h-4" />
                        <label for="role_member">{{ $t('public.member') }}</label>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton v-model="filters['role'].value" inputId="role_agent" value="agent" class="w-4 h-4" />
                        <label for="role_agent">{{ $t('public.agent') }}</label>
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
                        <RadioButton v-model="filters['status'].value" inputId="status_active" value="active" class="w-4 h-4" />
                        <label for="status_active">{{ $t('public.active') }}</label>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton v-model="filters['status'].value" inputId="status_inactive" value="inactive" class="w-4 h-4" />
                        <label for="status_inactive">{{ $t('public.inactive') }}</label>
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
