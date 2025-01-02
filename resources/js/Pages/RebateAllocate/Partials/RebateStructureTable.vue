<script setup>
import {ref, watch, watchEffect} from "vue";
import InputText from 'primevue/inputtext';
import Button from '@/Components/Button.vue';
import {useForm, usePage} from '@inertiajs/vue3';
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import {FilterMatchMode} from "primevue/api";
import Loader from "@/Components/Loader.vue";
import Dropdown from "primevue/dropdown";
import {
    IconSearch,
    IconCircleXFilled,
    IconAdjustmentsHorizontal,
} from '@tabler/icons-vue';
import { wTrans, trans } from "laravel-vue-i18n";
import AgentDropdown from '@/Pages/RebateAllocate/Partials/AgentDropdown.vue';
import InputNumber from "primevue/inputnumber";
import Dialog from "primevue/dialog";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import toast from '@/Composables/toast';
import debounce from "lodash/debounce.js";

const props = defineProps({
    accountTypes: Array,
})

// Emit event back to parent
const emit = defineEmits(['update:accountType']);

const accountTypes = ref();
const search = ref(null);

const clearSearch = () => {
    search.value = null;
}
// Watch for changes in the accountTypes prop
watch(() => props.accountTypes, (newAccountTypes) => {
    accountTypes.value = newAccountTypes;
}, { immediate: true }); // immediate: true will execute the watcher immediately on component mount

const accountType = ref(accountTypes.value[0].value);
const loading = ref(false);
const dt = ref();
const agents = ref();

const getResults = async (type_id) => {
    loading.value = true;

    try {
        let url = `/rebate_allocate/getAgents?type_id=${type_id}`;

        if (search.value) {
            url += `&search=${search.value}`;
        }

        // Make the API request
        const response = await axios.get(url);

        agents.value = response.data;
    } catch (error) {
        console.error('Error get agents:', error);
    } finally {
        loading.value = false;
    }
};

// Fetch results initially using the current accountType
getResults(accountType.value, search.value);

// Watch for changes in accountType and fetch new results accordingly
watch(accountType, (newValue) => {
    emit('update:accountType', newValue);  // Emit the new value to the parent
    getResults(newValue, search.value);
});

// Flag to temporarily disable the watcher
let isChangingAgent = false;

// Watch the search value with a debounced function directly in the watcher
watch(search, debounce((newSearchValue) => {
    // Prevent getResults from being called when changing agent
    if (!isChangingAgent) {
        getResults(accountType.value, newSearchValue); // Fetch with current account type and search query
    }
}, 1000)); // Debounce time (1000ms)

const changeAgent = async (newAgent) => {
    loading.value = true;

    try {
        // Temporarily disable the watcher to prevent getResults from running
        isChangingAgent = true;

        // Clear the search value to ensure it doesn't trigger a search
        clearSearch();

        const response = await axios.get(`/rebate_allocate/changeAgents?id=${newAgent.id}&type_id=${accountType.value}`);
        agents.value = response.data;
    } catch (error) {
        console.error('Error get change:', error);
    } finally {
        loading.value = false;

        // Re-enable the watcher after the agent change is complete
        isChangingAgent = false;
    }
}

const editingRows = ref([]);
const visible = ref(false);
const agentRebateDetail = ref();
const productDetails = ref();

const openDialog = (agentData) => {
    visible.value = true;
    agentRebateDetail.value = agentData[0][0];
    productDetails.value = agentData[1];
}

const form = useForm({
    rebates: null
});

const onRowEditSave = (event) => {
    let { newData, index } = event;

    agents.value[index] = newData;

    const data = agents.value[index][1];
    
    // Map the indexes (1, 2, 3, 4, 5) to the corresponding categories
    const categories = [
        { key: 1, name: 'forex' },
        { key: 2, name: 'stocks' },
        { key: 3, name: 'indices' },
        { key: 4, name: 'commodities' },
        { key: 5, name: 'cryptocurrency' }
    ];

    // Flag to track if the post should proceed
    let canPost = true;

    categories.forEach((category) => {
        // Get the value for the category
        const value = data[category.key];

        // Retrieve the upline and downline values dynamically
        const uplineMax = data[`upline_${category.name}`];
        const downlineMax = data[`downline_${category.name}`];

        // Prepare the messages by replacing the :name and :value placeholders
        const exceedUplineMessage = wTrans('public.rebate_exceed_upline', { name: trans('public.' + category.name), value: uplineMax });
        const exceedDownlineMessage = wTrans('public.rebate_exceed_downline', { name: trans('public.' + category.name), value: downlineMax });

        // Check if the value exceeds the upline max or falls below the downline min
        if (value > uplineMax) {
            // Show a warning message for exceeding the upline
            toast.add({ 
                type: 'warning', 
                title: exceedUplineMessage,
            });
            canPost = false; // Set flag to false, prevent form post
        } else if (value < downlineMax) {
            // Show a warning message for falling below the downline
            toast.add({ 
                type: 'warning', 
                title: exceedDownlineMessage,
            });
            canPost = false; // Set flag to false, prevent form post
        }
    });

    // Proceed with the form post only if all checks pass
    if (canPost) {
        form.rebates = agents.value[index][1];
        form.post(route('rebate_allocate.updateRebateAmount'));
    }
};

const submitForm = (submitData) => {
    // Assign the submitData to form.rebates
    form.rebates = submitData;

    // Map the indexes (1, 2, 3, 4, 5) to the corresponding categories
    const categories = [
        { key: 1, name: 'forex' },
        { key: 2, name: 'stocks' },
        { key: 3, name: 'indices' },
        { key: 4, name: 'commodities' },
        { key: 5, name: 'cryptocurrency' }
    ];

    // Flag to track if the post should proceed
    let canPost = true;

    // Loop over the categories to validate each one
    categories.forEach((category) => {
        // Get the value for the category from the submitData
        const value = submitData[category.key];

        // Retrieve the upline and downline values dynamically from the submitData
        const uplineMax = submitData[`upline_${category.name}`];
        const downlineMax = submitData[`downline_${category.name}`];

        // Prepare the messages by replacing the :name and :value placeholders
        const exceedUplineMessage = wTrans('public.rebate_exceed_upline', { name: trans('public.' + category.name), value: uplineMax });
        const exceedDownlineMessage = wTrans('public.rebate_exceed_downline', { name: trans('public.' + category.name), value: downlineMax });

        // Check if the value exceeds the upline max or falls below the downline min
        if (value > uplineMax) {
            // Show a warning message for exceeding the upline
            toast.add({ 
                type: 'warning', 
                title: exceedUplineMessage,
            });
            closeDialog();
            canPost = false; // Set flag to false, prevent form post
        } else if (value < downlineMax) {
            // Show a warning message for falling below the downline
            toast.add({ 
                type: 'warning', 
                title: exceedDownlineMessage,
            });
            closeDialog();
            canPost = false; // Set flag to false, prevent form post
        }
    });

    // Proceed with the form post only if all checks pass
    if (canPost) {
        form.post(route('rebate_allocate.updateRebateAmount'), {
            onSuccess: () => {
                closeDialog();
                form.reset();
            },
        });
    }
};

const closeDialog = () => {
    visible.value = false;
}

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults(accountType.value);
    }
});
</script>

<template>
    <div
        class="p-6 flex flex-col items-center justify-center self-stretch gap-6 border border-gray-200 bg-white shadow-table rounded-2xl">
        <DataTable
            v-model:editingRows="editingRows"
            :value="agents"
            tableStyle="min-width: 50rem"
            :globalFilterFields="['name']"
            ref="dt"
            :loading="loading"
            table-style="min-width:fit-content"
            editMode="row"
            :dataKey="agents && agents.length ? agents[0].id : 'id'"
            @row-edit-save="onRowEditSave"
        >
            <template #header>
                <div class="flex flex-col md:flex-row gap-3 items-center self-stretch md:justify-between">
                    <div class="relative w-full md:w-60">
                        <div class="absolute top-2/4 -mt-[9px] left-4 text-gray-400">
                            <IconSearch size="20" stroke-width="1.25"/>
                        </div>
                        <InputText v-model="search" :placeholder="$t('public.search_agent')" class="font-normal pl-10 pr-10 w-full md:w-60" />
                        <div
                            v-if="search !== null"
                            class="absolute top-2/4 -mt-2 right-4 text-gray-300 hover:text-gray-400 select-none cursor-pointer"
                            @click="clearSearch"
                        >
                            <IconCircleXFilled size="16"/>
                        </div>
                    </div>
                    <Dropdown
                        v-model="accountType"
                        :options="accountTypes"
                        optionLabel="name"
                        optionValue="value"
                        class="w-full md:w-52 font-normal"
                    />
                </div>
            </template>
            <template #empty> {{ $t('public.no_user_header') }}</template>
            <template #loading>
                <div class="flex flex-col gap-2 items-center justify-center">
                    <Loader/>
                    <span class="text-sm text-gray-700">{{ $t('public.loading_users_caption') }}</span>
                </div>
            </template>
            <Column field="level" style="width:10%;">
                <template #header>
                    <span>{{ $t('public.level') }}</span>
                </template>
                <template #body="slotProps">
                    <span class="px-3">{{ slotProps.data[0][0].level }}</span>
                </template>
            </Column>
            <Column field="agent" class="w-auto">
                <template #header>
                    <span>{{ $t('public.agent') }}</span>
                </template>
                <template #body="slotProps">
                    <AgentDropdown :agents="slotProps.data[0]" @update:modelValue="changeAgent($event)" class="w-full"/>
                </template>
                <template #editor="{ data }">
                    <div class="flex items-center gap-3">
                        <img :src="data[0][0].profile_photo" class="w-5 h-5 rounded-full grow-0 shrink-0" alt="">
                        <span>{{ data[0][0].name }}</span>
                    </div>
                </template>
            </Column>
            <Column field="1" class="hidden md:table-cell" style="width:10%;">
                <template #header>
                    <span>{{ $t('public.forex') }}</span>
                </template>
                <template #body="slotProps">
                    {{ slotProps.data[1]['1'] }}
                </template>
                <template #editor="{ data, field }">
                    <InputNumber
                        v-model="data[1][field]"
                        :minFractionDigits="2"
                        fluid
                        size="sm"
                        inputClass="py-2 px-4 w-20"
                    />
                </template>
            </Column>
            <Column field="2" class="hidden md:table-cell" style="width:10%;">
                <template #header>
                    <span>{{ $t('public.stocks') }}</span>
                </template>
                <template #body="slotProps">
                    {{ slotProps.data[1]['2'] }}
                </template>
                <template #editor="{ data, field }">
                    <InputNumber
                        v-model="data[1][field]"
                        :minFractionDigits="2"
                        fluid
                        size="sm"
                        inputClass="py-2 px-4 w-20"
                    />
                </template>
            </Column>
            <Column field="3" class="hidden md:table-cell" style="width:10%;">
                <template #header>
                    <span>{{ $t('public.indices') }}</span>
                </template>
                <template #body="slotProps">
                    {{ slotProps.data[1]['3'] }}
                </template>
                <template #editor="{ data, field }">
                    <InputNumber
                        v-model="data[1][field]"
                        :minFractionDigits="2"
                        fluid
                        size="sm"
                        inputClass="py-2 px-4 w-20"
                    />
                </template>
            </Column>
            <Column field="4" class="hidden md:table-cell" style="width:10%;">
                <template #header>
                    <span class="w-12 truncate lg:w-auto">{{ $t('public.commodities') }}</span>
                </template>
                <template #body="slotProps">
                    {{ slotProps.data[1]['4'] }}
                </template>
                <template #editor="{ data, field }">
                    <InputNumber
                        v-model="data[1][field]"
                        :minFractionDigits="2"
                        fluid
                        size="sm"
                        inputClass="py-2 px-4 w-20"
                    />
                </template>
            </Column>
            <Column field="5" class="hidden md:table-cell" style="width:10%;">
                <template #header>
                    <span class="w-12 truncate lg:w-auto">{{ $t('public.cryptocurrency') }}</span>
                </template>
                <template #body="slotProps">
                    {{ slotProps.data[1]['5'] }}
                </template>
                <template #editor="{ data, field }">
                    <InputNumber
                        v-model="data[1][field]"
                        :minFractionDigits="2"
                        fluid
                        size="sm"
                        inputClass="py-2 px-4 w-20"
                    />
                </template>
            </Column>
            <Column :rowEditor="true" class="hidden md:table-cell" style="width: 10%; min-width: 8rem"
                    bodyStyle="text-align:center">
                <template #roweditoriniticon>
                    <Button
                        variant="gray-text"
                        type="button"
                        size="sm"
                        iconOnly
                        pill
                    >
                        <IconAdjustmentsHorizontal size="16" stroke-width="1.25"/>
                    </Button>
                </template>
            </Column>
            <Column field="action" style="width: 15%" class="md:hidden table-cell">
                <template #body="slotProps">
                    <Button
                        variant="gray-text"
                        type="button"
                        size="sm"
                        iconOnly
                        pill
                        @click="openDialog(slotProps.data)"
                    >
                        <IconAdjustmentsHorizontal size="16" stroke-width="1.25"/>
                    </Button>
                </template>
            </Column>
        </DataTable>
    </div>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.agent_rebate_structure')"
        class="dialog-xs"
    >
        <div class="flex flex-col gap-8 items-center self-stretch">
            <!-- agent details -->
            <div class="flex items-center gap-3 w-full">
                <div class="w-9 h-9 rounded-full overflow-hidden grow-0 shrink-0">
                    <template v-if="agentRebateDetail.profile_photo">
                        <img :src="agentRebateDetail.profile_photo" alt="profile_photo">
                    </template>
                    <template v-else>
                        <DefaultProfilePhoto/>
                    </template>
                </div>
                <div class="flex flex-col items-start">
                    <div class="font-medium text-gray-950">
                        {{ agentRebateDetail.name }}
                    </div>
                    <div class="text-gray-500 text-xs">
                        {{ agentRebateDetail.email }}
                    </div>
                </div>
            </div>

            <!-- rebate allocation -->
            <div class="flex flex-col items-center gap-2 w-full text-sm">
                <div class="flex justify-between items-center py-2 self-stretch border-b border-gray-200 bg-gray-100">
                    <div
                        class="flex items-center w-full max-w-[104px] px-2 text-gray-950 text-xs font-semibold uppercase">
                        {{ $t('public.product') }}
                    </div>
                    <div
                        class="flex items-center px-2 w-full max-w-[64px] text-gray-950 text-xs font-semibold uppercase">
                        {{ $t('public.upline_rebate') }}
                    </div>
                    <div
                        class="flex items-center px-2 w-full max-w-[72px] text-gray-950 text-xs font-semibold uppercase">
                        {{ $t('public.rebate') }} / Ł ($)
                    </div>
                </div>

                <div class="flex flex-col items-center self-stretch max-h-[400px] overflow-y-auto">
                    <div class="flex justify-between py-1 items-center self-stretch h-10 text-gray-950">
                        <div class="px-2 w-full max-w-[104px]">
                            {{ $t('public.forex') }}
                        </div>
                        <div class="px-2 w-full max-w-[64px]">
                            {{ productDetails.upline_forex }}
                        </div>
                        <div class="px-2 w-full max-w-[72px]">
                            <InputNumber
                                v-model="productDetails['1']"
                                :minFractionDigits="2"
                                fluid
                                size="sm"
                                inputClass="p-2 max-w-[64px]"
                            />
                        </div>
                    </div>
                    <div class="flex justify-between py-1 items-center self-stretch h-10 text-gray-950">
                        <div class="px-2 w-full max-w-[104px]">
                            {{ $t('public.stocks') }}
                        </div>
                        <div class="px-2 w-full max-w-[64px]">
                            {{ productDetails.upline_stocks }}
                        </div>
                        <div class="px-2 w-full max-w-[72px]">
                            <InputNumber
                                v-model="productDetails['2']"
                                :minFractionDigits="2"
                                fluid
                                size="sm"
                                inputClass="p-2 max-w-[64px]"
                            />
                        </div>
                    </div>
                    <div class="flex justify-between py-1 items-center self-stretch h-10 text-gray-950">
                        <div class="px-2 w-full max-w-[104px]">
                            {{ $t('public.indices') }}
                        </div>
                        <div class="px-2 w-full max-w-[64px]">
                            {{ productDetails.upline_indices }}
                        </div>
                        <div class="px-2 w-full max-w-[72px]">
                            <InputNumber
                                v-model="productDetails['3']"
                                :minFractionDigits="2"
                                fluid
                                size="sm"
                                inputClass="p-2 max-w-[64px]"
                            />
                        </div>
                    </div>
                    <div class="flex justify-between py-1 items-center self-stretch h-10 text-gray-950">
                        <div class="px-2 w-full max-w-[104px] truncate">
                            {{ $t('public.commodities') }}
                        </div>
                        <div class="px-2 w-full max-w-[64px]">
                            {{ productDetails.upline_commodities }}
                        </div>
                        <div class="px-2 w-full max-w-[72px]">
                            <InputNumber
                                v-model="productDetails['4']"
                                :minFractionDigits="2"
                                fluid
                                size="sm"
                                inputClass="p-2 max-w-[64px]"
                            />
                        </div>
                    </div>
                    <div class="flex justify-between py-1 items-center self-stretch h-10 text-gray-950">
                        <div class="px-2 w-full max-w-[104px] truncate">
                            {{ $t('public.cryptocurrency') }}
                        </div>
                        <div class="px-2 w-full max-w-[64px]">
                            {{ productDetails.upline_cryptocurrency }}
                        </div>
                        <div class="px-2 w-full max-w-[72px]">
                            <InputNumber
                                v-model="productDetails['5']"
                                :minFractionDigits="2"
                                fluid
                                size="sm"
                                inputClass="p-2 max-w-[64px]"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex justify-end items-center pt-8 gap-4 self-stretch sm:p-7">
            <Button
                type="button"
                variant="gray-tonal"
                class="w-full md:w-[120px]"
                @click="closeDialog"
            >
                {{ $t('public.cancel') }}
            </Button>
            <Button
                variant="primary-flat"
                class="w-full md:w-[120px]"
                @click="submitForm(productDetails)"
            >
                {{ $t('public.save') }}
            </Button>
        </div>
    </Dialog>
</template>
