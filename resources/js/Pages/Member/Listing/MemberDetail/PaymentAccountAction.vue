<script setup>
import {
    IconDotsVertical,
    IconEdit,
    IconId,
    IconArrowsRightLeft,
    IconUserUp,
    IconLockCog,
    IconTrash,
    IconDeviceLaptop
} from "@tabler/icons-vue";
import {Edit01Icon} from "@/Components/Icons/outline.jsx";
import Button from "@/Components/Button.vue";
import {computed, h, ref, watch} from "vue";
import TieredMenu from "primevue/tieredmenu";
import InputSwitch from "primevue/inputswitch";
import {router} from "@inertiajs/vue3";
import {useConfirm} from "primevue/useconfirm";
import {trans} from "laravel-vue-i18n";
import Dialog from "primevue/dialog";
import EditPaymentAccount from "@/Pages/Member/Listing/MemberDetail/Partials/EditPaymentAccount.vue";

const props = defineProps({
    paymentAccount: Object,
})

const menu = ref();
const visible = ref(false)
const dialogType = ref('')

const items = ref([
    {
        label: 'edit',
        icon: h(Edit01Icon),
        command: () => {
            visible.value = true;
            dialogType.value = 'edit_payment_account';
        },
    }
]);


const toggle = (event) => {
    menu.value.toggle(event);
};

</script>

<template>
    <div class="flex gap-3 items-center justify-center">
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
                    <component :is="item.icon" size="20" stroke-width="1.25" :color="'#667085'" />
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
        <template v-if="dialogType === 'edit_payment_account'">
            <EditPaymentAccount
                :paymentAccount="paymentAccount"
                @update:visible="visible = false"
            />
        </template>
    </Dialog>
</template>
