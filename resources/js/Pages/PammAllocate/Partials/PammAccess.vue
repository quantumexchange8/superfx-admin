<script setup>
import { ref, h, watch, computed, onMounted } from "vue";
import Button from '@/Components/Button.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputText from 'primevue/inputtext';
import { useForm, usePage } from '@inertiajs/vue3';
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import InputSwitch from "primevue/inputswitch";

const props = defineProps({
    master: Object
})

const emit = defineEmits(['update:visible'])

const closeDialog = () => {
    emit('update:visible', false);
}

const checked = ref(props.master.status === 'active')

</script>

<template>
    <div class="flex flex-col items-center gap-8 self-stretch md:pb-5">
        <span class="self-stretch text-gray-600 text-xs">{{ $t('public.pamm_access_message') }}</span>
        <div class="flex flex-col items-center gap-3 self-stretch">
            <span class="self-stretch text-gray-400 text-xs">{{ $t('public.selected') }}</span>
            <div class="flex justify-center items-center gap-3 self-stretch">
                <div class="flex items-center gap-3 flex-grow">
                    <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                        <DefaultProfilePhoto />
                    </div>
                    <div class="flex flex-col items-start flex-grow">
                        <span class="self-stretch overflow-hidden text-gray-950 text-ellipsis text-sm font-medium"></span>
                        <span class="self-stretch overflow-hidden text-gray-500 text-ellipsis text-xs"></span>
                    </div>
                </div>
                <InputSwitch
                    v-model="checked"
                />
            </div>
        </div>
        <div class="flex flex-col items-center gap-3 self-stretch">
            <span class="self-stretch text-gray-400 text-xs">{{ $t('public.admin_user') }}</span>
            <div class="flex justify-center items-center gap-3 self-stretch">
                <div class="flex items-center gap-3 flex-grow">
                    <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                        <DefaultProfilePhoto />
                    </div>
                    <div class="flex flex-col items-start flex-grow">
                        <span class="self-stretch overflow-hidden text-gray-950 text-ellipsis text-sm font-medium"></span>
                        <span class="self-stretch overflow-hidden text-gray-500 text-ellipsis text-xs"></span>
                    </div>
                </div>
                <InputSwitch
                    v-model="checked"
                />
            </div>
        </div>
    </div>
    <div class="flex justify-end items-end pt-5 self-stretch md:pt-7">
        <Button type="button" variant="primary-flat" size="base">{{ $t('public.save') }}</Button>
    </div>
</template>