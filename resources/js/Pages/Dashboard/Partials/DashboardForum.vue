<script setup>
import {ref} from "vue";
import dayjs from "dayjs";
import {wTrans} from "laravel-vue-i18n";
import Empty from "@/Components/Empty.vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import Skeleton from "primevue/skeleton";
import Image from "primevue/image";

const props = defineProps({
    postCounts: Number
})

const posts = ref([]);
const loading = ref(false);

const getResults = async () => {
    loading.value = true;

    try {
        let url = '/member/getPosts';

        const response = await axios.get(url);
        posts.value = response.data;
    } catch (error) {
        console.error('Error changing locale:', error);
    } finally {
        loading.value = false;
    }
};

getResults();

const expandedPosts = ref([]);

const toggleExpand = (index) => {
    expandedPosts.value[index] = !expandedPosts.value[index];
};

const formatPostDate = (date) => {
    const now = dayjs();
    const postDate = dayjs(date);

    if (postDate.isSame(now, 'day')) {
        return postDate.format('HH:mm');
    } else if (postDate.isSame(now.subtract(1, 'day'), 'day')) {
        return wTrans('public.yesterday');
    } else {
        return postDate.format('ddd, DD MMM');
    }
};
</script>

<template>
    <div
        v-if="postCounts === 0 && !posts.length"
        class="flex flex-col items-center justify-center self-stretch h-full"
    >
        <Empty
            :title="$t('public.no_posts_yet')"
            :message="$t('public.no_posts_yet_caption')"
        >
            <template #image>
                <img src="/img/no_data/illustration-forum.svg" alt="no data" />
            </template>
        </Empty>
    </div>

    <div
        v-else-if="loading"
        class="flex flex-col gap-1 items-center self-stretch"
    >
        <div class="flex justify-between items-start self-stretch">
            <div class="flex items-start gap-1 w-full">
                <div class="relative w-[38px] h-[38px]">
                    <div class="w-7 h-7 shrink-0 grow-0 rounded-full overflow-hidden">
                        <DefaultProfilePhoto />
                    </div>
                </div>
                <div class="flex flex-col items-start text-sm">
                    <Skeleton width="9rem" height="0.6rem" class="my-1" borderRadius="2rem"></Skeleton>
                    <Skeleton width="12rem" height="0.5rem" class="my-1" borderRadius="2rem"></Skeleton>
                </div>
            </div>
            <Skeleton width="2rem" height="0.6rem" class="my-1" borderRadius="2rem"></Skeleton>
        </div>

        <!-- content -->
        <div class="flex flex-col gap-2 items-start self-stretch pl-10">
            <Skeleton width="10rem" height="4rem"></Skeleton>
            <div class="flex flex-col gap-3 items-start self-stretch text-sm text-gray-950">
                <Skeleton width="9rem" height="0.6rem" borderRadius="2rem"></Skeleton>
                <Skeleton width="9rem" height="0.6rem" borderRadius="2rem"></Skeleton>
            </div>
        </div>
    </div>

    <div v-else class="h-[300px] overflow-y-auto">
        <div class="flex flex-col items-center gap-4 flex-1">
            <template
                v-for="post in posts"
                :key="post.id"
            >
                <div class="flex flex-col items-start gap-1 self-stretch">
                    <div class="flex justify-between items-start self-stretch">
                        <div class="flex items-start gap-3">
                            <div class="w-7 h-7 shrink-0 grow-0 rounded-full overflow-hidden">
                                <div v-if="post.profile_photo">
                                    <img :src="post.profile_photo" alt="Profile Photo" />
                                </div>
                                <div v-else>
                                    <DefaultProfilePhoto />
                                </div>
                            </div>
                            <div class="flex flex-col justify-center items-start">
                                <div class="text-gray-950 text-xs font-bold">
                                    {{ post.user.name }}
                                </div>
                                <div class="text-gray-500 text-xs">
                                    @{{ post.display_name }}
                                </div>
                            </div>
                        </div>
                        <div class="text-gray-700 text-right text-xs">
                            {{ formatPostDate(post.created_at) }}
                        </div>
                    </div>
                    <div class="pl-10 flex flex-col gap-1 items-start self-stretch">
                        <Image
                            v-if="post.post_attachment"
                            :src="post.post_attachment"
                            alt="Image"
                            image-class="w-[54px] h-[36px] object-contain"
                            preview
                        />
                        <div class="max-w-64 xl:max-w-sm self-stretch overflow-hidden text-gray-950 text-ellipsis whitespace-nowrap text-xs font-semibold">
                            {{ post.subject }}
                        </div>
                        <div
                            v-html="post.message"
                            :class="[
                            'prose prose-p:my-0 prose-ul:my-0 max-w-64 xl:max-w-[580px] prose-p:truncate text-gray-950 prose-p:text-xs',
                                {
                                     'max-h-[22px] truncate': !expandedPosts[post.id],
                                     'max-h-auto': expandedPosts[post.id],
                                }
                            ]"
                        />
                        <div
                            class="text-primary font-medium text-xs hover:text-primary-700 select-none cursor-pointer"
                            @click="toggleExpand(post.id)"
                        >
                            {{ expandedPosts[post.id] ? $t('public.see_less') : $t('public.see_more') }}
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
