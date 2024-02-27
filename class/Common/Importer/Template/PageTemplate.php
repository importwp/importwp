<?php

namespace ImportWP\Common\Importer\Template;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\EventHandler;

class PageTemplate extends PostTemplate
{
    protected $name = 'Page';

    public function __construct(EventHandler $event_handler)
    {
        parent::__construct($event_handler);
        $this->default_template_options['post_type'] = 'page';
    }

    public function register()
    {
        $groups = parent::register();

        $templates = array('default' => __('Default Template', 'jc-importer'));
        $templates = array_merge($templates, wp_get_theme()->get_page_templates());

        $template_list = [];
        foreach ($templates as $id => $name) {
            $template_list[] = ['value' => $id, 'label' => $name];
        }

        $groups[0]['fields'][] = $this->register_field(__('Page Template', 'jc-importer'), '_wp_page_template', [
            'options' => $template_list,
            'options_default' => 'default',
            'tooltip' => __('Select a page template from your active theme', 'jc-importer')
        ]);

        return $groups;
    }

    /**
     * Process data before record is importer.
     * 
     * Alter data that is passed to the mapper.
     *
     * @param ParsedData $data
     * @return ParsedData
     */
    public function pre_process(ParsedData $data)
    {
        $this->field_map['_wp_page_template'] = 'post._wp_page_template';

        if (true !== $this->importer->isEnabledField('post._wp_page_template')) {
            $temp = $data->getData();
            unset($temp['post._wp_page_template']);
            $data->replace($temp);
        }

        return parent::pre_process($data);
    }

    /**
     * Convert fields/headings to data map
     * 
     * @param mixed $fields
     * @param ImporterModel $importer
     * @return array 
     */
    public function generate_field_map($fields, $importer)
    {
        $result = parent::generate_field_map($fields, $importer);
        $map = $result['map'];
        $enabled = $result['enabled'];

        $template_index = array_search('custom_fields._wp_page_template', $fields);
        if ($template_index !== false) {
            $enabled[] = 'post._wp_page_template';
            $map['post._wp_page_template'] = sprintf('{%d}', $template_index);
            $map['post._wp_page_template._enable_text'] = 'yes';
        }

        return [
            'map' => $map,
            'enabled' => $enabled
        ];
    }

    public function get_permission_fields($importer_model)
    {
        $permission_fields = parent::get_permission_fields($importer_model);

        $permission_fields['core']['_wp_page_template'] = __('Page Template', 'jc-importer');

        return $permission_fields;
    }
}
