(function (blocks, element, blockEditor) {
    const el = element.createElement;
    const useBlockProps = blockEditor.useBlockProps;

    blocks.registerBlockType('wp7rss/search-block', {
        edit: function (props) {
            const attrs = props.attributes;
            const blockProps = useBlockProps({ className: 'wp7rss-search-block' });
            const set = function (key) {
                return function (value) {
                    const next = {};
                    next[key] = value;
                    props.setAttributes(next);
                };
            };

            if (!attrs.blockInstanceId) {
                props.setAttributes({ blockInstanceId: 'wp7rss-' + Math.random().toString(36).slice(2, 10) });
            }

            return el(
                'div',
                blockProps,
                attrs.heading ? el('h2', {}, attrs.heading) : null,
                attrs.intro ? el('p', {}, attrs.intro) : null,
                el('div', { className: 'wp7rss-search-block__form-preview' },
                    el('input', { type: 'search', placeholder: attrs.placeholder || 'Search this site...', disabled: true }),
                    el('button', { type: 'button' }, attrs.buttonLabel || 'Search')
                )
            );
        },
        save: function () {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor);
