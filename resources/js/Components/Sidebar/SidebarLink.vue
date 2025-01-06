<script setup>
import { Link } from '@inertiajs/vue3'
import { sidebarState } from '@/Composables'
import { EmptyCircleIcon } from '@/Components/Icons/outline'
import Badge from '@/Components/Badge.vue';
import { IconAlertTriangleFilled } from '@tabler/icons-vue';

const props = defineProps({
    href: {
        type: String,
        required: false,
    },
    active: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        required: true,
    },
    external: {
        type: Boolean,
        default: false,
    },
    pendingCounts: Number
})

const Tag = !props.external ? Link : 'a'
</script>

<template>
    <component
        :is="Tag"
        v-if="href"
        :href="href"
        :class="[
            'p-3 flex gap-3 items-center rounded transition-colors w-full focus:outline-none',
            {
                'text-gray-700 hover:bg-gray-100 focus:bg-gray-100':
                    !active,
                'text-primary-600 bg-primary-100':
                    active,
            },
        ]"
    >
        <div class="max-w-5 text-primary-500">
            <slot name="icon">
                <EmptyCircleIcon aria-hidden="true" class="flex-shrink-0 w-5 h-5" />
            </slot>
        </div>

        <div class="flex items-center gap-2 w-full">
            <span
                class="text-sm font-medium w-full"
                v-show="sidebarState.isOpen || sidebarState.isHovered"
            >
                {{ title }}
            </span>
            <Badge
                v-if="pendingCounts > 0 && (sidebarState.isOpen || sidebarState.isHovered)"
                class="text-xs text-white"
                :pill="true"
            >
                {{ pendingCounts }}
            </Badge>
        </div>
    </component>
    <button
        v-else
        type="button"
        :class="[
            'p-3 flex gap-3 items-center rounded transition-colors w-full focus:outline-none',
            {
                'text-gray-700 hover:bg-gray-100 focus:bg-gray-100':
                    !active,
                'text-primary-600 bg-primary-100':
                    active,
            },
        ]"
    >
        <div class="max-w-5 text-primary-500">
            <slot name="icon">
                <EmptyCircleIcon aria-hidden="true" class="flex-shrink-0 w-5 h-5" />
            </slot>
        </div>

        <span
            class="text-sm font-medium"
            v-show="sidebarState.isOpen || sidebarState.isHovered"
        >
            {{ title }}
        </span>
        <IconAlertTriangleFilled v-if="pendingCounts > 0" size="16" stroke-width="1.25" color="#FF9800" />
        <slot name="arrow" />
    </button>
</template>
