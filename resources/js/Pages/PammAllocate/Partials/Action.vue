<script setup>
import {
    IconDotsVertical,
    IconSettingsDollar,
    IconListSearch,
    IconUserShield,
    IconPencilMinus,
    IconBan,
    IconTrash,
} from "@tabler/icons-vue";
import Button from "@/Components/Button.vue";
import {computed, h, ref, watch} from "vue";
import { transactionFormat } from '@/Composables/index.js';
import TieredMenu from "primevue/tieredmenu";
import InputSwitch from "primevue/inputswitch";
import {Link, useForm} from "@inertiajs/vue3";
import {useConfirm} from "primevue/useconfirm";
import {trans} from "laravel-vue-i18n";
import Dialog from "primevue/dialog";
import DailyProfit from "@/Pages/PammAllocate/Partials/DailyProfit/DailyProfit.vue";
import PammReport from "@/Pages/PammAllocate/Partials/PammReport/PammReport.vue";
import PammAccess from "@/Pages/PammAllocate/Partials/PammAccess.vue";
import EditAssetMaster from "@/Pages/PammAllocate/Partials/EditAssetMaster.vue";

const { formatDate, formatDateTime, formatAmount } = transactionFormat();

const props = defineProps({
    master: Object,
    groupsOptions: Array,
})
const menu = ref();
const visible = ref(false)
const dialogType = ref('')
const master = ref()
const groupsOptions = ref()

watch(() => [props.master, props.groupsOptions], ([newMaster, newgroups]) => {
        master.value = newMaster;
        groupsOptions.value = newgroups;
    }, { immediate: true }
);

const items = ref([
    {
        label: 'daily_profit',
        icon: h(IconSettingsDollar),
        command: () => {
            visible.value = true;
            dialogType.value = 'daily_profit';
        },
    },
    {
        label: 'pamm_report',
        icon: h(IconListSearch),
        command: () => {
            visible.value = true;
            dialogType.value = 'pamm_report';
        },
    },
    // {
    //     label: 'pamm_access',
    //     icon: h(IconUserShield),
    //     command: () => {
    //         visible.value = true;
    //         dialogType.value = 'pamm_access';
    //     },
    // },
    {
        label: 'edit',
        icon: h(IconPencilMinus),
        command: () => {
            visible.value = true;
            dialogType.value = 'edit';
        },
    },
    {
        separator: true,
    },
    props.master?.total_real_investors > 0
        ? {
            label: 'disband',
            icon: h(IconBan),
            color: 'red',
            command: () => {
                requireConfirmation('disband');
            },
        }
        : {
            label: 'delete',
            icon: h(IconTrash),
            color: 'red',
            command: () => {
                requireConfirmation('delete');
            },
        },
]);

const checked = ref(props.master.status === 'active')
const confirm = useConfirm();

const form = useForm({
    id: props.master.id,
    action: '',
})

const requireConfirmation = (type) => {
    const messages = {
        delete: {
            group: 'headless-error',
            actionType: 'delete',
            header: trans('public.delete_pamm_header'),
            text: trans('public.delete_pamm_message'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.delete'),
            action: () => {
                form.action = 'delete'
                form.post(route('pamm_allocate.disband'));
            }
        },
        disband: {
            group: 'headless-error',
            actionType: 'disband',
            header: trans('public.disband_header'),
            text: trans('public.disband_message_text'),
            dynamicText: '$' + formatAmount(props.master.total_fund),
            suffix: trans('public.disband_message_text_suffix'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.disband'),
            action: () => {
                form.action = 'disband'
                form.post(route('pamm_allocate.disband'));
            }
        },
        activate: {
            group: props.master.status === 'active' ? 'headless-gray' : 'headless-primary',
            actionType: props.master.status === 'active' ? 'hide_asset_master' : 'show_asset_master',
            header: props.master.status === 'active' ? trans('public.hide_asset_master_header') : trans('public.show_asset_master_header'),
            text: props.master.status === 'active' ? trans('public.hide_asset_master_message') : trans('public.show_asset_master_message'),
            cancelButton: props.master.status === 'active' ? trans('public.cancel') : trans('public.cancel'),
            acceptButton: props.master.status === 'active' ? trans('public.hide') : trans('public.confirm'),
            action: () => {
                form.post(route('pamm_allocate.update_asset_master_status'), {
                    onSuccess: () => {
                        checked.value = !checked.value;
                    }
                });
            }
        }
    };

    const { group, actionType, header, message, text, dynamicText, suffix, cancelButton, acceptButton, action } = messages[type] || {};

    confirm.require({
        group,
        actionType,
        header,
        message,
        text,
        dynamicText,
        suffix,
        cancelButton,
        acceptButton,
        accept: action
    });
};


const toggle = (event) => {
    menu.value.toggle(event);
};
</script>

<template>
    <InputSwitch
        v-model="checked"
        readonly
        @click="requireConfirmation('activate');"
    />
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
                :class="{
                    'text-error-500': item.color === 'red',
                    'text-gray-950': item.color !== 'red'
                }"
            >
                <component :is="item.icon" size="20" stroke-width="1.25" />
                <span class="font-medium">{{ $t('public.' + item.label) }}</span>
            </div>
        </template>
    </TieredMenu>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t(`public.${dialogType}`)"
        :class="{
            'dialog-xs md:dialog-md': dialogType !== 'pamm_report',
            'dialog-xs md:dialog-lg': dialogType === 'pamm_report'
        }"
    >
        <template v-if="dialogType === 'daily_profit'">
            <DailyProfit
                :master="master"
                @update:visible="visible = false"
            />
        </template>
        <template v-if="dialogType === 'pamm_report'">
            <PammReport
                :master="master"
                @update:visible="visible = false"
            />
        </template>
        <template v-if="dialogType === 'pamm_access'">
            <PammAccess
                :master="master"
                @update:visible="visible = false"
            />
        </template>
        <template v-if="dialogType === 'edit'">
            <EditAssetMaster
                :master="master"
                :groupsOptions="groupsOptions"
                @update:visible="visible = false"
            />
        </template>
    </Dialog>
</template>
