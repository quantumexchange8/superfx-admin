<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: String
});
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        class="block w-full p-3 items-center self-stretch text-start text-sm leading-5 text-gray-950 hover:bg-primary-50 hover:text-primary-500 focus:outline-none focus:bg-primary-50 focus:text-primary-500 transition duration-150 ease-in-out"
    >
        <slot/>
    </Link>
    <span
        v-else
        class="block w-full p-3 items-center self-stretch text-start text-sm leading-5 text-gray-950 hover:bg-primary-50 hover:text-primary-500 focus:outline-none focus:bg-primary-50 focus:text-primary-500 transition duration-150 ease-in-out"
    >
      <slot/>
    </span>

 </template>
