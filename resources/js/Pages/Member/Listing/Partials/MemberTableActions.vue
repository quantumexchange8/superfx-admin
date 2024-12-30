<script setup>
import {
    IconDotsVertical,
    IconId,
    IconArrowsRightLeft,
    IconUserUp,
    IconLockCog,
    IconTrash,
    IconDeviceLaptop
} from "@tabler/icons-vue";
import Button from "@/Components/Button.vue";
import {computed, h, ref, watch} from "vue";
import TieredMenu from "primevue/tieredmenu";
import InputSwitch from "primevue/inputswitch";
import {router} from "@inertiajs/vue3";
import {useConfirm} from "primevue/useconfirm";
import {trans} from "laravel-vue-i18n";
import Dialog from "primevue/dialog";
import TransferUpline from "@/Pages/Member/Listing/Partials/TransferUpline.vue";
import UpgradeToAgent from "@/Pages/Member/Listing/Partials/UpgradeToAgent.vue";
import ResetPassword from "@/Pages/Member/Listing/Partials/ResetPassword.vue";

const props = defineProps({
    member: Object,
})

const menu = ref();
const visible = ref(false)
const dialogType = ref('')

const items = ref([
    {
        label: 'member_details',
        icon: h(IconId),
        command: () => {
            window.location.href = `/member/detail/${props.member.id_number}`;
        },
    },
    {
        label: 'access_portal',
        icon: h(IconDeviceLaptop),
        command: () => {
            window.open(route('member.access_portal', props.member.id), '_blank');
        },
    },
    {
        label: 'transfer_upline',
        icon: h(IconArrowsRightLeft),
        command: () => {
            visible.value = true;
            dialogType.value = 'transfer_upline';
        },
    },
    {
        label: 'upgrade_to_agent',
        icon: h(IconUserUp),
        command: () => {
            visible.value = true;
            dialogType.value = 'upgrade_to_agent';
        },
        role: 'member', // Add a custom property to check the role
    },
    {
        label: 'reset_password',
        icon: h(IconLockCog),
        command: () => {
            visible.value = true;
            dialogType.value = 'reset_password';
        },
    },
    {
        separator: true,
    },
    {
        label: 'delete_member',
        icon: h(IconTrash),
        command: () => {
            requireConfirmation('delete_member')
        },
    },
]);

const filteredItems = computed(() => {
    return items.value.filter(item => {
        return !(item.role && item.role === 'member' && props.member.role === 'agent');

    });
});

const checked = ref(props.member.status === 'active')

watch(() => props.member.status, (newStatus) => {
    checked.value = newStatus === 'active';
});

const confirm = useConfirm();

const requireConfirmation = (action_type) => {
    const messages = {
        activate_member: {
            group: 'headless-gray',
            header: trans('public.deactivate_member'),
            text: trans('public.deactivate_member_caption'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.confirm'),
            action: () => {
                router.visit(route('member.updateMemberStatus', props.member.id), {
                    method: 'post',
                    data: {
                        id: props.member.id,
                    },
                })

                checked.value = !checked.value;
            }
        },
        deactivate_member: {
            group: 'headless-primary',
            header: trans('public.activate_member'),
            text: trans('public.activate_member_caption'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.confirm'),
            action: () => {
                router.visit(route('member.updateMemberStatus', props.member.id), {
                    method: 'post',
                    data: {
                        id: props.member.id,
                    },
                })

                checked.value = !checked.value;
            }
        },
        delete_member: {
            group: 'headless-error',
            header: trans('public.delete_member'),
            text: trans('public.delete_member_desc'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.delete_confirm'),
            action: () => {
                router.visit(route('member.deleteMember'), {
                    method: 'delete',
                    data: {
                        id: props.member.id,
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

const toggle = (event) => {
    menu.value.toggle(event);
};

const handleMemberStatus = () => {
    if (props.member.status === 'active') {
        requireConfirmation('activate_member')
    } else {
        requireConfirmation('deactivate_member')
    }
}
</script>

<template>
    <div class="flex gap-3 items-center justify-center">
        <InputSwitch
            v-model="checked"
            readonly
            @click="handleMemberStatus"
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
        <TieredMenu ref="menu" id="overlay_tmenu" :model="filteredItems" popup>
            <template #item="{ item, props, hasSubmenu }">
                <div
                    class="flex items-center gap-3 self-stretch"
                    v-bind="props.action"
                >
                    <component :is="item.icon" size="20" stroke-width="1.25" :color="item.label === 'delete_member' ? '#F04438' : '#667085'" />
                    <span class="font-medium" :class="{'text-error-500': item.label === 'delete_member'}">{{ $t(`public.${item.label}`) }}</span>
                </div>
            </template>
        </TieredMenu>
    </div>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t(`public.${dialogType}`)"
        class="dialog-xs sm:dialog-md"
    >
        <template v-if="dialogType === 'transfer_upline'">
            <TransferUpline
                :member="member"
                @update:visible="visible = false"
            />
        </template>
        <template v-if="dialogType === 'upgrade_to_agent'">
            <UpgradeToAgent
                :member="member"
                @update:visible="visible = false"
            />
        </template>
        <template v-if="dialogType === 'reset_password'">
            <ResetPassword
                :member="member"
                @update:visible="visible = false"
            />
        </template>
    </Dialog>
<!--    <OverlayPanel ref="op">-->
<!--        <div class="py-2 flex flex-col items-center">-->
<!--            <Link :href="route('member.detail', member.id_number)" class="p-3 flex items-center gap-3 self-stretch hover:bg-gray-100 hover:cursor-pointer">-->
<!--                <IconId size="20" stroke-width="1.25" color="#667085" />-->
<!--                <div class="text-gray-950 text-sm font-medium">-->
<!--                    Member Details-->
<!--                </div>-->
<!--            </Link>-->
<!--            <UpgradeToAgent-->
<!--                :member="member"-->
<!--            />-->
<!--        </div>-->
<!--    </OverlayPanel>-->
</template>
