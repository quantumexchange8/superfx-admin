<script setup>
import Button from "@/Components/Button.vue";
import {IconAdjustmentsDollar, IconTrash} from "@tabler/icons-vue";
import TieredMenu from "primevue/tieredmenu";
import {h, onMounted, ref} from "vue";
import Dialog from "primevue/dialog";
import AccountAdjustment from "@/Pages/Member/Account/Partials/AccountAdjustment.vue";
import {useConfirm} from "primevue/useconfirm";
import {trans} from "laravel-vue-i18n";
import {router} from "@inertiajs/vue3";

const props = defineProps({
    account: Object,
})

const toggle = (event) => {
    menu.value.toggle(event);
};

const menu = ref();
const visible = ref(false);
const dialogType = ref('');
const items = ref([
    {
        label: 'account_balance',
        command: () => {
            visible.value = true;
            dialogType.value = 'account_balance';
        },
    },
    {
        label: 'account_credit',
        command: () => {
            visible.value = true;
            dialogType.value = 'account_credit';
        },
    },
]);

const confirm = useConfirm();

const requireConfirmation = (action_type) => {
    const messages = {
        delete_account: {
            group: 'headless-error',
            header: trans('public.delete_trading_account_header'),
            text: trans('public.delete_trading_account_message'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.delete_confirm'),
            action: () => {
                router.visit(route('member.accountDelete'), {
                    method: 'delete',
                    data: {
                        meta_login: props.account.meta_login,
                    },
                })
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

const deleteAccount = () => {
    requireConfirmation('delete_account')
}
</script>

<template>
    <div class="flex items-center justify-center gap-2">
        <Button
            type="button"
            variant="gray-text"
            size="sm"
            icon-only
            pill
            @click="toggle"
            aria-haspopup="true"
            aria-controls="overlay_tmenu"
        >
            <IconAdjustmentsDollar size="16" stroke-width="1.25" />
        </Button>
        <Button
            type="button"
            variant="error-text"
            size="sm"
            icon-only
            pill
            @click="deleteAccount"
        >
            <IconTrash size="16" stroke-width="1.25" />
        </Button>

        <TieredMenu ref="menu" id="overlay_tmenu" :model="items" popup>
            <template #item="{ item, props, hasSubmenu }">
                <div
                    class="flex items-center gap-3 self-stretch font-medium"
                    v-bind="props.action"
                >
                    {{ $t(`public.${item.label}`) }}
                </div>
            </template>
        </TieredMenu>
    </div>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t(`public.${dialogType + '_adjustment'}`)"
        class="dialog-xs sm:dialog-sm"
    >
        <template v-if="dialogType === 'account_balance'|| dialogType === 'account_credit' ">
            <AccountAdjustment
                :account="account"
                :dialogType="dialogType"
                @update:visible="visible = $event"
            />
        </template>
    </Dialog>
</template>
