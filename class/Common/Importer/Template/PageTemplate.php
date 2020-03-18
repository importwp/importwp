<?php

namespace ImportWP\Common\Importer\Template;

use ImportWP\Common\Importer\ParsedData;
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

        $templates = array('default' => 'Default Template');
        $templates = array_merge($templates, wp_get_theme()->get_page_templates());

        $template_list = [];
        foreach ($templates as $id => $name) {
            $template_list[] = ['value' => $id, 'label' => $name];
        }

        $groups[0]['fields'][] = $this->register_field('Page Template', '_wp_page_template', [
            'options' => $template_list,
            'options_default' => 'default',
            'tooltip' => __('Select a page template from your active theme', 'importwp')
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
}
