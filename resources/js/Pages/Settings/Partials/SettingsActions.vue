<script setup>
import { IconAdjustmentsHorizontal } from "@tabler/icons-vue";
import Button from "@/Components/Button.vue";
import { computed, h, ref, watch } from "vue";
import TieredMenu from "primevue/tieredmenu";
import InputSwitch from "primevue/inputswitch";
import { router } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import { trans } from "laravel-vue-i18n";

const props = defineProps({
    setting: Object,
    loading: Boolean,
});

const checked = ref(props.setting.status === 'active');

watch(() => props.setting.status, (newStatus) => {
    checked.value = newStatus === 'active';
});

const confirm = useConfirm();

const requireConfirmation = (action_type) => {
    const messages = {
        deactivate_setting: {
            group: 'headless-gray',
            header: trans('public.deactivate_setting_header'),
            message: trans('public.deactivate_setting_message'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.deactivate'),
            action: () => {
                router.patch(route('settings.updateSiteSettingsStatus', props.setting.id));

                checked.value = !checked.value;
            }
        },
        activate_setting: {
            group: 'headless-primary',
            header: trans('public.activate_setting_header'),
            message: trans('public.activate_setting_message'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.confirm'),
            action: () => {
                router.patch(route('settings.updateSiteSettingsStatus', props.setting.id));

                checked.value = !checked.value;
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
    if (props.setting.status === 'active') {
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
        <!-- <AccountTypeSetting 
            :accountType="props.accountType" 
            :leverages="props.leverages"
            :users="props.users"
            :loading="props.loading"
        /> -->
    </div>
</template>
