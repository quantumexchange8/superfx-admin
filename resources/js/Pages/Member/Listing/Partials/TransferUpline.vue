<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputNumber from "primevue/inputnumber";
import Passord from 'primevue/password';
import { useForm } from "@inertiajs/vue3";
import Button from "@/Components/Button.vue";
import Dropdown from "primevue/dropdown";
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import { IconUserCheck } from "@tabler/icons-vue";
import { trans, wTrans } from "laravel-vue-i18n";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import MultiSelect from 'primevue/multiselect';
import { transactionFormat } from "@/Composables/index.js";

const props = defineProps({
    member: Object,
})

const emit = defineEmits(['update:visible'])

const form = useForm({
    user_id: props.member.id,
    upline_id: props.member.upline_id,
    role: props.member.role,
    amounts: {},
    markup_profile_ids: '',
})

const closeDialog = () => {
    emit('update:visible', false);
}

const step = ref(1);
const uplines = ref(null);
const upline = ref(null);
const isLoading = ref(false);
const markupProfiles = ref([]);
const selectedMarkupProfiles = ref([]);
const accountTypes = ref([]);
const selectedAccountType = ref(null);
const rebateDetails = ref([]);
const accountRebateData = ref({}); // Stores rebate amounts for each account_type_id
const userRebateDetails = ref([]);
const { formatAmount } = transactionFormat();

const getAvailableUplines = async () => {
    isLoading.value = true;

    try {
        const url = props.member.role === 'ib' ? `/member/getAvailableUplines?id=${props.member.id}&role=ib` : `/member/getAvailableUplines?id=${props.member.id}`;

        const response = await axios.get(url);

        // Filter out the current member from the uplines list
        uplines.value = response.data.uplines;
        
    } catch (error) {
        console.error('Error get available uplines:', error);
    } finally {
        isLoading.value = false;
        // Try to match the initial upline_id (which is `form.upline_id`) to one of the available options
        upline.value = uplines.value.find(upline => upline.value === form.upline_id);
    }
};
getAvailableUplines();

const getUplineData = async () => {
    isLoading.value = true;

    try {
        // Here, we're directly using upline.value in the URL
        const response = await axios.get(`/member/getUplineData?user_id=${props.member?.id}&upline_id=${upline.value?.value}`);
        
        rebateDetails.value = response.data.rebateDetails;
        accountTypes.value = response.data.accountTypeSel;
        markupProfiles.value = response.data.markupProfiles;
        userRebateDetails.value = response.data.userRebateDetails;

        // Map selectedMarkupProfiles (which is an array of IDs) to full profile objects
        if (props.member?.markup_profile_ids && markupProfiles.value.length) {
            selectedMarkupProfiles.value = props.member.markup_profile_ids
                .map(id => markupProfiles.value.find(profile => profile.id === id))
                .filter(profile => profile); // Filter out undefined if no match is found
        }

        initializeMemberRebateAmount();

    } catch (error) {
        console.error('Error fetching available upline data:', error);
    } finally {
        isLoading.value = false;
    }
};

// Watch for changes to `upline` and call `getUplineData` only if the `role` is 'ib'
watch(upline, () => {
    if (props.member.role === 'ib') {
        step.value = 1;
        selectedMarkupProfiles.value = [],
        selectedAccountType.value = [],
        getUplineData();
    }
});

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

    // Filter rebate details for the current account type
    const currentRebateDetails = rebateDetails.value.filter(detail => detail.account_type_id === currentAccountTypeId);

    // Check if there's existing data for this account type
    if (!accountRebateData.value[currentAccountTypeId]) {
        accountRebateData.value[currentAccountTypeId] = currentRebateDetails.reduce((acc, detail) => {
            // Initialize rebate entry for each detail
            acc[detail.id] = {
                amount: acc[detail.id]?.amount || 0,  // Default to 0 if not already set
                symbol_group_id: detail.symbol_group_id
            };

            // Check if userRebateDetails is available and contains matching data
            if (userRebateDetails.value && Array.isArray(userRebateDetails.value)) {
                const matchingUserRebate = userRebateDetails.value.find(userRebate => 
                    userRebate.account_type_id === currentAccountTypeId &&
                    userRebate.symbol_group_id === detail.symbol_group_id
                );

                // If a match is found, update the amount from userRebateDetails
                if (matchingUserRebate) {
                    acc[detail.id].amount = parseFloat(matchingUserRebate.amount);  // Update with the amount from userRebateDetails
                }
            }

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

const nextStep = () => {
    step.value = 2;
};

const previousStep = () => {
    step.value = 1;
};

const submitForm = () => {
    form.upline_id = upline.value['value'];
    form.amounts = prepareSubmissionData();
    form.markup_profile_ids = selectedMarkupProfiles.value.map(profile => profile.id);

    // Check if the role is 'ib' and require confirmation first
    if (props.member.role === 'ib') {
        requireConfirmation('ib_transfer_upline'); // Show confirmation dialog first
    } else {
        // Directly submit the form if the role is not 'ib'
        form.post(route('member.transferUpline'), {
            onSuccess: () => {
                closeDialog();
                form.reset();
            },
        });
    }
}

const confirm = useConfirm();

const requireConfirmation = (action_type) => {
    const messages = {
        ib_transfer_upline: {
            group: 'headless-error',
            actionType: 'transfer_upline',
            header: trans('public.ib_transfer_upline_header'),
            text: trans('public.ib_transfer_upline_message'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.confirm'),
            action: () => {
                form.post(route('member.transferUpline'), {
                    onSuccess: () => {
                        closeDialog();
                        form.reset();
                    },
                });
            }
        },
    };

    const { group, actionType, header, text, cancelButton, acceptButton, action } = messages[action_type];

    confirm.require({
        group,
        actionType,
        header,
        text,
        cancelButton,
        acceptButton,
        accept: action
    });
};

</script>

<template>
        <div class="flex flex-col gap-6 items-center self-stretch py-4 md:py-6 md:gap-8">
            <div class="flex flex-col justify-center items-start p-3 self-stretch bg-gray-50">
                <span class="w-full truncate text-gray-950 font-semibold">{{ props.member.name }}</span>
                <span class="w-full truncate text-gray-500 text-sm">{{ props.member.email }}</span>
            </div>
            <div class="flex flex-col items-center gap-3 self-stretch">
                <span class="self-stretch text-gray-950 text-sm font-bold">{{ $t('public.select_new_upline') }}</span>
                <div class="flex flex-col items-start gap-2 self-stretch">
                    <InputLabel for="upline" :value="$t('public.upline')" />
                    <Dropdown
                        v-model="upline"
                        :options="uplines"
                        filter
                        :filterFields="['name','email','id_number']"
                        optionLabel="name"
                        :placeholder="$t('public.select_upline')"
                        class="w-full font-normal"
                        :disabled="isLoading"
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
                    <InputError :message="form.errors.upline_id" />
                </div>

                <form
                    v-if="props.member.role === 'ib'"
                    class="w-full space-y-3"
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

                            <div class="flex justify-end items-center pt-6 gap-4 self-stretch">
                                <Button
                                    variant="gray-outlined"
                                    class="w-full"
                                    :disabled="form.processing"
                                    @click.prevent="closeDialog"
                                >
                                    {{ $t('public.cancel') }}
                                </Button>
                                <Button
                                    variant="primary-flat"
                                    class="w-full"
                                    @click.prevent="nextStep"
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

                            <div class="flex justify-end items-center pt-6 gap-4 self-stretch">
                                <Button
                                    variant="gray-outlined"
                                    class="w-full"
                                    :disabled="form.processing"
                                    @click.prevent="previousStep"
                                >
                                    {{ $t('public.back') }}
                                </Button>
                                <Button
                                    variant="primary-flat"
                                    class="w-full"
                                    :disabled="form.processing || isLoading"
                                    @click.prevent="submitForm"
                                >
                                    {{ $t('public.confirm') }}
                                </Button>
                            </div>
                        </div>
                    </template>
                </form>

                <div v-else class="flex justify-end items-center pt-6 gap-4 self-stretch">
                    <Button
                        variant="gray-outlined"
                        class="w-full"
                        :disabled="form.processing"
                        @click.prevent="closeDialog"
                    >
                        {{ $t('public.cancel') }}
                    </Button>
                    <Button
                        variant="primary-flat"
                        class="w-full"
                        :disabled="form.processing || isLoading"
                        @click.prevent="submitForm"
                    >
                        {{ $t('public.confirm') }}
                    </Button>
                </div>

            </div>
        </div>
</template>
