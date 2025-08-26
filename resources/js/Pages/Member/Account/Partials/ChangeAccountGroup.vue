<script setup>
import InputLabel from "@/Components/InputLabel.vue";
import {useForm} from "@inertiajs/vue3";
import Button from "@/Components/Button.vue"
import InputError from "@/Components/InputError.vue";
import Dropdown from "primevue/dropdown";
import {ref, watch} from "vue";

const props = defineProps({
    account: Object,
})

const accountGroups = ref([]);
const emit = defineEmits(['update:visible'])

const getOptions = async () => {
    try {
        const response = await axios.get('/getAllAccountGroups');
        accountGroups.value = response.data.accountGroups;
    } catch (error) {
        console.error('Error changing locale:', error);
    }
};

getOptions();

const form = useForm({
    meta_login: props.account.meta_login,
    account_group: props.account.account_group,
})

const submitForm = () => {
    form.post(route('member.updateAccountGroup'), {
        onSuccess: () => {
            closeDialog();
        }
    });
}

const closeDialog = () => {
    emit('update:visible', false)
}
</script>

<template>
    <form>
        <div class="flex flex-col items-center gap-8 self-stretch md:gap-10">
            <div class="flex flex-col items-center gap-5 self-stretch">
                <div class="flex flex-col justify-center items-center py-4 px-8 gap-2 self-stretch bg-gray-200">
                    <span class="w-full text-gray-500 text-center text-xs font-medium">#{{ account.meta_login }} - {{ $t('public.available_account_balance') }}</span>
                    <span class="w-full text-gray-950 text-center text-xl font-semibold">$ {{ account.balance }}</span>
                </div>

                <!-- input fields -->
                <div class="flex flex-col items-start gap-1 self-stretch">
                    <InputLabel for="account_group" :value="$t('public.account_type')" />
                    <Dropdown
                        v-model="form.account_group"
                        :options="accountGroups"
                        optionLabel="name"
                        optionValue="value"
                        :placeholder="$t('public.account_type_placeholder')"
                        class="w-full"
                        scroll-height="236px"
                        :invalid="!!form.errors.account_group"
                        :disabled="!accountGroups.length"
                    />
                    <InputError :message="form.errors.account_group" />
                </div>
            </div>
        </div>
        <div class="flex justify-end items-center pt-5 gap-4 self-stretch sm:pt-7">
            <Button
                type="button"
                variant="gray-tonal"
                class="w-full md:w-[120px]"
                @click.prevent="closeDialog()"
                :disabled="form.processing"
            >
                {{ $t('public.cancel') }}
            </Button>
            <Button
                variant="primary-flat"
                class="w-full md:w-[120px]"
                @click.prevent="submitForm"
                :disabled="form.processing"
            >
                {{ $t('public.confirm') }}
            </Button>
        </div>
    </form>
</template>
