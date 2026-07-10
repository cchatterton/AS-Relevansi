(function (blocks, element, blockEditor, components) {
    const el = element.createElement;
    const InspectorControls = blockEditor.InspectorControls;
    const useBlockProps = blockEditor.useBlockProps;
    const TextControl = components.TextControl;
    const TextareaControl = components.TextareaControl;
    const ToggleControl = components.ToggleControl;
    const PanelBody = components.PanelBody;

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
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Search settings' },
                        el(TextControl, { label: 'Placeholder', value: attrs.placeholder || '', onChange: set('placeholder') }),
                        el(TextControl, { label: 'Button label', value: attrs.buttonLabel || '', onChange: set('buttonLabel') }),
                        el(TextControl, { label: 'Heading', value: attrs.heading || '', onChange: set('heading') }),
                        el(TextareaControl, { label: 'Intro text', value: attrs.intro || '', onChange: set('intro') }),
                        el(TextControl, { label: 'Results URL', value: attrs.resultsUrl || '', onChange: set('resultsUrl') }),
                        el(TextControl, { label: 'Intent label', value: attrs.intentLabel || '', onChange: set('intentLabel') }),
                        el(TextControl, { label: 'CSS class', value: attrs.cssClass || '', onChange: set('cssClass') }),
                        el(ToggleControl, { label: 'Enable Search Bot for this block', checked: attrs.enableBot !== false, onChange: set('enableBot') })
                    )
                ),
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
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components);
