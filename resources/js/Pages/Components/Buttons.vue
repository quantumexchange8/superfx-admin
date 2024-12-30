<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Button from '@/Components/Button.vue'
import { EmptyCircleIcon } from "@/Components/Icons/outline.jsx";
import {Head} from "@inertiajs/vue3";

let buttonVariants = [
    'primary-flat', 'primary-tonal', 'primary-outlined', 'primary-text',
    'gray-flat', 'gray-tonal', 'gray-outlined', 'gray-text',
    'error-flat', 'error-tonal', 'error-outlined', 'error-text',
    'success-flat', 'success-tonal', 'success-outlined', 'success-text',
];

let buttonSizes = ["sm", "base", "lg"]
</script>

<template>
    <AuthenticatedLayout title="Buttons">
        <div class="grid items-center gap-6 mt-4">
            <div v-for="variant in buttonVariants" :key="variant"
                 class="grid items-start grid-cols-3 gap-4 justify-items-center">
                <Button v-for="size in buttonSizes" :key="size" :variant="variant" :size="size">
                    Button CTA
                </Button>
            </div>

            <div v-for="variant in buttonVariants" :key="variant"
                 class="grid items-start grid-cols-3 gap-4 justify-items-center">
                <Button v-for="size in buttonSizes" :key="size" :variant="variant" :size="size"
                        class="justify-center gap-3" v-slot="{ iconSizeClasses }">
                    <EmptyCircleIcon aria-hidden="true" :class="iconSizeClasses"/>
                    <span>Button</span>
                </Button>
            </div>

            <div v-for="variant in buttonVariants" :key="variant"
                 class="grid items-start grid-cols-3 gap-4 justify-items-center">
                <Button v-for="size in buttonSizes" :key="size" iconOnly :variant="variant" :size="size"
                        class="justify-center gap-3" v-slot="{ iconSizeClasses }">
                    <EmptyCircleIcon aria-hidden="true" :class="iconSizeClasses"/>
                    <span class="sr-only">Icon only button</span>
                </Button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
