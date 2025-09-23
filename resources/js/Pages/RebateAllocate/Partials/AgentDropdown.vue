<script setup>
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import Dropdown from "primevue/dropdown";
import { ref, watch } from "vue";

const props = defineProps({
    ibs: Array,
})

const selectedib = ref(props.ibs[0]);

watch(()=>props.ibs, () => {
    selectedib.value = props.ibs[0];
})
</script>

<template>
    <Dropdown
        v-if="ibs.length > 1"
        v-model="selectedib"
        :options="ibs"
        filter
        :filterFields="['name', 'id_number', 'email']"
        optionLabel="name"
        class="w-44"
        scroll-height="236px"
    >
        <template #value="slotProps">
            <div v-if="slotProps.value" class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <div class="w-5 h-5 rounded-full overflow-hidden">
                        <template v-if="slotProps.value.profile_photo">
                            <img :src="slotProps.value.profile_photo" alt="profile_picture" />
                        </template>
                        <template v-else>
                            <DefaultProfilePhoto />
                        </template>
                    </div>
                    <div>{{ slotProps.value.name }}</div>
                </div>
            </div>
        </template>
        <template #option="slotProps">
            <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded-full overflow-hidden">
                    <template v-if="slotProps.option.profile_photo">
                        <img :src="slotProps.option.profile_photo" alt="profile_picture" />
                    </template>
                    <template v-else>
                        <DefaultProfilePhoto />
                    </template>
                </div>
                <div>{{ slotProps.option.name }}</div>
            </div>
        </template>
    </Dropdown>
    <div
        v-else
        class="flex items-center gap-3 w-full"
    >
        <div class="w-5 h-5 rounded-full overflow-hidden">
            <template v-if="ibs[0].profile_photo">
                <img :src="ibs[0].profile_photo" alt="profile_picture" />
            </template>
            <template v-else>
                <DefaultProfilePhoto />
            </template>
        </div>
        <div class="w-28 xl:w-auto truncate text-gray-950 text-sm">
            {{ ibs[0].name }}
        </div>
    </div>
</template>
