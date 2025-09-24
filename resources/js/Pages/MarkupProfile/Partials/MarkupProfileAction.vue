<script setup>
import { IconAdjustmentsHorizontal } from "@tabler/icons-vue";
import Button from "@/Components/Button.vue";
import {computed, h, ref, watch} from "vue";
import TieredMenu from "primevue/tieredmenu";
import InputSwitch from "primevue/inputswitch";
import {router} from "@inertiajs/vue3";
import {useConfirm} from "primevue/useconfirm";
import {trans} from "laravel-vue-i18n";
import MarkupProfileSetting from "@/Pages/MarkupProfile/Partials/MarkupProfileSetting.vue"

const props = defineProps({
    profile: Object,
    accountTypes: Array,
    users: Array,
    loading: Boolean,
})

const checked = ref(props.profile.status === 'active')

watch(() => props.profile.status, (newStatus) => {
    checked.value = newStatus === 'active';
});

const confirm = useConfirm();

const requireConfirmation = (action_type) => {
    const messages = {
        deactivate_profile: {
            group: 'headless-gray',
            header: trans('public.deactivate_profile_header'),
            message: trans('public.deactivate_profile_message'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.deactivate'),
            action: () => {
                router.patch(route('markup_profile.updateStatus'), {
                    id: props.profile.id,
                });

                checked.value = !checked.value;
            }
        },
        activate_profile: {
            group: 'headless-primary',
            header: trans('public.activate_profile_header'),
            message: trans('public.activate_profile_message'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.confirm'),
            action: () => {
                router.patch(route('markup_profile.updateStatus'), {
                    id: props.profile.id,
                });

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

const handleMarkupProfileStatus = () => {
    if (props.profile.status === 'active') {
        requireConfirmation('deactivate_profile')
    } else {
        requireConfirmation('activate_profile')
    }
}

</script>

<template>
    <div class="flex gap-3 items-center justify-center">
        <InputSwitch
            v-model="checked"
            readonly
            @click="handleMarkupProfileStatus"
            :disabled="props.loading"
        />
        <MarkupProfileSetting
            :profile="props.profile"
            :accountTypes="accountTypes"
            :users="users"
            :loading="props.loading"
        />
    </div>
</template>
