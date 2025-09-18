<script setup>
import { ref, watch } from "vue";
import InputSwitch from "primevue/inputswitch";
import { useConfirm } from "primevue/useconfirm";
import { trans } from "laravel-vue-i18n";
import toast from "@/Composables/toast.js";

const props = defineProps({
    tradingPlatform: Object,
});

const checked = ref(props.tradingPlatform.status === 'active');

watch(() => props.tradingPlatform.status, (newStatus) => {
    checked.value = newStatus === 'active';
});

const confirm = useConfirm();

const submitForm = async () => {
    const originalChecked = checked.value;

    checked.value = !originalChecked;

    const newStatus = checked.value ? 'active' : 'inactive';

    try {
        const response = await axios.patch(
            route('system.updateTradingPlatform', props.tradingPlatform.id),
            { status: newStatus }
        );

        props.tradingPlatform.status = newStatus;

        toast.add({
            title: response.data.toast_title,
            type: response.data.type,
        });
    } catch (error) {
        checked.value = originalChecked;
        props.tradingPlatform.status = originalChecked ? 'active' : 'inactive';

        const errData = error?.response?.data;

        toast.add({
            title: errData?.toast_title || trans('public.update_failed'),
            type: 'error',
        });

        console.error('Update failed:', error);
    }
};

const requireConfirmation = (action_type) => {
    const messages = {
        deactivate_setting: {
            group: 'headless-gray',
            header: trans('public.deactivate_setting_header'),
            message: trans('public.deactivate_setting_message'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.deactivate'),
            action: () => {
                submitForm();
            }
        },
        activate_setting: {
            group: 'headless-primary',
            header: trans('public.activate_setting_header'),
            message: trans('public.activate_setting_message'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.confirm'),
            action: () => {
                submitForm();
            }
        },
    };

    const { group, header, message, actionType, cancelButton, acceptButton, action } = messages[action_type];

    confirm.require({
        group,
        header,
        actionType,
        message,
        cancelButton,
        acceptButton,
        accept: action
    });
};

const handleSiteSettingStatus = () => {
    if (props.tradingPlatform.status === 'active') {
        requireConfirmation('deactivate_setting')
    } else {
        requireConfirmation('activate_setting')
    }
}
</script>

<template>
    <div class="flex gap-3 items-center justify-center">
        <InputSwitch
            v-model="checked"
            readonly
            @click="handleSiteSettingStatus"
            :disabled="props.loading"
        />
    </div>
</template>
