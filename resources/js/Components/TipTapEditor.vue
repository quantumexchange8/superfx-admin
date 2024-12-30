<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'

const props = defineProps({
    modelValue: String,
})

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue,
    onUpdate: ({editor}) => {
        emit('update:modelValue', editor.getHTML())
    },
    editorProps: {
        attributes: {
            class: 'caret-primary-500 text-sm py-3 px-4 rounded-md text-gray-950 placeholder:text-gray-400 bg-surface-0 border border-surface-300 hover:border-gray-500 focus:outline-none focus:ring-0 focus:border-primary-500 appearance-none shadow-input transition-colors duration-200 min-h-24 max-h-36 overflow-y-auto prose prose-p:my-0 prose-ul:my-0',
        },
    },
    extensions: [
        StarterKit,
    ],
})
</script>

<template>
    <EditorContent :editor="editor" class="w-full" />
</template>
