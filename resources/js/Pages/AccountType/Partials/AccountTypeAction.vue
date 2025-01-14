<script setup>
import { IconAdjustmentsHorizontal } from "@tabler/icons-vue";
import Button from "@/Components/Button.vue";
import {computed, h, ref, watch} from "vue";
import TieredMenu from "primevue/tieredmenu";
import InputSwitch from "primevue/inputswitch";
import {router} from "@inertiajs/vue3";
import {useConfirm} from "primevue/useconfirm";
import {trans} from "laravel-vue-i18n";
import AccountTypeSetting from "@/Pages/AccountType/Partials//AccountTypeSetting.vue";

const props = defineProps({
    accountType: Object,
    leverages: Array,
    loading: Boolean,
})

const checked = ref(props.accountType.status === 'active')

watch(() => props.accountType.status, (newStatus) => {
    checked.value = newStatus === 'active';
});

const confirm = useConfirm();

const requireConfirmation = (action_type) => {
    const messages = {
        deactivate_account_type: {
            group: 'headless-gray',
            header: trans('public.deactivate_header'),
            message: trans('public.activate_content'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.deactivate'),
            action: () => {
                router.patch(route('accountType.updateStatus', props.accountType.id));

                checked.value = !checked.value;
            }
        },
        activate_account_type: {
            group: 'headless-primary',
            header: trans('public.activate_header'),
            message: trans('public.activate_content'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.confirm'),
            action: () => {
                router.patch(route('accountType.updateStatus', props.accountType.id));

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

const handleAccountTypeStatus = () => {
    if (props.accountType.status === 'active') {
        requireConfirmation('deactivate_account_type')
    } else {
        requireConfirmation('activate_account_type')
    }
}

</script>

<template>
    <div class="flex gap-3 items-center justify-center">
        <InputSwitch
            v-model="checked"
            readonly
            @click="handleAccountTypeStatus"
            :disabled="props.loading"
        />
        <AccountTypeSetting 
            :accountType="props.accountType" 
            :leverages="props.leverages"
            :loading="props.loading"
        />
    </div>
</template>
