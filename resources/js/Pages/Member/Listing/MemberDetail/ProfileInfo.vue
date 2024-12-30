<script setup>
import Button from "@/Components/Button.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import { Edit01Icon } from "@/Components/Icons/outline.jsx";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import InputSwitch from "primevue/inputswitch";
import { ref, watch } from "vue";
import Dialog from "primevue/dialog";
import InputLabel from "@/Components/InputLabel.vue";
import Dropdown from "primevue/dropdown";
import InputError from "@/Components/InputError.vue";
import InputText from "primevue/inputtext";
import { useForm } from "@inertiajs/vue3";
import { generalFormat } from "@/Composables/index.js";
import { useConfirm } from "primevue/useconfirm";
import { trans } from "laravel-vue-i18n";
import { router } from "@inertiajs/vue3";

const props = defineProps({
    userDetail: Object,
})

const checked = ref(false)
const visible = ref(false)
const countries = ref()
const selectedCountry = ref();
const { formatRgbaColor } = generalFormat();

watch(() => props.userDetail, (user) => {
    checked.value = user.status === 'active';
    form.user_id = props.userDetail.id
    form.name = props.userDetail.name
    form.email = props.userDetail.email
    form.phone = props.userDetail.phone
});

watch(countries, () => {
    selectedCountry.value = countries.value.find(country => country.phone_code === props.userDetail.dial_code);
});

const openDialog = () => {
    visible.value = true
}

const form = useForm({
    user_id: '',
    name: '',
    email: '',
    dial_code: '',
    phone: '',
    phone_number: '',
});

const getResults = async () => {
    try {
        const response = await axios.get('/member/getFilterData');
        countries.value = response.data.countries;
    } catch (error) {
        console.error('Error changing locale:', error);
    }
};

getResults();

const submitForm = () => {
    form.dial_code = selectedCountry.value;

    if (selectedCountry.value) {
        form.phone_number = selectedCountry.value.phone_code + form.phone;
    }

    form.post(route('member.updateContactInfo'), {
        onSuccess: () => {
            visible.value = false;
        },
    });
};

const confirm = useConfirm();

const requireConfirmation = (action_type) => {
    const messages = {
        activate_member: {
            group: 'headless-gray',
            header: trans('public.deactivate_member'),
            text: trans('public.deactivate_member_caption'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.confirm'),
            action: () => {
                router.visit(route('member.updateMemberStatus', props.userDetail.id), {
                    method: 'post',
                    data: {
                        id: props.userDetail.id,
                    },
                })

                checked.value = !checked.value;
            }
        },
        deactivate_member: {
            group: 'headless-primary',
            header: trans('public.activate_member'),
            text: trans('public.activate_member_caption'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.confirm'),
            action: () => {
                router.visit(route('member.updateMemberStatus', props.userDetail.id), {
                    method: 'post',
                    data: {
                        id: props.userDetail.id,
                    },
                })

                checked.value = !checked.value;
            }
        },
    };

    const { group, header, text, dynamicText, suffix, actionType, cancelButton, acceptButton, action } = messages[action_type];

    confirm.require({
        group,
        header,
        actionType,
        text,
        dynamicText,
        suffix,
        cancelButton,
        acceptButton,
        accept: action
    });
};

const handleMemberStatus = () => {
    if (props.userDetail.status === 'active') {
        requireConfirmation('activate_member')
    } else {
        requireConfirmation('deactivate_member')
    }
}

</script>

<template>
    <div class="bg-white w-full xl:min-w-[540px] flex flex-col gap-6 md:gap-5 xl:gap-8 p-4 md:py-6 md:px-8 rounded-2xl shadow-toast self-stretch">
        <div class="flex flex-col pb-6 md:pb-5 xl:pb-8 items-start gap-4 self-stretch border-b border-gray-200">
            <div class="flex justify-between items-start self-stretch">
                <div class="w-20 h-20 grow-0 shrink-0 rounded-full overflow-hidden bg-primary-100">
                    <div v-if="userDetail">
                        <div v-if="userDetail.profile_photo">
                            <img :src="userDetail.profile_photo" alt="Profile Photo" class="w-full object-cover" />
                        </div>
                        <div v-else class="p-2">
                            <DefaultProfilePhoto />
                        </div>
                    </div>
                    <div v-else class="animate-pulse p-2">
                        <DefaultProfilePhoto />
                    </div>
                </div>
                <div class="flex gap-2 items-center">
                    <div class="p-2.5 flex items-center hover:bg-gray-100 rounded-full">
                        <InputSwitch
                            v-model="checked"
                            readonly
                            @click="handleMemberStatus"
                        />
                    </div>
                    <Button
                        type="button"
                        iconOnly
                        size="sm"
                        variant="gray-text"
                        pill
                        @click="openDialog()"
                        :disabled="!userDetail"
                    >
                        <Edit01Icon class="w-4 h-4 text-gray-500"/>
                    </Button>
                </div>
            </div>
            <div v-if="userDetail" class="flex flex-col items-start gap-1.5 self-stretch">
                <div class="flex items-center gap-3 self-stretch">
                    <div class="truncate text-gray-950 md:text-lg font-semibold">
                        {{ userDetail.name }}
                    </div>
                    <StatusBadge :value="userDetail.role">{{ $t(`public.${userDetail.role}`) }}</StatusBadge>
                    <StatusBadge :value="userDetail.status">{{ $t(`public.${userDetail.status}`) }}</StatusBadge>
                </div>
                <div class="text-gray-700 text-sm md:text-base">{{ userDetail.id_number }}</div>
            </div>
            <div v-else class="animate-pulse flex flex-col items-start gap-1.5 self-stretch">
                <div class="h-4 bg-gray-200 rounded-full w-48 my-2 md:my-3"></div>
                <div class="h-2 bg-gray-200 rounded-full w-20 mb-1"></div>
            </div>
        </div>
        <div v-if="userDetail" class="flex flex-col justify-center items-center gap-5 self-stretch">
            <div class="flex justify-center items-center gap-5 self-stretch">
                <div class="flex flex-col justify-center items-start gap-2 w-1/2">
                    <div class="text-gray-500 text-xs w-full truncate">{{ $t('public.email_address') }}</div>
                    <div class="truncate text-gray-950 text-sm font-medium w-full">{{ userDetail.email }}</div>
                </div>
                <div class="flex flex-col justify-center items-start gap-2 w-1/2">
                    <div class="text-gray-500 text-xs w-full truncate">{{ $t('public.phone_number') }}</div>
                    <div class="truncate text-gray-950 text-sm font-medium w-full">{{ userDetail.dial_code }} {{ userDetail.phone }}</div>
                </div>
            </div>
            <div class="flex justify-center items-center gap-5 self-stretch">
                <div class="flex flex-col justify-center items-start gap-2 w-1/2">
                    <div class="text-gray-500 text-xs w-full truncate">{{ $t('public.group') }}</div>
                    <div
                        v-if="userDetail.group_id"
                        class="flex items-center gap-2 rounded justify-center py-1 px-2"
                        :style="{ backgroundColor: formatRgbaColor(userDetail.group_color, 0.1) }"
                    >
                        <div
                            class="w-1.5 h-1.5 grow-0 shrink-0 rounded-full"
                            :style="{ backgroundColor: `#${userDetail.group_color}` }"
                        ></div>
                        <div
                            class="text-xs font-semibold"
                            :style="{ color: `#${userDetail.group_color}` }"
                        >
                            {{ userDetail.group_name }}
                        </div>
                    </div>
                    <div v-else>
                        -
                    </div>
                </div>
                <div class="flex flex-col justify-center items-start gap-2 w-1/2">
                    <div class="text-gray-500 text-xs w-full truncate">{{ $t('public.upline') }}</div>
                    <div class="flex items-center gap-2 w-full">
                        <div class="w-[26px] h-[26px] grow-0 shrink-0 rounded-full overflow-hidden">
                            <div v-if="userDetail.upline_profile_photo">
                                <img :src="userDetail.upline_profile_photo" alt="Profile Photo" />
                            </div>
                            <div v-else>
                                <DefaultProfilePhoto />
                            </div>
                        </div>
                        <div class="truncate text-gray-950 text-sm font-medium w-full">{{ userDetail.upline_name ?? '-' }}</div>
                    </div>
                </div>
            </div>
            <div class="flex justify-center items-center gap-5 self-stretch">
                <div class="flex flex-col justify-center items-start gap-2 w-1/2">
                    <div class="text-gray-500 text-xs w-full truncate">{{ $t('public.total_referred_member') }}</div>
                    <div class="truncate text-gray-950 text-sm font-medium w-full">{{ userDetail.total_direct_member }}</div>
                </div>
                <div class="flex flex-col justify-center items-start gap-2 w-1/2">
                    <div class="text-gray-500 text-xs w-full truncate">{{ $t('public.total_referred_agent') }}</div>
                    <div class="truncate text-gray-950 text-sm font-medium w-full">{{ userDetail.total_direct_agent }}</div>
                </div>
            </div>
        </div>
        <div v-else class="flex flex-col justify-center items-center gap-5 self-stretch animate-pulse">
            <div class="flex justify-center items-center gap-5 self-stretch">
                <div class="flex flex-col justify-center items-start gap-2 w-1/2">
                    <div class="text-gray-500 text-xs w-full truncate">{{ $t('public.email_address') }}</div>
                    <div class="truncate text-gray-950 text-sm font-medium w-full">
                        <div class="h-2 bg-gray-200 rounded-full w-48 my-2"></div>
                    </div>
                </div>
                <div class="flex flex-col justify-center items-start gap-2 w-1/2">
                    <div class="text-gray-500 text-xs w-full truncate">{{ $t('public.phone_number') }}</div>
                    <div class="h-2 bg-gray-200 rounded-full w-36 my-2"></div>
                </div>
            </div>
            <div class="flex justify-center items-center gap-5 self-stretch">
                <div class="flex flex-col justify-center items-start gap-2 w-1/2">
                    <div class="text-gray-500 text-xs w-full truncate">{{ $t('public.group') }}</div>
                    <div class="h-3 bg-gray-200 rounded-full w-20 mt-1 mb-1.5"></div>
                </div>
                <div class="flex flex-col justify-center items-start gap-2 w-1/2">
                    <div class="text-gray-500 text-xs w-full truncate">{{ $t('public.upline') }}</div>
                    <div class="h-3 bg-gray-200 rounded-full w-36 mt-1 mb-1.5"></div>
                </div>
            </div>
            <div class="flex justify-center items-center gap-5 self-stretch">
                <div class="flex flex-col justify-center items-start gap-2 w-1/2">
                    <div class="text-gray-500 text-xs w-full truncate">{{ $t('public.total_referred_member') }}</div>
                    <div class="h-2 bg-gray-200 rounded-full w-36 mt-2 mb-1"></div>
                </div>
                <div class="flex flex-col justify-center items-start gap-2 w-1/2">
                    <div class="text-gray-500 text-xs w-full truncate">{{ $t('public.total_referred_agent') }}</div>
                    <div class="h-2 bg-gray-200 rounded-full w-36 mt-2 mb-1"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- edit contact info -->
    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.contact_information')"
        class="dialog-xs md:dialog-sm"
    >
        <form>
            <div class="flex flex-col gap-5">
                <div class="flex flex-col gap-1">
                    <InputLabel for="name" :value="$t('public.name')" />
                    <InputText
                        id="name"
                        type="text"
                        class="block w-full"
                        v-model="form.name"
                        :placeholder="$t('public.enter_name')"
                        :invalid="!!form.errors.name"
                        autocomplete="name"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="flex flex-col gap-1">
                    <InputLabel for="email" :value="$t('public.email')" />
                    <InputText
                        id="email"
                        type="email"
                        class="block w-full"
                        v-model="form.email"
                        :placeholder="$t('public.enter_email')"
                        :invalid="!!form.errors.email"
                        autocomplete="email"
                    />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="flex flex-col gap-1 items-start self-stretch">
                    <InputLabel for="phone" :value="$t('public.phone_number')" />
                    <div class="flex gap-2 items-center self-stretch relative">
                        <Dropdown
                            v-model="selectedCountry"
                            :options="countries"
                            filter
                            :filterFields="['name', 'phone_code']"
                            optionLabel="name"
                            :placeholder="$t('public.phone_code')"
                            class="w-[100px]"
                            scroll-height="236px"
                            :invalid="!!form.errors.phone"
                        >
                            <template #value="slotProps">
                                <div v-if="slotProps.value" class="flex items-center">
                                    <div>{{ slotProps.value.phone_code }}</div>
                                </div>
                                <span v-else>
                                            {{ slotProps.placeholder }}
                                        </span>
                            </template>
                            <template #option="slotProps">
                                <div class="flex items-center w-[262px] md:max-w-[236px]">
                                    <div>{{ slotProps.option.name }} <span class="text-gray-500">{{ slotProps.option.phone_code }}</span></div>
                                </div>
                            </template>
                        </Dropdown>

                        <InputText
                            id="phone"
                            type="text"
                            class="block w-full"
                            v-model="form.phone"
                            :placeholder="$t('public.phone_number')"
                            :invalid="!!form.errors.phone"
                        />
                    </div>
                    <InputError :message="form.errors.phone" />
                </div>
            </div>
            <div class="flex justify-end items-center pt-10 md:pt-7 gap-4 self-stretch">
                <Button
                    type="button"
                    variant="gray-tonal"
                    class="w-full md:w-[120px]"
                    :disabled="form.processing"
                    @click.prevent="visible = false"
                >
                    {{ $t('public.cancel') }}
                </Button>
                <Button
                    variant="primary-flat"
                    class="w-full md:w-[120px]"
                    :disabled="form.processing"
                    @click="submitForm"
                >
                    {{ $t('public.save') }}
                </Button>
            </div>
        </form>
    </Dialog>
</template>

