<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Passord from 'primevue/password';
import { useForm } from "@inertiajs/vue3";
import Button from "@/Components/Button.vue";
import Dropdown from "primevue/dropdown";
import { h, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import { IconUserCheck } from "@tabler/icons-vue";
import { trans, wTrans } from "laravel-vue-i18n";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";

const props = defineProps({
    member: Object,
})

const emit = defineEmits(['update:visible'])

const form = useForm({
    user_id: props.member.id,
    upline_id: props.member.upline_id,
    role: props.member.role,
})

const closeDialog = () => {
    emit('update:visible', false);
}

const uplines = ref(null);
const upline = ref(null);
const isLoading = ref(false);

const getAvailableUplines = async () => {
    isLoading.value = true;

    try {
        const url = props.member.role === 'agent' ? `/member/getAvailableUplines?id=${props.member.id}&role=agent` : `/member/getAvailableUplines?id=${props.member.id}`;

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

const submitForm = () => {
    form.upline_id = upline.value['value'];
    form.post(route('member.transferUpline'), {
        onSuccess: () => {
            closeDialog();
            form.reset();

            // Check if the role is 'agent' and require confirmation
            if (props.member.role === 'agent') {
                requireConfirmation('set_rebate');
            }
        },
    });
}

const confirm = useConfirm();

const requireConfirmation = (action_type) => {
    const messages = {
        set_rebate: {
            group: 'headless',
            color: 'primary',
            icon: h(IconUserCheck),
            header: trans('public.set_rebate_header'),
            message: trans('public.set_rebate_message'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.set_rebate'),
            action: () => {
                router.visit(route('rebate_allocate'), {
                    method: 'get',
                })
            }
        },
    };

    const { group, color, icon, header, message, cancelButton, acceptButton, action } = messages[action_type];

    confirm.require({
        group,
        color,
        icon,
        header,
        message,
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
                        :filterFields="['name']"
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
                :disabled="form.processing || isLoading"
                @click.prevent="submitForm"
            >
                {{ $t('public.reset') }}
            </Button>
        </div>
</template>
