<script setup>
import {ref, watch} from "vue";
import Dialog from "primevue/dialog";
import Button from "@/Components/Button.vue";
import dayjs from "dayjs";
import {useForm} from "@inertiajs/vue3";

const props = defineProps({
    userDetail: Object
})

const kycVerification = ref([]);
const visible = ref(false);

watch(() => props.userDetail, () => {
    kycVerification.value = props.userDetail.kyc_verification;
})

const openDialog = () => {
    visible.value = true
}

const form = useForm({
    id: '',
})

const submitForm = () => {
    if (props.userDetail) {
        form.id = props.userDetail.id

        form.post(route('member.updateKYCStatus'), {
            onSuccess: () => {
                visible.value = false;
            }
        })
    }
}
</script>

<template>
    <div class="flex flex-col items-start px-8 pt-4 pb-6 gap-3 flex-grow self-stretch rounded-2xl bg-white shadow-toast">
        <div class="flex h-9 items-center gap-7 self-stretch">
            <div class="flex flex-grow text-gray-950 text-sm font-bold">{{ $t('public.kyc_verification') }}</div>
        </div>
        <div
            v-if="userDetail"
            class="p-3 flex gap-5 items-center self-stretch select-none cursor-pointer rounded-xl bg-gray-50 hover:bg-gray-100"
            @click="openDialog"
        >
            <img
                :src="kycVerification ? kycVerification.original_url : '/img/member/kyc_example_preview.svg'"
                class="w-12 h-9"
                alt="kyc_verification"
            />
            <div class="truncate text-gray-950 font-medium w-full">
                {{ kycVerification ? kycVerification.file_name : $t('public.image') + '.jpg' }}
            </div>
        </div>

        <!-- loading state -->
        <div
            v-else
            class="p-3 flex gap-5 items-center self-stretch rounded-xl bg-gray-50 animate-pulse"
        >
            <div class="flex items-center justify-center w-12 h-9 rounded">
                <svg class="w-12 h-9 text-gray-300" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 18">
                    <path d="M18 0H2a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2Zm-5.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm4.376 10.481A1 1 0 0 1 16 15H4a1 1 0 0 1-.895-1.447l3.5-7A1 1 0 0 1 7.468 6a.965.965 0 0 1 .9.5l2.775 4.757 1.546-1.887a1 1 0 0 1 1.618.1l2.541 4a1 1 0 0 1 .028 1.011Z"/>
                </svg>
            </div>
            <div class="flex items-center w-full">
                <div class="h-2.5 bg-gray-300 rounded-full w-48"></div>
            </div>
        </div>
        <div v-if="userDetail && kycVerification" class="flex items-center gap-3">
            <span class="text-gray-500 text-xs">{{ $t('public.uploaded') }} {{ dayjs(kycVerification.created_at).format('YYYY/MM/DD HH:mm:ss')  }}</span>
            <span class="bg-gray-500 w-1 h-1 rounded-full grow-0 shrink-0"></span>
            <span class="text-gray-500 text-xs">{{ (kycVerification.size / 1000000 ).toFixed(2) }}MB</span>
        </div>
    </div>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.kyc_verification')"
        class="dialog-xs md:dialog-lg"
    >
        <div v-if="kycVerification" class="flex justify-center">
            <img :src="kycVerification.original_url" alt="kyc_verification">
        </div>
        <div v-else>
            <img src="/img/member/kyc_example.svg" alt="kyc_verification">
        </div>

        <div class="h-[1px] bg-gray-200 absolute left-0 w-full"></div>
        <div class="flex flex-col gap-5 items-start self-stretch pt-7">
            <div class="flex flex-col items-start self-stretch gap-1">
                <span class="font-bold text-gray-950">{{ $t('public.kyc_verification_title') }}</span>
                <span class="text-sm text-gray-950">{{ $t('public.kyc_verification_desc') }}</span>
            </div>
            <Button
                type="button"
                variant="primary-flat"
                @click="submitForm"
                :disabled="userDetail.kyc_approved_at === null"
            >
                {{ $t('public.ask_to_submit_again') }}
            </Button>
        </div>
    </Dialog>
</template>
