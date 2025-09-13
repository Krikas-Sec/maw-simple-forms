(function () {
  const { registerBlockType } = wp.blocks;
  const { useSelect } = wp.data;
  const { InspectorControls } = wp.blockEditor || wp.editor;
  const { PanelBody, SelectControl, Spinner, Notice } = wp.components;
  const { __ } = wp.i18n;
  const { createElement: el, Fragment } = wp.element;

  registerBlockType('mawebb/maw-form', {
    title: 'MAW Form',
    icon: 'feedback',
    category: 'widgets',
    description: __('Insert a MAW form and choose which one in the panel.', 'maw-simple-forms'),
    attributes: { formId: { type: 'number', default: 0 } },

    edit: (props) => {
      const { attributes: { formId }, setAttributes } = props;

      // Fetch maw_form posts (requires show_in_rest: true)
      const forms = useSelect((select) =>
        select('core').getEntityRecords('postType', 'maw_form', { per_page: -1, _fields: ['id','title'] })
      , []);

      const isLoading = (typeof forms === 'undefined');

      const options = [{ label: __('— Select a form —', 'maw-simple-forms'), value: 0 }];
      if (Array.isArray(forms)) {
        forms.forEach((f) => options.push({
          label: (f.title && f.title.rendered) ? f.title.rendered : ('#' + f.id),
          value: f.id
        }));
      }

      return el(Fragment, null,
        el(InspectorControls, null,
          el(PanelBody, { title: __('Settings', 'maw-simple-forms'), initialOpen: true },
            isLoading
              ? el(Spinner, null)
              : el(SelectControl, {
                  label: __('Form', 'maw-simple-forms'),
                  value: formId,
                  options: options,
                  onChange: (val) => setAttributes({ formId: parseInt(val, 10) || 0 })
                })
          )
        ),
        el('div', { className: 'maw-form-block-preview', style: { border: '1px dashed #ccc', padding: '12px' } },
          el('strong', null, 'MAW Form'),
          isLoading && el('p', null, el(Spinner, null), ' ', __('Loading forms…', 'maw-simple-forms')),
          (!isLoading && !formId) && el(Notice, { status: 'info', isDismissible: false },
            __('Choose a form in the settings panel (gear icon).', 'maw-simple-forms')
          ),
          (!isLoading && formId > 0) && el('p', null,
            __('Form ID:', 'maw-simple-forms'), ' ', el('code', null, String(formId))
          )
        )
      );
    },

    save: () => null // dynamic render via PHP
  });
})();
