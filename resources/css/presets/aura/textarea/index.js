export default {
    root: ({ context, props, parent }) => ({
        class: [
            // Font
            'leading-none caret-primary-500 text-sm',

            // Spacing
            'm-0',
            'py-3 px-4',

            // Shape
            'rounded-md',

            // Colors
            'text-gray-950',
            'placeholder:text-gray-400',
            { 'bg-surface-0': !context.disabled },
            'border',
            { 'border-surface-300': !props.invalid },

            // Invalid State
            { 'border-red-500': props.invalid },

            // States
            {
                'hover:border-gray-500': !context.disabled && !props.invalid,
                'focus:outline-none focus:ring-0 focus:border-primary-500': !context.disabled,
                'bg-gray-50 text-gray-300 placeholder:text-gray-300 select-none pointer-events-none cursor-default': context.disabled
            },

            // Filled State *for FloatLabel
            { filled: parent.instance?.$name == 'FloatLabel' && props.modelValue !== null && props.modelValue?.length !== 0 },

            // Misc
            'appearance-none',
            'shadow-input',
            'transition-colors duration-200'
        ]
    })
};
