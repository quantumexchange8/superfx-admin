<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import ChangeProfilePhoto from "@/Pages/Profile/Partials/ChangeProfilePhoto.vue";

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <AuthenticatedLayout :title="$t('public.my_profile')">
        <div class="flex flex-col items-center gap-5 self-stretch">
            <div class="flex flex-col md:flex-row justify-center items-center gap-5 self-stretch">
                <ChangeProfilePhoto />
                <UpdateProfileInformationForm />
            </div>

            <div class="flex flex-col md:flex-row justify-center items-center gap-5 self-stretch">
                <UpdatePasswordForm />
                <DeleteUserForm />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
