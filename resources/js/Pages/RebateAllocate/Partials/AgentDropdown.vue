<script setup>
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import Dropdown from "primevue/dropdown";
import { ref, watch } from "vue";

const props = defineProps({
    agents: Array,
})

const selectedAgent = ref(props.agents[0]);

watch(()=>props.agents, () => {
    selectedAgent.value = props.agents[0];
})
</script>

<template>
    <Dropdown
        v-if="agents.length > 1"
        v-model="selectedAgent"
        :options="agents"
        filter
        :filterFields="['name']"
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
            <template v-if="agents[0].profile_photo">
                <img :src="agents[0].profile_photo" alt="profile_picture" />
            </template>
            <template v-else>
                <DefaultProfilePhoto />
            </template>
        </div>
        <div class="w-28 xl:w-auto truncate text-gray-950 text-sm">
            {{ agents[0].name }}
        </div>
    </div>
</template>
