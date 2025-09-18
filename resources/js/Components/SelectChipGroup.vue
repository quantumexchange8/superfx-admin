<script setup>
import { IconCircleCheckFilled } from "@tabler/icons-vue";

const props = defineProps({
    modelValue: [String, Number, Object],
    items: {
        type: Array,
        required: true,
    },
    valueKey: {
        type: String,
        default: null,
    },
    disabledKey: {
        type: String,
        default: "disabled",
    },
});

const emit = defineEmits(["update:modelValue"]);

const selectItem = (item) => {
    if (item[props.disabledKey]) return; // prevent selection
    const value = props.valueKey ? item[props.valueKey] : item;
    emit("update:modelValue", value);
};

const isSelected = (item) => {
    const value = props.valueKey ? item[props.valueKey] : item;
    return props.modelValue === value;
};
</script>

<template>
    <div class="grid gap-3 self-stretch grid-cols-1 sm:grid-cols-2">
        <div
            v-for="item in items"
            :key="props.valueKey ? item[props.valueKey] : item"
            @click="selectItem(item)"
            class="group flex flex-col items-start py-3 px-4 gap-1 self-stretch rounded-lg border shadow-input transition-colors duration-300 select-none w-full relative"
            :class="{
                'bg-primary-50 border-primary cursor-pointer': isSelected(item) && !item[disabledKey],
                'bg-white border-surface-300 hover:bg-primary-50 hover:border-primary cursor-pointer':
                  !isSelected(item) && !item[disabledKey],
                'bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed opacity-60': item[disabledKey],
            }"
        >
            <div class="flex items-center self-stretch">
                <div
                    class="flex-grow text-xs font-semibold transition-colors duration-300"
                    :class="{
                        'text-primary-700': isSelected(item) && !item[disabledKey],
                        'text-surface-950': !isSelected(item) && !item[disabledKey],
                        'text-gray-400': item[disabledKey],
                    }"
                >
                    <slot name="option" :item="item">
                        <div class="uppercase">
                            {{ $t(`public.${props.valueKey ? item[props.valueKey] : item}`) }}
                        </div>
                    </slot>
                </div>

                <div v-if="isSelected(item) && !item[disabledKey]" class="absolute right-2">
                    <IconCircleCheckFilled size="16" stroke-width="1.5" color="#34d399" />
                </div>
            </div>
        </div>
    </div>
</template>
