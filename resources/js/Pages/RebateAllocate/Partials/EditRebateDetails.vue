<script setup>
import Button from "@/Components/Button.vue"
import {IconPencil} from "@tabler/icons-vue"
import Dialog from "primevue/dialog";
import {ref, watch} from "vue";
import {useForm} from "@inertiajs/vue3";
import InputError from "@/Components/InputError.vue";
import InputNumber from "primevue/inputnumber";

const props = defineProps({
    rebateDetails: {
        type: Object,
        default: []
    }
})

const visible = ref(false);

const openDialog = () => {
    visible.value = true
}

const form = useForm({
    id: '',
    amount: 0,
});

watch(
    () => props.rebateDetails,
    (newRebate) => {
        if (props.rebateDetails.length > 0) {
            form.id = newRebate.map((rebate, index) => rebate.id || form.id[index] || '');
            form.amount = newRebate.map((rebate, index) => Number(rebate.amount) || form.amount[index] || 0);
        }
    },
    { immediate: true }
);

const submitForm = () => {
    form.post(route('rebate_allocate.updateRebateAllocation'), {
        onSuccess: () => {
            visible.value = false;
        }
    })
}
</script>

<template>
    <Button
        type="button"
        variant="gray-text"
        iconOnly
        pill
        size="sm"
        @click="openDialog()"
    >
        <IconPencil size="16" stroke-width="1.25" color="#667085" />
    </Button>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.crypto_wallet_information')"
        class="dialog-xs md:dialog-sm"
    >
        <form>
            <div class="flex flex-col items-center gap-2 self-stretch">
                <div class="flex items-center w-full self-stretch py-2 text-gray-950 bg-gray-100">
                    <span class="uppercase text-xs font-semibold px-2 w-full">{{ $t('public.product') }}</span>
                    <span class="uppercase text-xs font-semibold px-2 w-full">{{ $t('public.rebate') }} / Ł ($)</span>
                </div>

                <!-- symbol groups -->
                <div
                    v-for="(rebateDetail, index) in rebateDetails"
                    class="flex items-center w-full self-stretch py-2 text-gray-950"
                >
                    <div class="text-sm px-2 w-full">{{ $t(`public.${rebateDetail.symbol_group.display}`) }}</div>
                    <div class="px-2 w-full">
                        <InputNumber
                            v-model="form.amount[index]"
                            :min="0"
                            :minFractionDigits="2"
                            fluid
                            :invalid="!!form.errors[`amount.${index}`]"
                            inputClass="py-2 px-4 w-full"
                        />
                        <InputError :message="form.errors[`amount.${index}`]" />
                    </div>
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
