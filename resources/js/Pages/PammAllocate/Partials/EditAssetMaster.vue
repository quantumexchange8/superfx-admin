<script setup>
import { ref, h, watch, computed, onMounted } from "vue";
import Button from '@/Components/Button.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputText from 'primevue/inputtext';
import { useForm, usePage } from '@inertiajs/vue3';
import Dropdown from "primevue/dropdown";
import { IconPlus, IconX, IconRefresh } from '@tabler/icons-vue';
import Calendar from 'primevue/calendar';
import MultiSelect from 'primevue/multiselect';
import IconField from 'primevue/iconfield';
import FileUpload from 'primevue/fileupload';
import { transactionFormat } from '@/Composables/index.js';
import InputNumber from 'primevue/inputnumber';
import Checkbox from "primevue/checkbox";
import dayjs from "dayjs";

const { formatDate, formatDateTime, formatAmount } = transactionFormat();

// Define props
const props = defineProps({
    master: Object,
    groupsOptions: Array,
});

// Define emits
const emit = defineEmits(['update:visible']);

// Define constants and reactive data
const PUBLIC_OPTION = { value: 'public', name: 'Public', color: 'ffffff' };

// Initialize groupsOptions and selectedGroups
const groupsOptions = ref(props.groupsOptions);
const selectedGroups = ref([PUBLIC_OPTION]);
const checked = ref(false);

// Function to initialize selectedGroups based on visible_to
const initializeSelectedGroups = () => {
    const visibleTo = props.master.visible_to || [];
    if (visibleTo.includes('public')) {
        selectedGroups.value = [PUBLIC_OPTION];
    } else {
        selectedGroups.value = props.groupsOptions.filter(group => visibleTo.includes(group.value));
    }
    // Update `checked` state based on `selectedGroups`
    checked.value = selectedGroups.value.some(item => item.value === PUBLIC_OPTION.value);
};

// Initialize selectedGroups
initializeSelectedGroups();

// Watch for changes in props.groupsOptions and props.master.visible_to
watch([() => props.groupsOptions, () => props.master.group_names], () => {
    groupsOptions.value = props.groupsOptions;
    initializeSelectedGroups();
}, { immediate: true });

// Define form using useForm
const form = useForm({
    id: props.master.id,
    pamm_name: props.master.asset_name,
    trader_name: props.master.trader_name,
    started_at: props.master.started_at,
    groups: [],
    total_investors: props.master.total_investors,
    total_fund: Number(props.master.total_fund),
    asset_master: '',
    min_investment: Number(props.master.minimum_investment),
    min_investment_period: props.master.minimum_investment_period,
    performance_fee: props.master.performance_fee,
    penalty_fee: props.master.penalty_fee,
    master_profile_photo: null,
});

// Clear date function
const clearDate = () => {
    form.started_at = '';
};

// Handle checkbox change and div click
function togglePublicSelection() {
    const isCurrentlyChecked = selectedGroups.value.some(item => item.value === PUBLIC_OPTION.value);

    if (isCurrentlyChecked) {
        // Remove 'Public' from selection
        selectedGroups.value = selectedGroups.value.filter(item => item.value !== PUBLIC_OPTION.value);
    } else {
        // Add 'Public' to selection and remove all other selections
        selectedGroups.value = [PUBLIC_OPTION];
    }

    // Update `checked` state based on the new state
    checked.value = !isCurrentlyChecked;
}

// Watch for changes in selectedGroups
watch(selectedGroups, (newValue) => {
    // If another option is selected, remove 'Public'
    if (newValue.length > 1 && selectedGroups.value.some(item => item.value === PUBLIC_OPTION.value)) {
        selectedGroups.value = newValue.filter(item => item.value !== PUBLIC_OPTION.value);
    }

    // Update `checked` state based on 'Public' selection
    checked.value = selectedGroups.value.some(item => item.value === PUBLIC_OPTION.value);

    // Update form's groups field
    form.groups = selectedGroups.value.map(group => group.value);
}, { deep: true });

// Investment period options
const investmentPeriodOptions = ref([
    { name: 'Lock-free', value: 0 },
    { name: '3 months',  value: 3 },
    { name: '6 months',  value: 6 },
    { name: '9 months',  value: 9 },
    { name: '12 months', value: 12 },
    { name: '18 months', value: 18 },
    { name: '24 months', value: 24 },
    { name: '36 months', value: 36 },
]);

const editAssetMaster  = () => {
    form.groups = selectedGroups.value.map(group => group.value);

    form.post(route('pamm_allocate.edit_asset_master'), {
        onSuccess: () => {
            form.reset();
            emit('update:visible')
        }
    });
}

const selectedMasterProfilePhoto = ref(null);
const selectedMasterProfilePhotoName = ref(null);
const handleKycVerification = (event) => {
    const masterProfilePhotoInput = event.target;
    const file = masterProfilePhotoInput.files[0];

    if (file) {
        // Display the selected image
        const reader = new FileReader();
        reader.onload = () => {
            selectedMasterProfilePhoto.value = reader.result;
        };
        reader.readAsDataURL(file);
        selectedMasterProfilePhotoName.value = file.name;
        form.master_profile_photo = event.target.files[0];
    } else {
        selectedMasterProfilePhoto.value = null;
    }
};

const removeMasterProfilePhoto = () => {
    selectedMasterProfilePhoto.value = null;
    form.master_profile_photo = '';
};
</script>

<template>
    <form>
        <div class="flex flex-col items-center gap-8 self-stretch">
            <div class="flex flex-col items-center gap-3 self-stretch">
                <span class="self-stretch text-gray-950 text-sm font-bold">{{ $t('public.basic_information') }}</span>
                <div class="grid grid-cols-1 items-center gap-3 self-stretch md:grid-cols-2">
                    <div class="flex flex-col items-start gap-1 self-stretch">
                        <InputLabel for="pamm_name" :value="$t('public.pamm_name')" />
                        <InputText
                            id="pamm_name"
                            type="text"
                            class="block w-full"
                            v-model="form.pamm_name"
                            :placeholder="$t('public.pamm_name_placeholder')"
                            :invalid="!!form.errors.pamm_name"
                            autofocus
                        />
                        <InputError :message="form.errors.pamm_name" />
                    </div>
                    <div class="flex flex-col items-start gap-1 self-stretch">
                        <InputLabel for="trader_name" :value="$t('public.trader_name')" />
                        <InputText
                            id="trader_name"
                            type="text"
                            class="block w-full"
                            v-model="form.trader_name"
                            :placeholder="$t('public.trader_name_placeholder')"
                            :invalid="!!form.errors.trader_name"
                            autofocus
                        />
                        <InputError :message="form.errors.trader_name" />
                    </div>
                    <div class="flex flex-col items-start gap-1 self-stretch">
                        <InputLabel for="started_at" :value="$t('public.created_date')" />
                        <div class="relative w-full">
                            <Calendar
                                v-model="form.started_at"
                                selectionMode="single"
                                :manualInput="false"
                                dateFormat="yy/mm/dd"
                                showIcon
                                iconDisplay="input"
                                placeholder="yyyy/mm/dd"
                                class="w-full"
                                :invalid="!!form.errors.started_at"
                            />
                            <div
                                v-if="form.started_at"
                                class="absolute top-2/4 -mt-2.5 right-4 text-gray-400 select-none cursor-pointer bg-white"
                                @click="clearDate"
                            >
                                <IconX size="20" stroke-width="1.25" />
                            </div>
                        </div>
                        <InputError :message="form.errors.started_at" />
                    </div>
                    <div class="flex flex-col items-start gap-1 self-stretch">
                        <InputLabel for="visible_to" :value="$t('public.visible_to')" />
                        <MultiSelect
                            v-model="selectedGroups"
                            :showToggleAll="false"
                            :options="groupsOptions"
                            class="w-full h-full"
                            :invalid="!!form.errors.groups"
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
                        <InputError :message="form.errors.groups" />
                    </div>
                    <div class="flex flex-col items-start gap-1 self-stretch">
                        <InputLabel for="total_investors">{{ $t('public.total_investors') }}</InputLabel>
                        <InputText
                            id="total_investors"
                            type="text"
                            class="block w-full"
                            v-model="form.total_investors"
                            placeholder="0"
                            :invalid="!!form.errors.total_investors"
                            autofocus
                        />
                        <InputError :message="form.errors.total_investors" />
                    </div>
                    <div class="flex flex-col items-start gap-1 self-stretch">
                        <InputLabel for="total_fund">{{ $t('public.total_fund') }}</InputLabel>
                        <InputNumber
                            v-model="form.total_fund"
                            inputId="currency-us"
                            prefix="$ "
                            class="w-full"
                            inputClass="py-3 px-4"
                            :min="0"
                            :step="100"
                            :minFractionDigits="2"
                            fluid
                            autofocus
                            :invalid="!!form.errors.total_fund"
                        />
                        <InputError :message="form.errors.total_fund" />
                    </div>
                </div>
            </div>
            <div class="flex flex-col items-center gap-1 self-stretch">
                <span class="self-stretch text-gray-950 text-sm font-bold">{{ $t('public.upload_image') }}</span>
                <div class="flex flex-col items-start gap-3 self-stretch">
                    <span class="text-xs text-gray-500">{{ $t('public.upload_image_caption') }}</span>
                    <div class="flex flex-col gap-3">
                        <input
                            ref="masterProfilePhotoInput"
                            id="master_profile_photo"
                            type="file"
                            class="hidden"
                            accept="image/*"
                            @change="handleKycVerification"
                        />
                        <Button
                            type="button"
                            variant="primary-tonal"
                            @click="$refs.masterProfilePhotoInput.click()"
                        >
                            {{ $t('public.browse') }}
                        </Button>
                        <InputError :message="form.errors.master_profile_photo" />
                    </div>
                    <div
                        v-if="selectedMasterProfilePhoto"
                        class="relative w-full py-3 pl-4 flex justify-between rounded-xl bg-gray-50"
                    >
                        <div class="inline-flex items-center gap-3">
                            <img :src="selectedMasterProfilePhoto" alt="Selected Image" class="max-w-full h-9 object-contain rounded" />
                            <div class="text-sm text-gray-950">
                                {{ selectedMasterProfilePhotoName }}
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="gray-text"
                            @click="removeMasterProfilePhoto"
                            pill
                            iconOnly
                        >
                            <IconX class="text-gray-700 w-5 h-5" />
                        </Button>
                    </div>
                </div>
            </div>
            <div class="flex flex-col items-center gap-1 self-stretch">
                <span class="self-stretch text-gray-950 text-sm font-bold">{{ $t('public.joining_conditions') }}</span>
                <div class="w-full grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="flex flex-col items-start gap-1 self-stretch md:flex-grow">
                        <InputLabel for="min_investment">{{ $t('public.min_investment') }}</InputLabel>
                        <InputNumber
                            v-model="form.min_investment"
                            inputId="currency-us"
                            prefix="$ "
                            class="w-full"
                            inputClass="py-3 px-4"
                            :min="0"
                            :step="100"
                            :minFractionDigits="2"
                            fluid
                            autofocus
                            :invalid="!!form.errors.min_investment"
                        />
                        <InputError :message="form.errors.min_investment" />
                    </div>
                    <div class="flex flex-col items-start gap-1 self-stretch md:flex-grow">
                        <InputLabel for="min_investment_period" :value="$t('public.min_investment_period')" />
                        <Dropdown
                            v-model="form.min_investment_period"
                            :options="investmentPeriodOptions"
                            optionLabel="name"
                            optionValue="value"
                            :placeholder="$t('public.min_investment_period_placeholder')"
                            class="w-full"
                            scroll-height="236px"
                            :invalid="!!form.errors.min_investment_period"
                        />
                        <InputError :message="form.errors.min_investment_period" />
                    </div>
                    <div class="flex flex-col items-start gap-1 self-stretch md:flex-grow">
                        <InputLabel for="profit_sharing">{{ $t('public.profit_sharing_label') }}</InputLabel>
                        <InputText
                            id="profit_sharing"
                            type="number"
                            class="block w-full"
                            v-model="form.performance_fee"
                            :invalid="!!form.errors.performance_fee"
                            placeholder="0.00%"
                        />
                        <InputError :message="form.errors.performance_fee" />
                    </div>
                    <div class="flex flex-col items-start gap-1 self-stretch md:flex-grow">
                        <InputLabel for="penalty_fee">{{ $t('public.penalty_fee') }}</InputLabel>
                        <InputText
                            id="penalty_fee"
                            type="number"
                            class="block w-full"
                            v-model="form.penalty_fee"
                            :invalid="!!form.errors.penalty_fee"
                            placeholder="0.00%"
                        />
                        <InputError :message="form.errors.penalty_fee" />
                    </div>
                </div>
            </div>
        </div>
        <div class="flex justify-end items-end pt-5 self-stretch md:pt-7">
            <Button variant="primary-flat" size="base" @click.prevent="editAssetMaster">{{ $t('public.save') }}</Button>
        </div>
    </form>
</template>
