<script setup>
import {
    IconX
} from "@tabler/icons-vue";
import Calendar from 'primevue/calendar';
import Button from "@/Components/Button.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import { ref, watch } from 'vue';
import { useForm } from "@inertiajs/vue3";

const props = defineProps({
    member: Object,
});

const emit = defineEmits(['update:visible']);

const selectedDate = ref(null);

const form = useForm({
    id: props.member.id,
    date: null,
});

const clearDate = () => {
    selectedDate.value = null;
    form.date = null;
};

watch(selectedDate, (val) => {
    form.date = val;
});

const closeDialog = () => {
    emit('update:visible', false);
};

const submitForm = () => {
    form.post(route('member.verifyEmail'), {
        onSuccess: () => {
            closeDialog();
            form.reset();
            selectedDate.value = null;
        },
    });
};
</script>

<template>
    <form>
        <div class="flex flex-col gap-5 items-center self-stretch py-4 md:py-6">
            <div class="flex flex-col items-start gap-2 self-stretch">
                <InputLabel for="date" :value="$t('public.date')" :invalid="!!form.errors.date" />
                <div class="relative w-full">
                    <Calendar
                        v-model="selectedDate"
                        :manualInput="false"
                        dateFormat="yy/mm/dd"
                        showIcon
                        iconDisplay="input"
                        placeholder="yyyy/mm/dd"
                        class="w-full"
                        :invalid="!!form.errors.date"
                    />
                    <div
                        v-if="selectedDate"
                        class="absolute top-2/4 -translate-y-1/2 right-4 text-gray-400 select-none cursor-pointer bg-white"
                        @click="clearDate"
                    >
                        <IconX size="20" />
                    </div>
                </div>
                <InputError :message="form.errors.date" />
            </div>
        </div>

        <div class="flex justify-end items-center pt-6 gap-4 self-stretch">
            <Button
                variant="gray-outlined"
                class="w-full"
                :disabled="form.processing"
                @click.prevent="closeDialog"
            >
                {{ $t('public.cancel') }}
            </Button>
            <Button
                variant="primary-flat"
                class="w-full"
                :disabled="form.processing"
                @click.prevent="submitForm"
            >
                {{ $t('public.confirm') }}
            </Button>
        </div>
    </form>
</template>
