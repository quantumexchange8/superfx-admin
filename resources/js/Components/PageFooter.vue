<script setup>
import dayjs from "dayjs";
</script>

<template>
    <footer class="flex-shrink-0 px-6 py-4">
        <p
            class="flex items-center justify-center gap-1 text-sm text-gray-600 dark:text-gray-400"
        >
            © {{ dayjs().year() }} superfx. All rights reserved.
        </p>
    </footer>
</template>
