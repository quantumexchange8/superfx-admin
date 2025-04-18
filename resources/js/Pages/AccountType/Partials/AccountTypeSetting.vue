<script setup>
import Button from '@/Components/Button.vue';
import { IconAdjustmentsHorizontal } from '@tabler/icons-vue';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import { onMounted, ref, watch } from 'vue';
import InputText from 'primevue/inputtext';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { useForm } from '@inertiajs/vue3';
import ColorPicker from 'primevue/colorpicker';
import InputNumber from 'primevue/inputnumber';
import MultiSelect from 'primevue/multiselect';

const props = defineProps({
    accountType: Object,
    leverages: Array,
    users: Array,
    loading: Boolean,
})

const isLoading = ref(false);
const visible = ref(false);
const categories = ref(['cent', 'dollar']);
const trade_delay_duration_dropdown = ref([
    {name: '0 sec', value: '0'},
    {name: '1 sec', value: '1'},
    {name: '2 sec', value: '2'},
    {name: '3 sec', value: '3'},
    {name: '4 sec', value: '4'},
    {name: '5 sec', value: '5'},
    {name: '6 sec', value: '6'},
    {name: '7 sec', value: '7'},
    {name: '8 sec', value: '8'},
    {name: '9 sec', value: '9'},
    {name: '10 sec', value: '10'},
    {name: '1 min', value: '60'},
    {name: '2 min', value: '120'},
    {name: '3 min', value: '180'},
    {name: '4 min', value: '240'},
    {name: '5 min', value: '300'},
])
const leverages = ref(props.leverages);
const selectedUser = ref();

const openDialog = () => {
    visible.value = true;
    form.reset();
    selectedUser.value = null;
    getAccountTypeUsers();
}

const closeDialog = () => {
    visible.value = false;
}

const form = useForm({
    id: props.accountType.id,
    account_type_name: props.accountType.name,
    member_display_name: props.accountType.member_display_name,
    category: props.accountType.category,
    min_deposit: Number(props.accountType.minimum_deposit ?? 0),
    descriptions: { en: props.accountType.description_en, tw: props.accountType.description_tw },
    leverage: props.accountType.leverage,
    trade_delay_duration: props.accountType.trade_open_duration,
    max_account: props.accountType.maximum_account_number,
    color: props.accountType.color,
    user_access: [],
})

const getAccountTypeUsers = async () => {
    isLoading.value = true;

    try {
        const response = await axios.get(`/account_type/getAccountTypeUsers?account_type_id=${props.accountType.id}`);
        selectedUser.value = response.data.users;
    } catch (error) {
        console.error('Error getting account type users:', error);
    } finally {
        isLoading.value = false;
    }
}

const submitForm = () => {
    if (selectedUser?.value?.length) {
        form.user_access = selectedUser.value.map(user => user.value);
    }
    
    form.post(route('accountType.update', props.accountType.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeDialog();
            emit('detailsVisible', false);
        },
        onError: (e) => {
            console.log('Error submit form:', e);
        }
    })
}


const emit = defineEmits(['detailsVisible']);
</script>

<template>
    <Button
        variant="gray-text"
        type="button"
        size="sm"
        iconOnly
        pill
        v-tooltip.bottom="'Setting'"
        @click="openDialog"
        :disabled="props.loading"
    >
        <IconAdjustmentsHorizontal size="16" stroke-width="1.25" color="#667085" />
    </Button>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.account_type_setting')"
        class="dialog-xs md:dialog-md"
    >
        <form @submit.prevent="submitForm()">
            <div class="flex flex-col items-center gap-8 self-stretch">
                <div class="flex flex-col items-center gap-3 self-stretch">
                    <div class="self-stretch text-gray-950 text-sm font-bold">
                        {{ $t('public.account_information') }}
                    </div>
                    <div class="w-full flex flex-col gap-1">
                        <div class="grid justify-center items-start content-start gap-3 self-stretch flex-wrap grid-cols-1 md:grid-cols-2 md:gap-5">
                            <div class="flex flex-col items-start gap-1 flex-1">
                                <InputLabel for="account_type_name" :invalid="!!form.errors.account_type_name">
                                    {{ $t('public.account_type_name') }}
                                </InputLabel>
                                <InputText
                                    v-model="form.account_type_name"
                                    id="account_type_name"
                                    type="text"
                                    class="w-full"
                                    disabled
                                />
                                <InputError :message="form.errors.account_type_name" />
                            </div>
                            <div class="flex flex-col items-start gap-1 flex-1">
                                <InputLabel for="member_display_name" :invalid="!!form.errors.member_display_name">
                                    {{ $t('public.member_display_name') }}
                                </InputLabel>
                                <InputText
                                    v-model="form.member_display_name"
                                    id="member_display_name"
                                    type="text"
                                    :placeholder="$t('public.enter_name')"
                                    class="w-full"
                                />
                                <InputError :message="form.errors.member_display_name" />
                            </div>
                            <div class="flex flex-col items-start gap-1 flex-1">
                                <InputLabel for="category" :value="$t('public.category')" :invalid="!!form.errors.category" />
                                <Dropdown
                                    v-model="form.category"
                                    id="category"
                                    :options="categories"
                                    class="w-full"
                                    :disabled="props.loading"
                                >
                                    <template #value="slotProps">
                                        <div v-if="slotProps.value" class="flex items-center gap-3">
                                            <div class="flex items-center gap-2">
                                                <div>{{ $t('public.' + slotProps.value) }}</div>
                                            </div>
                                        </div>
                                    </template>
                                    <template #option="slotProps">
                                        <div class="flex items-center gap-2">
                                            <div>{{ $t('public.' + slotProps.option) }}</div>
                                        </div>
                                    </template>
                                </Dropdown>
                                <InputError :message="form.errors.category" />
                            </div>

                            <div class="md:col-span-2 w-full flex flex-col gap-1">
                                <div class="md:col-span-2 grid justify-center items-start content-start gap-3 self-stretch flex-wrap grid-cols-1 md:grid-cols-2 md:gap-5">
                                    <div class="flex flex-col items-start gap-1 flex-1">
                                        <InputLabel for="descriptions_en" :invalid="!!form.errors['descriptions.en']">
                                            {{ $t('public.description_en') }}
                                        </InputLabel>
                                        <InputText
                                            v-model="form.descriptions.en"
                                            id="descriptions_en"
                                            type="text"
                                            class="w-full"
                                            :placeholder="$t('public.descriptions_en_placeholder')"
                                            :disabled="props.loading"
                                        />
                                        <InputError :message="form.errors['descriptions.en']" />
                                    </div>
                                    <div class="flex flex-col items-start gap-1 flex-1">
                                        <InputLabel for="descriptions_tw" :invalid="!!form.errors['descriptions.tw']">
                                            {{ $t('public.description_tw') }}
                                        </InputLabel>
                                        <InputText
                                            v-model="form.descriptions.tw"
                                            id="descriptions_tw"
                                            type="text"
                                            class="w-full"
                                            :placeholder="$t('public.descriptions_cn_placeholder')"
                                            :disabled="props.loading"
                                        />
                                        <InputError :message="form.errors['descriptions.tw']" />
                                    </div>
                                </div>
                                <div class="self-stretch text-gray-500 text-xs">
                                    {{ $t('public.description_caption') }}
                                </div>
                            </div>

                            <div class="md:col-span-2 flex flex-col items-start gap-1 flex-1">
                                <InputLabel for="min_deposit" :invalid="!!form.errors.min_deposit">
                                    {{ $t('public.min_deposit') }}
                                </InputLabel>
                                <InputNumber
                                    v-model="form.min_deposit"
                                    id="min_deposit"
                                    :min="0"
                                    :minFractionDigits="2"
                                    prefix="$ "
                                    class="w-full"
                                    inputClass="w-full py-3 px-4"
                                    placeholder="0"
                                />
                                <InputError :message="form.errors.min_deposit" />
                            </div>

                        </div>
                    </div>
                </div>
                <div class="flex flex-col items-center gap-3 self-stretch">
                    <div class="self-stretch text-gray-950 text-sm font-bold">
                        {{ $t('public.trading_conditions') }}
                    </div>
                    <div class="flex justify-center items-start content-start gap-5 self-stretch flex-wrap flex-col md:flex-row">
                        <div class="w-full flex flex-col items-start gap-1 flex-1">
                            <InputLabel for="leverage" :value="$t('public.leverage')" :invalid="!!form.errors.leverage" />
                            <Dropdown
                                v-model="form.leverage"
                                id="category"
                                :options="leverages"
                                optionLabel="name"
                                optionValue="value"
                                class="w-full"
                                :disabled="props.loading"
                            />
                            <InputError :message="form.errors.leverage" />
                        </div>
                        <div class="w-full flex flex-col items-start gap-1 flex-1">
                            <InputLabel for="trade_delay_duration" :value="$t('public.trade_delay_duration')" :invalid="!!form.errors.trade_delay_duration" />
                            <Dropdown
                                v-model="form.trade_delay_duration"
                                id="trade_delay_duration"
                                :options="trade_delay_duration_dropdown"
                                optionLabel="name"
                                optionValue="value"
                                class="w-full"
                                :disabled="props.loading"
                            />
                            <InputError :message="form.errors.trade_delay_duration" />
                        </div>
                    </div>
                </div>
                <div class="flex flex-col items-center gap-3 self-stretch">
                    <div class="self-stretch text-gray-950 text-sm font-bold">
                        {{ $t('public.other_settings') }}
                    </div>
                    <div class="grid justify-center items-start content-start gap-5 self-stretch flex-wrap grid-cols-1 md:grid-cols-2">
                        <div class="flex flex-col items-start gap-1 flex-1">
                            <InputLabel for="max_account" :value="$t('public.maximum_account_creation')" :invalid="!!form.errors.max_account" />
                            <InputText
                                v-model="form.max_account"
                                id="max_account"
                                type="number"
                                class="w-full"
                                placeholder="0"
                                :disabled="props.loading"
                            />
                            <InputError :message="form.errors.max_account" />
                        </div>
                        <div class="flex flex-col items-start gap-1 flex-1">
                            <InputLabel for="user_access" :value="$t('public.access_to')" :invalid="!!form.errors.user_access"/>
                            <MultiSelect
                                v-model="selectedUser"
                                :options="props.users"
                                :placeholder="$t('public.select_user')"
                                filter
                                :filterFields="['name', 'email', 'id_number']"
                                :maxSelectedLabels="1"
                                :selectedItemsLabel="`${selectedUser?.length} ${$t('public.users_selected')}`"
                                class="w-full md:w-64 font-normal"
                                :disabled="isLoading"
                            >
                                <template #option="{option}">
                                    <div class="flex flex-col">
                                        <span>{{ option.name }}</span>
                                        <span class="text-xs text-gray-400 max-w-52 truncate">{{ option.email }}</span>
                                    </div>
                                </template>
                                <template #value>
                                    <div v-if="selectedUser?.length === 1">
                                        <span>{{ selectedUser[0].name }}</span>
                                    </div>
                                    <span v-else-if="selectedUser?.length > 1">
                                        {{ selectedUser?.length }} {{ $t('public.users_selected') }}
                                    </span>
                                    <span v-else class="text-gray-400">
                                        {{ $t('public.select_user') }}
                                    </span>
                                </template>
                            </MultiSelect>
                            <InputError :message="form.errors.color" />
                        </div>
                        <div class="flex flex-col items-start gap-1 flex-1">
                            <InputLabel for="color" :value="$t('public.color')" :invalid="!!form.errors.color"/>
                            <ColorPicker v-model="form.color" id="Color"/>
                            <InputError :message="form.errors.color" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="pt-5 md:pt-7 flex flex-col items-end self-stretch">
                <Button
                    variant="primary-flat"
                    :disabled="form.processing || props.loading || isLoading"
                >
                    {{ $t('public.save') }}
                </Button>
            </div>
        </form>
    </Dialog>
</template>
