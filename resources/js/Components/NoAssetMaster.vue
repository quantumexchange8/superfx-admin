<script setup>
import { NoAssetMasterIcon } from '@/Components/Icons/solid';
import { defineProps } from 'vue';

const props = defineProps({
  title: String,
  message: String
});
</script>

<template>
    <div class="flex flex-col justify-center items-center py-5 px-6 gap-5 self-stretch">
        <NoAssetMasterIcon class="w-60 h-[180px]" />
        <div class="flex flex-col items-center gap-2 self-stretch">
            <div v-if="title" class="self-stretch text-gray-950 text-center font-semibold text-sm md:text-base">{{ title }}</div>
            <div v-if="message" class="self-stretch text-gray-700 text-center text-xs md:text-sm">{{ message }}</div>
        </div>
    </div>
</template>
