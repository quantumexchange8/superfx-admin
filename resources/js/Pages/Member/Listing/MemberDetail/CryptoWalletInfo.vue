<script setup>
import Button from "@/Components/Button.vue";
import {Edit01Icon} from "@/Components/Icons/outline.jsx";
import {computed, ref, watch} from "vue";
import Dialog from "primevue/dialog";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import InputText from "primevue/inputtext";
import {useForm} from "@inertiajs/vue3";

const props = defineProps({
    paymentAccounts: {
        type: Array,
        default: () => [],
    },
    userDetail: Object,
})

const displayAccounts = computed(() => {
    if (props.paymentAccounts.length === 0) {
        return new Array(3).fill({ wallet_name: '', token_address: '' });
    }
    return props.paymentAccounts.concat(new Array(3 - props.paymentAccounts.length).fill({ wallet_name: '', token_address: '' }));
});

watch(
    () => props.paymentAccounts,
    (newAccounts) => {
        if (props.paymentAccounts.length > 0) {
            const updatedAccounts = newAccounts.concat(new Array(3 - newAccounts.length).fill({ wallet_name: '', token_address: '' }));
            form.wallet_name = updatedAccounts.map((account, index) => account.payment_account_name || form.wallet_name[index] || '');
            form.token_address = updatedAccounts.map((account, index) => account.account_no || form.token_address[index] || '');
        }
    },
    { immediate: true }
);

const visible = ref(false);

const openDialog = () => {
    visible.value = true
}

const form = useForm({
    id: new Array(3).fill(''),
    user_id: '',
    wallet_name: new Array(3).fill(''),
    token_address: new Array(3).fill(''),
});

const submitForm = () => {
    form.id = displayAccounts.value.map((account, index) =>
        account && account.id ? account.id : form.id[index]
    );
    form.wallet_name = displayAccounts.value.map((account, index) =>
        account && account.wallet_name ? account.wallet_name : form.wallet_name[index]
    );
    form.token_address = displayAccounts.value.map((account, index) =>
        account && account.token_address ? account.token_address : form.token_address[index]
    );

    if (props.userDetail) {
        form.user_id = props.userDetail.id;
    }

    form.post(route('member.updateCryptoWalletInfo'), {
        onSuccess: () => {
            visible.value = false;
        },
    });
};
</script>

<template>
    <div class="flex flex-col items-start p-4 md:py-6 md:px-8 gap-3 self-stretch rounded-2xl bg-white shadow-toast">
        <div class="flex justify-between items-center self-stretch">
            <div class="text-gray-950 text-sm font-bold">{{ $t('public.crypto_wallet_information') }}</div>
            <Button
                type="button"
                iconOnly
                size="sm"
                variant="gray-text"
                @click="openDialog"
                pill
                :disabled="!userDetail"
            >
                <Edit01Icon class="w-4 h-4 text-gray-500"/>
            </Button>
        </div>
        <div v-if="displayAccounts" class="flex flex-col divide-y items-start self-stretch">
            <div
                v-for="(account, index) in displayAccounts"
                :key="index"
                class="flex text-sm flex-col items-start gap-1 self-stretch py-3 first:pt-0 last:pb-0"
            >
                <div class="font-semibold truncate w-full">{{ account.payment_account_name ? account.payment_account_name : '-' }}</div>
                <div class="font-medium truncate w-full">{{ account.account_no ? account.account_no : '-' }}</div>
            </div>
        </div>
    </div>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.crypto_wallet_information')"
        class="dialog-xs md:dialog-sm"
    >
        <form>
            <div class="flex flex-col gap-5">
                <div
                    v-for="(account, index) in displayAccounts"
                    class="flex flex-col gap-2"
                >
                    <InputLabel
                        for="wallet_name"
                    >
                        {{ $t('public.wallet') }} #{{ index + 1 }}
                    </InputLabel>
                    <InputText
                        :id="`wallet_name_${index+1}`"
                        type="text"
                        class="block w-full"
                        :aria-label="`wallet_name_${index+1}`"
                        v-model="form.wallet_name[index]"
                        :placeholder="$t('public.wallet_name')"
                        :invalid="!!form.errors[`wallet_name.${index}`]"
                    />
                    <InputError :message="form.errors[`wallet_name.${index}`]" />
                    <InputText
                        :id="`token_address_${index+1}`"
                        type="text"
                        class="block w-full"
                        :aria-label="`token_address_${index+1}`"
                        v-model="form.token_address[index]"
                        :placeholder="$t('public.token_address')"
                        :invalid="!!form.errors[`token_address.${index}`]"
                    />
                    <InputError :message="form.errors[`token_address.${index}`]" />
                </div>
            </div>
            <div class="flex justify-end items-center pt-10 md:pt-7 gap-4 self-stretch">
                <Button
                    type="button"
                    variant="gray-tonal"
                    class="w-full md:w-[120px]"
                    :disabled="form.processing"
                    @click.prevent="visible = false"
                >
                    {{ $t('public.cancel') }}
                </Button>
                <Button
                    variant="primary-flat"
                    class="w-full md:w-[120px]"
                    :disabled="form.processing"
                    @click="submitForm"
                >
                    {{ $t('public.save') }}
                </Button>
            </div>
        </form>
    </Dialog>
</template>
