<script setup>
import {IconArrowRight} from "@tabler/icons-vue"
import {computed, ref, watch} from "vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import {transactionFormat} from "@/Composables/index.js";
import InputError from "@/Components/InputError.vue";
import InputNumber from "primevue/inputnumber";
import {useForm} from "@inertiajs/vue3";
import Button from "@/Components/Button.vue";
import Dropdown from "primevue/dropdown";
import MultiSelect from 'primevue/multiselect';

const props = defineProps({
    member: Object
})

const step = ref(1);
const isLoading = ref(false);
const availableUpline = ref(null);
const newIdNumber = ref('');
const markupProfiles = ref([]);
const selectedMarkupProfiles = ref([]);
const accountTypes = ref([]);
const selectedAccountType = ref(null);
const rebateDetails = ref([]);
const accountRebateData = ref({}); // Stores rebate amounts for each account_type_id
const { formatAmount } = transactionFormat();
const emit = defineEmits(['update:visible'])

// Fetch and initialize data
const getAvailableUplineData = async () => {
    isLoading.value = true;

    try {
        const response = await axios.get(`/member/getAvailableUplineData?user_id=${props.member.id}`);
        availableUpline.value = response.data.availableUpline;
        rebateDetails.value = response.data.rebateDetails;
        accountTypes.value = response.data.accountTypeSel;
        markupProfiles.value = response.data.markupProfiles;

        // Map selectedMarkupProfiles (which is an array of IDs) to full profile objects
        if (props.member?.markup_profile_ids && markupProfiles.value.length) {
            selectedMarkupProfiles.value = props.member.markup_profile_ids
                .map(id => markupProfiles.value.find(profile => profile.id === id))
                .filter(profile => profile); // Filter out undefined if no match is found
        }
        newIdNumber.value = props.member.id_number.replace(/^M/, 'I');

        initializeMemberRebateAmount();
    } catch (error) {
        console.error('Error fetching available upline data:', error);
    } finally {
        isLoading.value = false;
    }
};

// Computed property to filter account types based on selected markup profiles
const filteredAccountTypes = computed(() => {
  // Get all selected account IDs from selected markup profiles
  const selectedAccountIds = selectedMarkupProfiles.value.flatMap(profile =>
    profile.account_types.map(account => account.id)
  );

  // Filter accountTypes to include only those that match the selected account IDs
  return accountTypes.value.filter(accountType => selectedAccountIds.includes(accountType.id));
});

// Initialize or reset rebate amounts for the selected account type
const initializeMemberRebateAmount = () => {
    const currentAccountTypeId = selectedAccountType.value?.id;

    if (!currentAccountTypeId) return;

    const currentRebateDetails = rebateDetails.value.filter(detail => detail.account_type_id === currentAccountTypeId);

    if (!accountRebateData.value[currentAccountTypeId]) {
        accountRebateData.value[currentAccountTypeId] = currentRebateDetails.reduce((acc, detail) => {
            acc[detail.id] = {
                amount: acc[detail.id]?.amount || 0,
                symbol_group_id: detail.symbol_group_id
            };
            return acc;
        }, {});
    }
};

let isMarkupChange = false;

// Watch for changes in selectedMarkupProfiles
watch(selectedMarkupProfiles, () => {
    // Update the flag indicating markup change
    isMarkupChange = true;

    // Check if there are filtered account types, if so, select the first one as the new account type
    selectedAccountType.value = filteredAccountTypes.value[0];
    accountRebateData.value = {};
    initializeMemberRebateAmount();
}, { immediate: true });

// Watch for changes in selectedAccountType to update data
watch(selectedAccountType, (newType, oldType) => {
    if (isMarkupChange) {
        initializeMemberRebateAmount();
        isMarkupChange = false;
    } else {
        // If account type has changed, save previous data
        if (oldType) {
            // Ensure we are preserving the rebate data for the previous account type
            accountRebateData.value[oldType.id] = accountRebateData.value[oldType.id] || {};
        }

        // Initialize or reset rebate data for the new selected account type
        initializeMemberRebateAmount();
    }
});

const filteredRebateDetails = computed(() => {
    const currentAccountTypeId = selectedAccountType.value?.id;
    return rebateDetails.value.filter(detail => detail.account_type_id === currentAccountTypeId);
});

// Prepare data for submission
const prepareSubmissionData = () => {
    return Object.entries(accountRebateData.value).flatMap(([accountTypeId, rebates]) =>
        Object.entries(rebates).map(([rebateDetailId, data]) => ({
            account_type_id: accountTypeId,
            rebate_detail_id: rebateDetailId,
            amount: data.amount || 0,
            symbol_group_id: data.symbol_group_id
        }))
    );
};

const form = useForm({
    user_id: props.member.id,
    id_number: '',
    upline_id: null,
    amounts: {},
    markup_profile_ids: '',
});

const nextStep = () => {
    step.value = 2;
};

const previousStep = () => {
    step.value = 1;
};

const submitForm = () => {
    form.id_number = newIdNumber.value;
    form.upline_id = availableUpline.value.id
    form.amounts = prepareSubmissionData();
    // Extract the IDs of the selected markup profiles and assign them to the form
    form.markup_profile_ids = selectedMarkupProfiles.value.map(profile => profile.id);

    form.post(route('member.upgradeIB'), {
        onSuccess: () => {
            closeDialog();
        }
    });
};

getAvailableUplineData();

const closeDialog = () => {
    emit('update:visible', false);
}
</script>

<template>
    <div class="flex flex-col gap-5 items-start self-stretch">
        <div class="flex flex-col md:flex-row items-center gap-3 self-stretch">
            <!-- member -->
            <div class="flex flex-col gap-2 items-start self-stretch w-full">
                <div class="text-xs text-gray-500">
                    {{ $t('public.member') }}
                </div>
                <div class="flex gap-3 items-center self-stretch">
                    <div class="w-9 h-9 rounded-full overflow-hidden">
                        <template v-if="member.profile_photo">
                            <img :src="member.profile_photo" alt="profile_photo">
                        </template>
                        <template v-else>
                            <DefaultProfilePhoto />
                        </template>
                    </div>
                    <div class="flex flex-col items-start">
                        <span class="text-gray-950 text-sm font-medium max-w-[200px] truncate">{{ member.name }}</span>
                        <div class="text-gray-500 text-xs max-w-[200px] truncate flex gap-1 items-center self-stretch">
                            <span>{{ member.id_number }}</span>
                            <IconArrowRight size="12" stroke-width="1.25" />
                            <span>{{ newIdNumber }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ib -->
            <div class="flex flex-col gap-2 items-start self-stretch w-full">
                <div class="text-xs text-gray-500">
                    {{ $t('public.upline') }}
                </div>
                <div
                    v-if="availableUpline"
                    class="flex gap-3 items-center self-stretch"
                >
                    <div class="w-9 h-9 rounded-full overflow-hidden">
                        <template v-if="availableUpline.profile_photo">
                            <img :src="availableUpline.profile_photo" alt="profile_photo">
                        </template>
                        <template v-else>
                            <DefaultProfilePhoto />
                        </template>
                    </div>
                    <div class="flex flex-col items-start">
                        <span class="text-gray-950 text-sm font-medium max-w-[200px] truncate">{{ availableUpline.name }}</span>
                        <span class="text-gray-500 text-xs truncate">{{ availableUpline.id_number }}</span>
                    </div>
                </div>

                <!-- lazy loading -->
                <div v-else class="flex gap-3 items-center self-stretch">
                    <div class="w-9 h-9 rounded-full overflow-hidden">
                        <DefaultProfilePhoto />
                    </div>
                    <div class="flex flex-col items-start animate-pulse">
                        <div class="h-2.5 bg-gray-200 rounded-full w-48 my-2"></div>
                        <div class="h-2 bg-gray-200 rounded-full w-20 mb-1"></div>
                    </div>
                </div>
            </div>
        </div>

        <form
            class="w-full space-y-5"
        >
            <template v-if="step === 1">
                <!-- account type -->
                <div class="flex flex-col items-start gap-1">
                    <MultiSelect
                        v-model="selectedMarkupProfiles"
                        :options="markupProfiles"
                        :placeholder="$t('public.select_profile')"
                        filter
                        :filterFields="['name']"
                        :maxSelectedLabels="1"
                        :selectedItemsLabel="`${selectedMarkupProfiles?.length} ${$t('public.profiles_selected')}`"
                        class="w-full md:w-64 font-normal"
                    >
                        <template #option="{ option }">
                            <div class="flex flex-col">
                                <span>{{ option.name }}</span>
                            </div>
                        </template>
                        <template #value>
                            <div v-if="selectedMarkupProfiles?.length === 1">
                                <span>{{ selectedMarkupProfiles[0].name }}</span>
                            </div>
                            <span v-else-if="selectedMarkupProfiles?.length > 1">
                                {{ selectedMarkupProfiles?.length }} {{ $t('public.profiles_selected') }}
                            </span>
                            <span v-else class="text-gray-400">
                                {{ $t('public.select_profile') }}
                            </span>
                        </template>
                    </MultiSelect>
                </div>

                <!-- Selected Markup Profiles -->
                <div class="flex flex-col items-center self-stretch divide-y">
                    <div class="flex items-center w-full self-stretch py-2 text-gray-950 bg-gray-100">
                        <span class="uppercase text-xs font-semibold px-2 w-[120px] md:w-full">{{ $t('public.name') }}</span>
                        <span class="uppercase text-xs font-semibold px-2 w-20 md:w-full">{{ $t('public.account_type') }}</span>
                    </div>

                    <!-- details -->
                    <div
                        v-if="!isLoading"
                        v-for="(profile, index) in selectedMarkupProfiles"
                        class="flex items-center w-full self-stretch py-2 text-gray-950"
                    >
                        <div class="text-sm px-2 w-[120px] md:w-full truncate">{{ profile.name }}</div>
                        <div class="text-sm px-2 w-20 md:w-full">{{ profile.account_types.map(account => account.name).join(', ') }}</div>
                    </div>

                    <!-- lazy loading -->
                    <div
                        v-else
                        v-for="index in 2"
                        class="flex items-center w-full self-stretch py-4 text-gray-950"
                    >
                        <div class="w-full">
                            <div class="h-2.5 bg-gray-200 rounded-full w-20 md:w-36 mt-1 mb-1.5"></div>
                        </div>
                        <div class="w-full">
                            <div class="h-2.5 bg-gray-200 rounded-full w-10 md:w-36 mt-1 mb-1.5"></div>
                        </div>
                    </div>

                    <div class="flex gap-3 md:gap-4 md:justify-end pt-10 md:pt-7 self-stretch">
                        <Button
                            type="button"
                            variant="gray-tonal"
                            size="base"
                            class="w-full md:w-[120px]"
                            @click="closeDialog"
                        >
                            {{ $t('public.cancel') }}
                        </Button>
                        <Button
                            variant="primary-flat"
                            size="base"
                            class="w-full md:w-[120px]"
                            @click="nextStep"
                            :disabled="form.processing"
                        >
                            {{ $t('public.next') }}
                        </Button>
                    </div>
                </div>
            </template>

            <template v-if="step === 2">
                <!-- Markup Profile -->
                <div class="flex flex-col items-start gap-1">
                    <Dropdown
                        v-model="selectedAccountType"
                        id="accountTypes"
                        :options="filteredAccountTypes"
                        class="md:max-w-[240px] w-full"
                        optionLabel="name"
                        :disabled="!selectedAccountType || selectedMarkupProfiles?.length <= 0"
                    />
                    <InputError :message="form.errors.account_type_id" />
                </div>

                <!-- rebate allocate -->
                <div class="flex flex-col items-center self-stretch divide-y">
                    <div class="flex items-center w-full self-stretch py-2 text-gray-950 bg-gray-100">
                        <span class="uppercase text-xs font-semibold px-2 w-[120px] md:w-full">{{ $t('public.product') }}</span>
                        <span class="uppercase text-xs font-semibold px-2 w-20 md:w-full">{{ $t('public.upline_rebate') }}</span>
                        <span class="uppercase text-xs font-semibold px-2 w-[88px] md:w-full">{{ $t('public.rebate') }} / Ł ($)</span>
                    </div>

                    <!-- symbol groups -->
                    <div
                        v-if="rebateDetails.length > 0"
                        v-for="(rebateDetail, index) in filteredRebateDetails"
                        class="flex items-center w-full self-stretch py-2 text-gray-950"
                    >
                        <div class="text-sm px-2 w-[120px] md:w-full truncate">{{ $t(`public.${rebateDetail.symbol_group.display}`) }}</div>
                        <div class="text-sm px-2 w-20 md:w-full">{{ formatAmount(rebateDetail.amount, 2) }}</div>
                        <div class="text-sm px-2 w-[88px] md:w-full">
                            <InputNumber
                                v-model="accountRebateData[selectedAccountType.id][rebateDetail.id].amount"
                                :min="0"
                                :max="Number(rebateDetail.amount)"
                                :minFractionDigits="2"
                                fluid
                                :placeholder="formatAmount(rebateDetail.amount, 0)"
                                :invalid="!!form.errors[`amounts.${index}`]"
                                inputClass="py-2 px-4 w-20"
                                :disabled="!selectedAccountType || selectedMarkupProfiles?.length <= 0"
                            />
                            <InputError :message="form.errors[`amounts.${index}`]" />
                        </div>
                    </div>

                    <!-- lazy loading -->
                    <div
                        v-else
                        v-for="index in 6"
                        class="flex items-center w-full self-stretch py-4 text-gray-950"
                    >
                        <div class="w-full">
                            <div class="h-2.5 bg-gray-200 rounded-full w-20 md:w-36 mt-1 mb-1.5"></div>
                        </div>
                        <div class="w-full">
                            <div class="h-2.5 bg-gray-200 rounded-full w-10 md:w-36 mt-1 mb-1.5"></div>
                        </div>
                        <div class="w-full">
                            <div class="h-2.5 bg-gray-200 rounded-full w-10 mt-1 mb-1.5"></div>
                        </div>
                    </div>

                    <div class="flex gap-3 md:gap-4 md:justify-end pt-10 md:pt-7 self-stretch">
                        <Button
                            type="button"
                            variant="gray-tonal"
                            size="base"
                            class="w-full md:w-[120px]"
                            @click="previousStep"
                        >
                            {{ $t('public.back') }}
                        </Button>
                        <Button
                            variant="primary-flat"
                            size="base"
                            class="w-full md:w-[120px]"
                            @click="submitForm"
                            :disabled="form.processing"
                        >
                            {{ $t('public.upgrade') }}
                        </Button>
                    </div>
                </div>
            </template>
        </form>
    </div>
</template>
