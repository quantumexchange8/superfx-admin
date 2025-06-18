<script setup>
import {IconCircleXFilled, IconSearch} from "@tabler/icons-vue";
import InputText from "primevue/inputtext";
import {ref, watch, watchEffect} from "vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import TogglePostPermission from "@/Pages/Member/Forum/Partials/TogglePostPermission.vue";
import {usePage} from "@inertiajs/vue3";
import Loader from "@/Components/Loader.vue";
import debounce from "lodash/debounce.js";

const search = ref('');
const selectedIBs = ref([]);
const ibs = ref([]);
const isLoading = ref(false);

const getIBs = async () => {
    isLoading.value = true;
    try {
        let url = '/member/getIBs';

        if (search.value) {
            url += `?search=${search.value}`;
        }

        const response = await axios.get(url);
        selectedIBs.value = response.data.selectedIBs;
        ibs.value = response.data.ibs;
    } catch (error) {
        console.error('Error changing ibs:', error);
    } finally {
        isLoading.value = false;
    }
};

getIBs();

const clearSearch = () => {
    search.value = '';
}

watch(search, debounce(() => {
    getIBs();
}, 300));

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getIBs();
    }
});
</script>

<template>
    <div class="pt-6 flex-col items-center self-stretch bg-white shadow-toast rounded-2xl">
        <div class="px-6 flex flex-col items-center self-stretch gap-5 pb-3">
            <span class="text-left w-full text-sm font-bold text-gray-950">{{ $t('public.manage_posting_permissions') }}</span>
            <div class="relative w-full">
                <div class="absolute top-2/4 -mt-[9px] left-4 text-gray-400">
                    <IconSearch size="20" stroke-width="1.25" />
                </div>
                <InputText
                    v-model="search"
                    :placeholder="$t('public.search_ib')"
                    class="font-normal pl-12 w-full"
                />
                <div
                    v-if="search"
                    class="absolute top-2/4 -mt-2 right-4 text-gray-300 hover:text-gray-400 select-none cursor-pointer"
                    @click="clearSearch"
                >
                    <IconCircleXFilled size="16" />
                </div>
            </div>
        </div>

        <div v-if="isLoading" class="flex items-center justify-center py-6">
            <Loader />
        </div>

        <div v-else class="flex flex-col overflow-y-auto self-stretch max-h-[47vh]">
            <!-- Selected -->
            <div v-if="selectedIBs.length > 0" class="py-3 px-6 text-gray-400 text-xs">
                {{ $t('public.selected') }}
            </div>
            <div
                v-for="selected in selectedIBs"
                class="px-6 border-b border-gray-50 last:border-0 py-2"
            >
                <div class="flex gap-3 items-center self-stretch w-full">
                    <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                        <template v-if="selected.profile_photo">
                            <img :src="selected.profile_photo" alt="profile_picture" />
                        </template>
                        <template v-else>
                            <DefaultProfilePhoto />
                        </template>
                    </div>
                    <div class="flex flex-col w-full max-w-[200px] sm:max-w-[120px] lg:max-w-[120px] 2xl:max-w-[160px]">
                        <span class="text-sm text-gray-950 font-medium">{{ selected.name }}</span>
                        <span class="text-xs text-gray-500 md:max-w-[150px] truncate">{{ selected.email }}</span>
                    </div>
                    <TogglePostPermission
                        :ib="selected"
                    />
                </div>
            </div>

            <!-- IBs -->
            <div class="py-3 px-6 text-gray-400 text-xs">
                {{ $t('public.ibs') }}
            </div>
            <div
                v-for="ib in ibs"
                class="px-6 border-b border-gray-50 last:border-0 py-2"
            >
                <div v-if="ib" class="flex gap-3 items-center self-stretch w-full">
                    <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                        <template v-if="ib.profile_photo">
                            <img :src="ib.profile_photo" alt="profile_picture" />
                        </template>
                        <template v-else>
                            <DefaultProfilePhoto />
                        </template>
                    </div>
                    <div class="flex flex-col w-full max-w-[200px] sm:max-w-[120px] lg:max-w-[120px] 2xl:max-w-[160px]">
                        <span class="text-sm text-gray-950 font-medium">{{ ib.name }}</span>
                        <span class="text-xs text-gray-500 md:max-w-[150px] truncate">{{ ib.email }}</span>
                    </div>
                    <TogglePostPermission
                        :ib="ib"
                    />
                </div>
            </div>
            <div v-if="ibs.length === 0" class="font-semibold px-6 text-gray-600 text-xs pb-3">
                {{ $t('public.no_user_header') }}
            </div>
        </div>
    </div>
</template>
