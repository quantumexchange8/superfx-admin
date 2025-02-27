export default {
    root: ({ props }) => ({
        class: [
            //Font
            'text-xs font-bold',

            //Alignments
            'inline-flex items-center justify-center',

            //Spacing
            'px-2 py-1',

            //Shape
            {
                'rounded-md': !props.rounded,
                'rounded-full': props.rounded
            },

            //Colors
            {
                'text-primary-highlight-inverse bg-primary-highlight': props.severity == null || props.severity == 'primary',
                'text-green-700 bg-green-100': props.severity == 'success',
                'text-gray-700 bg-gray-100': props.severity == 'secondary',
                'text-blue-700 bg-blue-100': props.severity == 'info',
                'text-orange-700 bg-orange-100': props.severity == 'warning',
                'text-red-700 bg-red-100': props.severity == 'danger',
                'text-white bg-black': props.severity == 'contrast'
            }
        ]
    }),
    value: {
        class: 'leading-normal'
    },
    icon: {
        class: 'mr-1 text-sm'
    }
};
