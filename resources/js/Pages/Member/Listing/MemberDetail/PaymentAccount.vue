<script setup>
import Button from "@/Components/Button.vue";
import {Edit01Icon} from "@/Components/Icons/outline.jsx";
import {computed, ref, watch} from "vue";
import Dialog from "primevue/dialog";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import InputText from "primevue/inputtext";
import {useForm} from "@inertiajs/vue3";
import PaymentAccountAction from "@/Pages/Member/Listing/MemberDetail/PaymentAccountAction.vue";
import StatusBadge from "@/Components/StatusBadge.vue";

const props = defineProps({
    paymentAccounts: Array,
    userDetail: Object,
})
</script>

<template>
    <div class="flex flex-col h-full max-h-full items-start p-4 md:py-6 md:px-8 gap-3 self-stretch rounded-2xl bg-white shadow-toast">
        <div class="flex justify-between items-center self-stretch">
            <div class="text-gray-950 text-sm font-bold">{{ $t('public.payment_account_information') }}</div>
            <!-- <Button
                type="button"
                iconOnly
                size="sm"
                variant="gray-text"
                @click="openDialog"
                pill
                :disabled="!userDetail"
            >
                <Edit01Icon class="w-4 h-4 text-gray-500"/>
            </Button> -->
        </div>
        <div v-if="props?.paymentAccounts" class="flex flex-col divide-y items-start self-stretch overflow-y-auto max-h-[250px]">
            <div
                v-for="(account, index) in props?.paymentAccounts"
                :key="index"
                class="flex text-sm gap-1 self-stretch py-3 first:pt-0 last:pb-0"
            >
                <div class="w-full grid grid-cols-1 gap-1">
                    <div class="text-gray-950 font-semibold truncate w-full">{{ account.payment_account_name ? account.payment_account_name : '-' }}</div>
                    <div class="text-gray-500 font-medium truncate w-full">{{ account.account_no ? account.account_no : '-' }}</div>
                    <div class="w-full flex items-center gap-3">
                        <StatusBadge variant="primary">
                            {{ account.payment_platform ? $t('public.' + account.payment_platform) : '-' }}
                        </StatusBadge>
                        <StatusBadge variant="info">
                            {{ account.payment_account_type ? $t('public.' + account.payment_account_type) : '-' }}
                        </StatusBadge>
                    </div>
                </div>
                <div class="flex items-center justify-center">
                    <PaymentAccountAction
                        :paymentAccount="account"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
