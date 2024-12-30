<script setup>
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import {useForm, usePage} from "@inertiajs/vue3";
import Button from "@/Components/Button.vue"
import {ref} from "vue";
import Avatar from 'primevue/avatar';

const form = useForm({
    profile_photo: null,
    action: ''
})

const removeProfilePhoto = () => {
    selectedProfilePhoto.value = null;
    form.profile_photo = null;

    form.action = 'remove';
    form.post(route('profile.updateProfilePhoto'))
};

const selectedProfilePhoto = ref(usePage().props.auth.profile_photo);
const handleUploadProfilePhoto = (event) => {
    const profilePhotoInput = event.target;
    const file = profilePhotoInput.files[0];

    if (file) {
        // Display the selected image
        const reader = new FileReader();
        reader.onload = () => {
            selectedProfilePhoto.value = reader.result;
        };
        reader.readAsDataURL(file);
        form.profile_photo = event.target.files[0];
        form.action = 'upload';
        form.post(route('profile.updateProfilePhoto'))
    } else {
        selectedProfilePhoto.value = null;
    }
};
</script>

<template>
    <div class="p-4 md:py-6 md:px-8 flex flex-col gap-5 md:gap-8 items-center self-stretch rounded-2xl shadow-toast w-full">
        <div class="flex flex-col gap-1 items-start justify-center w-full">
            <span class="text-gray-950 font-bold">{{ $t('public.change_profile') }}</span>
            <span class="text-gray-500 text-xs">{{ $t('public.change_profile_caption') }}</span>
        </div>

        <div class="flex flex-col gap-5 md:gap-8 items-center self-stretch">
            <Avatar
                v-if="selectedProfilePhoto"
                :image="selectedProfilePhoto"
                size="xlarge"
                shape="circle"
            />
            <div v-else class="w-[100px] h-[100px] rounded-full overflow-hidden shrink-0 grow-0">
                <DefaultProfilePhoto />
            </div>

            <div class="flex items-center gap-4">
                <input
                    ref="profilePhotoInput"
                    id="kyc_verification"
                    type="file"
                    class="hidden"
                    accept="image/*"
                    @change="handleUploadProfilePhoto"
                />
                <Button
                    type="button"
                    variant="primary-flat"
                    size="sm"
                    :disabled="form.processing"
                    @click="$refs.profilePhotoInput.click()"
                >
                    {{ $t('public.upload') }}
                </Button>
                <Button
                    type="button"
                    variant="error-outlined"
                    size="sm"
                    :disabled="form.processing"
                    @click="removeProfilePhoto"
                >
                    {{ $t('public.remove') }}
                </Button>
            </div>

            <span class="text-xs text-gray-400">{{ $t('public.change_profile_help_text') }}</span>
        </div>
    </div>
</template>
