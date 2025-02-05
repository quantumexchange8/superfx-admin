<script setup>
import { sidebarState } from '@/Composables'
import {
    IconWorld,
    IconLogout,
    IconMenu2
} from '@tabler/icons-vue';
import ProfilePhoto from "@/Components/ProfilePhoto.vue";
import {Link, usePage} from "@inertiajs/vue3";
import OverlayPanel from "primevue/overlaypanel";
import {ref} from "vue";
import {loadLanguageAsync} from "laravel-vue-i18n";
import {router} from "@inertiajs/vue3";
import {useConfirm} from "primevue/useconfirm";
import {trans} from "laravel-vue-i18n";

defineProps({
    title: String
})

const op = ref();
const toggle = (event) => {
    op.value.toggle(event);
}

const currentLocale = ref(usePage().props.locale);
const locales = [
    {'label': 'English', 'value': 'en'},
    {'label': '简体中文', 'value': 'cn'},
    {'label': '繁體中文', 'value': 'tw'},
    {'label': 'tiếng Việt', 'value': 'vn'},
];

const changeLanguage = async (langVal) => {
    try {
        op.value.toggle(false)
        currentLocale.value = langVal;
        await loadLanguageAsync(langVal);
        await axios.get(`/locale/${langVal}`);
    } catch (error) {
        console.error('Error changing locale:', error);
    }
};

const confirm = useConfirm();

const requireConfirmation = (action_type) => {
    const messages = {
        logout: {
            group: 'headless-error',
            header: trans('public.logout_header'),
            text: trans('public.logout_caption'),
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.confirm'),
            action: () => {
                router.visit(route('logout'), {
                    method: 'post',
                })
            }
        },
    };

    const { group, header, text, cancelButton, acceptButton, action } = messages[action_type];

    confirm.require({
        group,
        header,
        text,
        cancelButton,
        acceptButton,
        accept: action
    });
};
</script>

<template>
    <nav
        aria-label="secondary"
        class="sticky top-0 z-10 py-2 px-3 md:px-5 bg-gray-25 flex items-center gap-3"
    >
        <div
            class="inline-flex justify-center items-center rounded-full hover:bg-gray-100 w-12 h-12 shrink-0 grow-0 hover:select-none hover:cursor-pointer"
            @click="sidebarState.isOpen = !sidebarState.isOpen"
        >
            <IconMenu2 size="20" color="#182230" stroke-width="1.25" />
        </div>
        <div
            class="text-base md:text-lg font-semibold text-gray-950 w-full"
        >
            {{ title }}
        </div>
        <div class="flex items-center">
            <div
                class="w-12 h-12 p-3.5 flex items-center justify-center rounded-full hover:cursor-pointer hover:bg-gray-100 text-gray-800"
                @click="toggle"
            >
                <IconWorld size="20" stroke-width="1.25" />
            </div>
            <!-- <Link
                class="w-12 h-12 p-3.5 flex items-center justify-center rounded-full hover:cursor-pointer hover:bg-gray-100 text-gray-800"
                :href="route('logout')"
                method="post"
                as="button"
            >
                <IconLogout size="20" stroke-width="1.25" />
            </Link> -->
            <div
                class="w-12 h-12 p-3.5 flex items-center justify-center rounded-full hover:cursor-pointer hover:bg-gray-100 text-gray-800"
                @click.prevent="requireConfirmation('logout')"
            >
                <IconLogout size="20" stroke-width="1.25" />
            </div>
            <Link
                class="w-12 h-12 p-2 items-center justify-center rounded-full hover:cursor-pointer hover:bg-gray-100 hidden md:block"
                :href="route('profile')"
            >
                <ProfilePhoto class="w-8 h-8" />
            </Link>
        </div>
    </nav>

    <OverlayPanel ref="op">
        <div class="py-2 flex flex-col items-center w-[120px]">
            <div
                v-for="locale in locales"
                class="p-3 flex items-center gap-3 self-stretch text-sm hover:bg-gray-100 hover:cursor-pointer"
                :class="{'bg-primary-50 text-primary-500': locale.value === currentLocale}"
                @click="changeLanguage(locale.value)"
            >
                {{ locale.label }}
            </div>
        </div>
    </OverlayPanel>
</template>
