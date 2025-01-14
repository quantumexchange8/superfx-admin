<script setup>
import Button from "@/Components/Button.vue";
import {IconAdjustmentsDollar, IconTrash} from "@tabler/icons-vue";
import TieredMenu from "primevue/tieredmenu";
import {h, onMounted, ref} from "vue";
import Dialog from "primevue/dialog";
import AccountAdjustment from "@/Pages/Member/Account/Partials/AccountAdjustment.vue";
import ChangeLeverage from "@/Pages/Member/Account/Partials/ChangeLeverage.vue";
import ChangeAccountGroup from "@/Pages/Member/Account/Partials/ChangeAccountGroup.vue";
import ChangePassword from "@/Pages/Member/Account/Partials/ChangePassword.vue";
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
    {
        label: 'change_leverage',
        command: () => {
            visible.value = true;
            dialogType.value = 'change_leverage';
        },
    },
    {
        label: 'change_account_type',
        command: () => {
            visible.value = true;
            dialogType.value = 'change_account_type';
        },
    },
    {
        label: 'change_password',
        command: () => {
            visible.value = true;
            dialogType.value = 'change_password';
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
                router.delete(route('member.accountDelete'), {
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
        :header="dialogType === 'account_balance' || dialogType === 'account_credit' ? $t(`public.${dialogType + '_adjustment'}`) : $t(`public.${dialogType}`)"
        class="dialog-xs sm:dialog-sm"
    >
        <template v-if="dialogType === 'account_balance'|| dialogType === 'account_credit' ">
            <AccountAdjustment
                :account="account"
                :dialogType="dialogType"
                @update:visible="visible = $event"
            />
        </template>
        <template v-if="dialogType === 'change_leverage'">
            <ChangeLeverage
                :account="account"
                @update:visible="visible = false"
            />
        </template>
        <template v-if="dialogType === 'change_account_type'">
            <ChangeAccountGroup
                :account="account"
                @update:visible="visible = false"
            />
        </template>
        <template v-if="dialogType === 'change_password'">
            <ChangePassword
                :account="account"
                @update:visible="visible = false"
            />
        </template>
    </Dialog>
</template>
