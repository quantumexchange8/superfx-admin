<script setup>
import Button from "@/Components/Button.vue";
import {
    IconEdit,
    IconSearch,
    IconCircleXFilled,
    IconTrashX,
} from "@tabler/icons-vue";
import {h, ref, watchEffect} from "vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import dayjs from "dayjs";
import Image from 'primevue/image';
import {wTrans, trans} from "laravel-vue-i18n";
import Empty from "@/Components/Empty.vue";
import Skeleton from 'primevue/skeleton';
import {usePage, router} from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";

const props = defineProps({
    postCounts: Number,
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

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults();
    }
});

const confirm = useConfirm();

const requireConfirmation = (action_type, postId) => {
    const messages = {
        delete_post: {
            group: 'headless-error',
            icon: h(IconTrashX),
            header: trans('public.delete_post'),
            message: trans('public.delete_post_desc'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.delete'),
            action: () => {
                router.delete(route('member.deletePost', { id: postId }));
            }
        },
    };

    const { group, color, icon, header, message, cancelButton, acceptButton, action } = messages[action_type];

    confirm.require({
        group,
        color,
        icon,
        header,
        message,
        cancelButton,
        acceptButton,
        accept: action
    });
};

const isEnlarged = ref(false); // Track if image is enlarged
const imageStyle = ref({
  transform: 'scale(1) translate3d(0, 0, 0)', // Initial transform
  transition: 'transform 0.3s ease', // Transition applied directly
  transformOrigin: 'center center', // Initial transform origin
});

const scaleFactor = 3; // Scale factor for the image

// Toggle enlarged state of the image
const toggleEnlarged = (event) => {
  isEnlarged.value = !isEnlarged.value;

  // Prevent triggering the preview callback by stopping the event
  event.stopImmediatePropagation(); // Prevent the image preview action

  const rect = event.target.getBoundingClientRect();
  const mouseX = event.clientX - rect.left;
  const mouseY = event.clientY - rect.top;

  if (isEnlarged.value) {
    // Adjust transform-origin to focus on the clicked position
    imageStyle.value.transformOrigin = `${(mouseX / rect.width) * 100}% ${(mouseY / rect.height) * 100}%`;

    // Apply the scale transform
    imageStyle.value.transform = `scale(${scaleFactor}) translate3d(0, 0, 0)`;
  } else {
    // Reset transform when not enlarged
    imageStyle.value.transform = 'scale(1) translate3d(0, 0, 0)';
    imageStyle.value.transformOrigin = 'center center'; // Reset transform origin to center
  }
};

// Handle the mouse-follow behavior when enlarged
const followMouse = (event) => {
  if (isEnlarged.value) {
    const rect = event.currentTarget.getBoundingClientRect();
    const mouseX = event.clientX - rect.left;
    const mouseY = event.clientY - rect.top;

    // Amplify the translation values to make the movement more noticeable
    const translateX = ((mouseX / rect.width) - 0.5) * -100 / scaleFactor;
    const translateY = ((mouseY / rect.height) - 0.5) * -100 / scaleFactor;

    imageStyle.value.transform = `scale(${scaleFactor}) translate3d(${translateX}%, ${translateY}%, 0)`;
  }
};

// Reset the image transform to its original state
const resetImageTransform = () => {
  isEnlarged.value = false;
  imageStyle.value.transform = 'scale(1) translate3d(0, 0, 0)';
  imageStyle.value.transformOrigin = 'center center';
};

</script>

<template>
    <div
        v-if="postCounts === 0 && !posts.length"
        class="flex flex-col items-center justify-center self-stretch bg-white rounded-2xl shadow-toast w-full h-[65vh] md:h-[85vh] overflow-y-auto"
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
        class="flex flex-col self-stretch bg-white rounded-2xl shadow-toast w-full max-h-[85vh] overflow-y-auto"
    >
        <div
            class="border-b border-gray-200 last:border-transparent p-6 flex flex-col gap-5 items-center self-stretch"
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
            <div class="flex flex-col gap-5 items-start self-stretch pl-10">
                <Skeleton width="10rem" height="4rem"></Skeleton>
                <div class="flex flex-col gap-3 items-start self-stretch text-sm text-gray-950">
                    <Skeleton width="9rem" height="0.6rem" borderRadius="2rem"></Skeleton>
                    <Skeleton width="9rem" height="0.6rem" borderRadius="2rem"></Skeleton>
                </div>
            </div>
        </div>
    </div>

    <div v-else class="flex flex-col self-stretch bg-white rounded-2xl shadow-toast w-full max-h-[65vh] md:max-h-[85vh] overflow-y-auto">
        <div
            v-for="post in posts"
            :key="post.id"
            class="border-b border-gray-200 last:border-transparent p-6 flex flex-col gap-5 items-center self-stretch"
        >
            <div class="flex justify-between items-start self-stretch">
                <div class="flex items-start gap-1 w-full">
                    <div class="relative w-[38px] h-[38px]">
                        <div class="w-7 h-7 shrink-0 grow-0 rounded-full overflow-hidden">
                            <div v-if="post.profile_photo">
                                <img :src="post.profile_photo" alt="Profile Photo" />
                            </div>
                            <div v-else>
                                <DefaultProfilePhoto />
                            </div>
                        </div>
                        <div class="absolute bottom-0.5 right-0.5 w-5 h-5">
                            <img :src="post.display_avatar" alt="avatar" />
                        </div>
                    </div>
                    <div class="flex flex-col items-start text-sm">
                        <span class="text-gray-950 font-bold">{{ post.user.name }}</span>
                        <span class="text-gray-500">@{{ post.display_name }}</span>
                    </div>
                </div>
                <span class="text-gray-700 text-xs text-right min-w-28">{{ formatPostDate(post.created_at) }}</span>
            </div>

            <!-- content -->
            <div class="flex flex-col gap-5 items-start self-stretch pl-10">
                <Image
                    v-if="post.post_attachment"
                    :src="post.post_attachment"
                    alt="Image"
                    image-class="w-[250px] h-[160px] object-contain"
                    preview
                    :pt="{
                        toolbar: 'hidden',
                    }"
                    @click="resetImageTransform()"
                >
                <!-- Preview image template with click event -->
                    <template #preview>
                        <img
                            :src="post.post_attachment"
                            alt="Image"
                            class="max-h-full object-contain"
                            :class="[isEnlarged ? 'cursor-zoom-out' : 'cursor-zoom-in']"
                            @click="toggleEnlarged($event)"
                            @mousemove="followMouse"
                            :style="imageStyle"
                            data-pc-section="preview"
                        />
                    </template>
                </Image>

                <div class="flex flex-col gap-3 items-start self-stretch text-sm text-gray-950">
                    <span class="font-semibold">{{ post.subject }}</span>
                    <div
                        v-html="post.message"
                        :class="[
                            'prose prose-p:my-0 prose-ul:my-0 w-full',
                            {
                                 'max-h-[82px] truncate': !expandedPosts[post.id],
                                 'max-h-auto': expandedPosts[post.id],
                            }
                        ]"
                    />
                </div>
                <div
                    class="text-primary font-medium text-xs hover:text-primary-700 select-none cursor-pointer"
                    @click="toggleExpand(post.id)"
                >
                    {{ expandedPosts[post.id] ? $t('public.see_less') : $t('public.see_more') }}
                </div>
            </div>

            <div class="flex justify-between items-center self-stretch">
                <div class="flex items-center">
                    <!-- <div class="flex justify-center items-center gap-1">
                        <Button
                            type="button"
                            variant="success-text"
                            size="sm"
                            iconOnly
                            pill
                            class="hover:rotate-[-15deg]"
                            @click="handleLikesCount(post.id, 'like')"
                        >
                            <IconThumbUpFilled size="16" stroke-width="1.25" />
                        </Button>
                        <span class="min-w-10 text-gray-700 text-sm">{{ likeCounts[post.id] > 0 ? post.total_likes_count + likeCounts[post.id] : post.total_likes_count }}</span>
                    </div>
                    <div class="flex justify-center items-center gap-1">
                        <Button
                            type="button"
                            variant="error-text"
                            size="sm"
                            iconOnly
                            pill
                            class="hover:rotate-[-15deg]"
                            @click="handleLikesCount(post.id, 'dislike')"
                        >
                            <IconThumbDownFilled size="16" stroke-width="1.25" />
                        </Button>
                        <span class="min-w-10 text-gray-700 text-sm">{{ dislikeCounts[post.id] > 0 ? post.total_dislikes_count + dislikeCounts[post.id] : post.total_dislikes_count }}</span>
                    </div> -->
                </div>
                <Button
                    type="button"
                    variant="error-text"
                    size="sm"
                    iconOnly
                    pill
                    @click="requireConfirmation('delete_post', post.id);"
                >
                    <IconTrashX size="16" stroke-width="1.25" />
                </Button>
            </div>

        </div>
    </div>
</template>
