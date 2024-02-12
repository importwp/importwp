<?php

namespace ImportWP\Common\Importer\Template;

use ImportWP\EventHandler;

class TemplateManager
{
    /**
     * @var EventHandler
     */
    protected $event_handler;

    public function __construct(EventHandler $event_handler)
    {
        $this->event_handler = $event_handler;
    }

    /**
     * @param string $name 
     * @return Template 
     */
    public function load_template($name)
    {
        $template =  new $name($this->event_handler);
        return $template;
    }

    public static function get_template_unique_fields($template_class)
    {
        // Hard coded unique fields
        $unique_fields = [];
        $mapper_id = $template_class->get_mapper();
        switch ($mapper_id) {
            case 'user':
                $unique_fields = ['user_email', 'user_login'];
                break;
            case 'page':
            case 'post':
            case 'custom-post-type':
                $unique_fields = ['ID', 'post_name'];
                break;
            case 'attachment':
                $unique_fields = ['ID', 'post_name', 'src'];
                break;
            case 'term':
                $unique_fields = ['term_id', 'slug', 'name'];
                break;
            case 'comment':
                $unique_fields = ['comment_ID', '_iwp_ref_id'];
                break;
        }

        $unique_fields = apply_filters('iwp/mapper/unique_fields', $unique_fields, $mapper_id);

        return $unique_fields;
    }
}
