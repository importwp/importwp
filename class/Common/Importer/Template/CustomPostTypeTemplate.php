<?php

namespace ImportWP\Common\Importer\Template;

class CustomPostTypeTemplate extends PostTemplate
{
    protected $name = 'Custom Post Type';

    public function register_options()
    {

        $post_types = get_post_types();

        // remove default post types that already have templates.
        $hide_post_types = array('post', 'page', 'attachment', 'revision', 'nav_menu_item', IWP_POST_TYPE);
        foreach ($hide_post_types as $hide_post_type) {
            if (isset($post_types[$hide_post_type])) {
                unset($post_types[$hide_post_type]);
            }
        }

        $output = [];
        foreach ($post_types as $value) {
            $output[] = ['value' => 'iwp_pro', 'label' => $value];
        }

        $options = array_merge([['value' => '', 'label' => __('Choose a Post Type', 'jc-importer')]], $output);

        return [
            $this->register_field(__('Post Type', 'jc-importer'), 'post_type', [
                'options' => $options
            ])
        ];
    }
}
