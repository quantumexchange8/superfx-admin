<script setup>
import Button from "@/Components/Button.vue";
import Dialog from 'primevue/dialog';
import { ref } from "vue";
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import { useForm } from '@inertiajs/vue3';
import { useConfirm } from "primevue/useconfirm";
import { trans, wTrans } from "laravel-vue-i18n";
import Tag from "primevue/tag";

// Props
const props = defineProps({
    accountTypes: Array,
    users: Array,
});

const visible = ref(false);

// Selected MultiSelect values
const selectedAccountTypes = ref([]);
const selectedUsers = ref([]);
const confirm = useConfirm();

// Form data
const form = useForm({
    name: '',
    description: '',
    account_type_ids: [],
    user_ids: [],
});

// Open Dialog
const openDialog = () => {
    visible.value = true;
    form.reset();
    selectedAccountTypes.value = [];
    selectedUsers.value = [];
};

// Close Dialog
const closeDialog = () => {
    visible.value = false;
    form.reset();
    selectedAccountTypes.value = [];
    selectedUsers.value = [];
};

const requireConfirmation = (action_type) => {
    const messages = {
        create_markup_profile: {
            group: 'headless-primary',
            header: trans('public.create_profile_header'),
            message: trans('public.create_profile_message'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.create'),
            action: () => {
                form.post(route('markup_profile.addMarkupProfile'), {
                    onSuccess: () => {
                        closeDialog();
                    },
                    onError: (e) => {
                        console.log('Error submit form:', e);
                    }
                });
            }
        }
    };

    const { group, header, message, cancelButton, acceptButton, action } = messages[action_type];

    confirm.require({
        group,
        header,
        message,
        cancelButton,
        acceptButton,
        accept: action,
    });
};

// Submit Function
const submitForm = () => {
    form.account_type_ids = selectedAccountTypes.value.map(item => item.id);
    form.user_ids = selectedUsers.value?.length ? selectedUsers.value.map(user => user.value) : [];

    requireConfirmation('create_markup_profile');
};
</script>

<template>
    <Button
        type="button"
        variant="primary-flat"
        size="base"
        class="w-full md:w-auto"
        @click="openDialog"
    >
        {{ $t('public.add_profile') }}
    </Button>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.new_profile')"
        class="dialog-xs md:dialog-md"
        @hide="closeDialog"
    >
        <form @submit.prevent="submitForm">
            <div class="flex flex-col items-center gap-3 md:gap-5 self-stretch">

                <!-- Basic Information -->
                <div class="flex flex-col gap-3 items-center self-stretch">
                    <div class="text-gray-950 font-semibold text-sm self-stretch">
                        {{ $t('public.basic_information') }}
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-5 w-full">

                        <!-- Name -->
                        <div class="space-y-1 h-[66px]">
                            <InputLabel for="name" :value="$t('public.name')" />
                            <InputText
                                id="name"
                                type="text"
                                class="block w-full"
                                v-model="form.name"
                                :placeholder="$t('public.enter_name')"
                                :invalid="!!form.errors.name"
                                autofocus
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <!-- Description -->
                        <div class="space-y-1 h-[66px]">
                            <InputLabel for="description">{{ $t('public.description') }}</InputLabel>
                            <InputText
                                id="description"
                                type="text"
                                class="block w-full"
                                v-model="form.description"
                                :placeholder="$t('public.enter_description')"
                                :invalid="!!form.errors.description"
                            />
                            <InputError :message="form.errors.description" />
                        </div>
                    </div>
                </div>

                <div class="grid justify-center items-start content-start gap-3 md:gap-5 self-stretch flex-wrap grid-cols-1 md:grid-cols-2">
                    <!-- Account Types -->
                    <div class="flex flex-col items-start gap-1 flex-1">
                        <InputLabel for="account_types">{{ $t('public.account_type') }}</InputLabel>
                        <MultiSelect
                            v-model="selectedAccountTypes"
                            :options="props.accountTypes"
                            :placeholder="$t('public.select_account_type')"
                            filter
                            :filterFields="['name']"
                            :maxSelectedLabels="1"
                            :selectedItemsLabel="`${selectedAccountTypes?.length} ${$t('public.account_types_selected')}`"
                            class="w-full md:w-64 font-normal"
                        >
                            <template #option="{ option }">
                                <div class="flex gap-2 items-center">
                                    <span>{{ option.name }}</span>
                                    <Tag
                                        :severity="option.trading_platform.slug === 'mt4' ? 'secondary' : 'info'"
                                        class="uppercase"
                                        :value="option.trading_platform.slug"
                                    />
                                </div>
                            </template>
                            <template #value>
                                <div v-if="selectedAccountTypes?.length === 1">
                                    <span>{{ selectedAccountTypes[0].name }}</span>
                                </div>
                                <span v-else-if="selectedAccountTypes?.length > 1">
                                    {{ selectedAccountTypes?.length }} {{ $t('public.account_types_selected') }}
                                </span>
                                <span v-else class="text-gray-400">
                                    {{ $t('public.select_account_type') }}
                                </span>
                            </template>
                        </MultiSelect>
                        <InputError :message="form.errors.account_type_ids" />
                    </div>

                    <!-- Users -->
                    <div class="flex flex-col items-start gap-1 flex-1">
                        <InputLabel for="user_ids">{{ $t('public.access_to') }}</InputLabel>
                        <MultiSelect
                            v-model="selectedUsers"
                            :options="props.users"
                            :placeholder="$t('public.select_user')"
                            filter
                            :filterFields="['name', 'email', 'id_number']"
                            :maxSelectedLabels="1"
                            :selectedItemsLabel="`${selectedUsers?.length} ${$t('public.users_selected')}`"
                            class="w-full md:w-64 font-normal"
                            :virtualScrollerOptions="{ itemSize: 56 }"
                        >
                            <template #option="{ option }">
                                <div class="flex flex-col">
                                    <span>{{ option.name }}</span>
                                    <span class="text-xs text-gray-400 max-w-52 truncate">{{ option.email }}</span>
                                </div>
                            </template>
                            <template #value>
                                <div v-if="selectedUsers?.length === 1">
                                    <span>{{ selectedUsers[0].name }}</span>
                                </div>
                                <span v-else-if="selectedUsers?.length > 1">
                                    {{ selectedUsers?.length }} {{ $t('public.users_selected') }}
                                </span>
                                <span v-else class="text-gray-400">
                                    {{ $t('public.select_user') }}
                                </span>
                            </template>
                        </MultiSelect>
                        <InputError :message="form.errors.user_ids" />
                    </div>
                </div>
            </div>

            <div class="flex flex-col items-end pt-5 self-stretch">
                <Button
                    variant="primary-flat"
                    size="base"
                    @click="submitForm"
                    :disabled="form.processing"
                >
                    {{ $t('public.create') }}
                </Button>
            </div>
        </form>
    </Dialog>
</template>
