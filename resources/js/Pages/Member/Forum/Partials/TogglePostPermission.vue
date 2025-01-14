<script setup>

import InputSwitch from "primevue/inputswitch";
import {ref, watch} from "vue";
import {useConfirm} from "primevue/useconfirm";
import {trans} from "laravel-vue-i18n";
import {router} from "@inertiajs/vue3";

const props = defineProps({
    ib: Object,
})

const checked = ref(props.ib.isSelected);

watch(() => props.ib.isSelected, (newStatus) => {
    checked.value = newStatus;
});

const confirm = useConfirm();

const requireConfirmation = (action_type) => {
    const messages = {
        grant_permission: {
            group: 'headless-primary',
            actionType: 'permission',
            header: trans('public.grant_permit'),
            text: trans('public.grant_permit_caption'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.confirm'),
            action: () => {
                router.post(route('member.updatePostPermission', props.ib.id), {
                    id: props.ib.id,
                })

                checked.value = !checked.value;
            }
        },
        remove_permission: {
            group: 'headless-gray',
            actionType: 'permission',
            header: trans('public.remove_permit'),
            text: trans('public.remove_permit_caption'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.confirm'),
            action: () => {
                router.post(route('member.updatePostPermission', props.ib.id), {
                    id: props.ib.id,
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

const handlePermissions = () => {
    if (props.ib.isSelected) {
        requireConfirmation('remove_permission')
    } else {
        requireConfirmation('grant_permission')
    }
}
</script>

<template>
    <div class="p-[9px] flex items-center justify-end">
        <InputSwitch
            v-model="checked"
            readonly
            @click="handlePermissions"
        />
    </div>
</template>

