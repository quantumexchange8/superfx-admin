<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import CreatePost from "@/Pages/Member/Forum/Partials/CreatePost.vue";
import {
    IconSearch,
    IconCircleXFilled
} from "@tabler/icons-vue";
import InputText from 'primevue/inputtext';
import {ref} from "vue";
import ForumPost from "@/Pages/Member/Forum/ForumPost.vue"
import ManagePostPermission from "@/Pages/Member/Forum/Partials/ManagePostPermission.vue";

const props = defineProps({
    postCounts: Number,
    authorName: String,
})
</script>

<template>
    <AuthenticatedLayout :title="$t('public.member_forum')">
        <div class="flex flex-col sm:flex-row items-start gap-5 self-stretch">
            <div class="flex w-full gap-5 sm:max-w-[280px] xl:max-w-[320px] flex-col items-center self-stretch">
                <!-- new post -->
                <div class="flex flex-col items-center gap-5 p-6 self-stretch bg-white shadow-toast rounded-2xl">
                    <span class="text-left w-full text-sm text-gray-400">{{ $t('public.share_your_thoughts_here') }}</span>
                    <CreatePost
                        :authorName="authorName"
                    />
                </div>

                <!-- manage posting permissions -->
                <ManagePostPermission />
            </div>

            <!-- posts -->
            <ForumPost
                :postCounts="postCounts"
            />
        </div>
    </AuthenticatedLayout>
</template>
