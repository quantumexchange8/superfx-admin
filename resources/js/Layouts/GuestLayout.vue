<script setup>
import { IconWorld } from '@tabler/icons-vue';
import {ref} from "vue";
import {usePage} from "@inertiajs/vue3";
import {loadLanguageAsync} from "laravel-vue-i18n";
import OverlayPanel from 'primevue/overlaypanel';
import dayjs from "dayjs";

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
</script>

<template>
    <div
        style="background-image: url('/img/background-login.svg'); background-repeat: repeat-x;"
    >
        <div class="flex flex-col min-h-screen">
            <div class="flex py-3 px-5 md:px-10 justify-end items-center">
                <div
                    class="w-[60px] h-[60px] p-[17.5px] flex items-center justify-center rounded-full hover:cursor-pointer hover:bg-gray-100 text-gray-800"
                    @click="toggle"
                >
                    <IconWorld size="25" stroke-width="1.25" />
                </div>
            </div>
            <div class="flex flex-1 flex-col justify-center items-center pb-12 md:px-8 xs:gap-y-[60px]">
                <div class="w-full flex md:flex-1 justify-center">
                    <div class="w-full max-w-xs md:max-w-none md:w-[360px] flex flex-col justify-center items-center mx-5">
                        <slot />
                    </div>
                </div>
                <div class="text-center text-gray-500 text-xs mt-auto">© {{ dayjs().year() }} superfx. All rights reserved.</div>
            </div>
        </div>
    </div>

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
