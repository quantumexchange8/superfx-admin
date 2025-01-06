<script setup>
import {usePage} from "@inertiajs/vue3";
import { NoProfileIcon } from '@/Components/Icons/solid.jsx'

const profile_photo = usePage().props.auth.profile_photo;
</script>

<template>
    <div class="rounded-full w-full h-full grow-0 shrink-0 flex justify-center items-center hover:select-none hover:cursor-pointer hover:bg-gray-100">
        <div v-if="profile_photo" class="rounded-full w-full h-full">
            <img v-if="profile_photo" :src="profile_photo" alt="profile_picture" class="rounded-full w-full h-full"/>
        </div>
        <div v-else class="bg-gray-300 rounded-full w-full h-full grow-0 shrink-0 flex justify-center items-center p-1">
            <NoProfileIcon />
        </div>
    </div>
</template>
