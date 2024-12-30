<script setup>
import {h, ref} from 'vue';
import {
    IconDotsVertical,
    IconReportSearch,
    IconPencilMinus,
    IconBan,
    IconCheckbox
} from '@tabler/icons-vue';
import Button from '@/Components/Button.vue';
import Dialog from 'primevue/dialog';
import EditGroup from '@/Pages/Group/Partials/EditGroup.vue';
import { useConfirm } from "primevue/useconfirm";
import { router } from '@inertiajs/vue3';
import GroupTransactions from '@/Pages/Group/Partials/GroupTransactions.vue';
import { trans } from 'laravel-vue-i18n';
import TieredMenu from "primevue/tieredmenu";

const props = defineProps({
    group: Object,
    date: Array,
})

const menu = ref();
const visible = ref(false);
const dialogTitle = ref('');

const toggle = (event) => {
    menu.value.toggle(event);
};

const items = ref([
    {
        label: 'transactions',
        icon: h(IconReportSearch),
        command: () => {
            visible.value = true;
            dialogTitle.value = 'view_group_transactions';
        },
    },
    {
        label: 'edit',
        icon: h(IconPencilMinus),
        command: () => {
            visible.value = true;
            dialogTitle.value = 'edit_group';
        },
    },
    {
        label: 'settlement',
        icon: h(IconCheckbox),
        command: () => {
            requireConfirmation('settlement')
        },
    },
    {
        separator: true
    },
    {
        label: 'remove',
        icon: h(IconBan),
        command: () => {
            requireConfirmation('remove_group')
        }
    }
]);

const confirm = useConfirm();

const requireConfirmation = (action_type) => {
    const messages = {
        settlement: {
            group: 'headless-primary',
            actionType: 'settlement',
            header: trans('public.mark_settlement_header'),
            text: trans('public.mark_settlement_message'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.confirm'),
            action: () => {
                router.visit(route('group.markSettlementReport', props.group.id), {method: 'post'})
            }
        },
        delete_account: {
            group: 'headless-error',
            header: trans('public.delete_group_header'),
            text: trans('public.delete_group_caption'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.delete_confirm'),
            action: () => {
                router.visit(route('group.delete', props.group.id), {method: 'delete'})
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
</script>

<template>
    <div class="flex items-center justify-center gap-2">
        <Button
            variant="gray-text"
            size="sm"
            type="button"
            iconOnly
            pill
            @click="toggle"
            aria-haspopup="true"
            aria-controls="overlay_tmenu"
        >
            <IconDotsVertical size="16" stroke-width="1.25" color="#667085" />
        </Button>

        <TieredMenu ref="menu" id="overlay_tmenu" :model="items" popup>
            <template #item="{ item, props, hasSubmenu }">
                <div
                    class="flex items-center gap-3 self-stretch"
                    v-bind="props.action"
                >
                    <component :is="item.icon" size="20" stroke-width="1.25" :color="item.label === 'remove' ? '#F04438' : '#667085'" />
                    <span class="font-medium" :class="{'text-error-500': item.label === 'remove'}">{{ $t(`public.${item.label}`) }}</span>
                </div>
            </template>
        </TieredMenu>
    </div>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t(`public.${dialogTitle}`)"
        class="dialog-xs"
        :class="[
            {'sm:dialog-md': dialogTitle === 'edit_group'},
            {'sm:dialog-lg': dialogTitle === 'view_group_transactions'},
        ]"
    >
        <template v-if="dialogTitle === 'edit_group'">
            <EditGroup
                :group="group"
                @closeDialog="visible = $event"
            />
        </template>

        <template v-if="dialogTitle === 'view_group_transactions'">
            <GroupTransactions :group="group" :date="date" />
        </template>
    </Dialog>
</template>
