<?php

namespace ImportWP\Common\Plugin;

use ImportWP\Common\Exporter\Mapper\CommentMapper;
use ImportWP\Common\Exporter\Mapper\PostMapper;
use ImportWP\Common\Exporter\Mapper\TaxMapper;
use ImportWP\Common\Exporter\Mapper\UserMapper;
use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Common\Importer\Template\TemplateManager;
use ImportWP\Common\Migration\Migrations;
use ImportWP\Common\Properties\Properties;
use ImportWP\Common\UI\ViewManager;

class Menu
{
    /**
     * @var Properties
     */
    private $properties;
    /**
     * @var ViewManager
     */
    private $view_manager;
    /**
     * @var ImporterManager
     */
    private $importer_manager;
    /**
     * @var TemplateManager $template_manager
     */
    private $template_manager;

    public function __construct(Properties $properties, ViewManager $view_manager, ImporterManager $importer_manager, TemplateManager $template_manager)
    {
        $this->properties = $properties;
        $this->view_manager = $view_manager;
        $this->importer_manager = $importer_manager;
        $this->template_manager = $template_manager;

        add_action('admin_menu', array($this, 'register_tools_menu'));
        add_action('tool_box', array($this->view_manager, 'tool_box'));
        add_filter('plugin_action_links_' . $this->properties->plugin_basename, array($this, 'add_plugin_links'));
        add_filter('update_footer', [$this, 'add_footer_text_right'], 20);
        add_filter('admin_footer_text', [$this, 'add_footer_text_left']);
    }

    public function add_footer_text_left($text)
    {
        $screen = get_current_screen();
        if ($screen->id !== 'tools_page_importwp') {
            return $text;
        }

        return '<a target="_blank" href="https://www.importwp.com/support/">Contact support</a> | Add your <a target="_blank" href="http://wordpress.org/support/view/plugin-reviews/jc-importer#postform">★★★★★</a> on <a target="_blank" href="http://wordpress.org/plugins/jc-importer/">wordpress.org</a>';
    }

    public function add_footer_text_right($text)
    {
        $screen = get_current_screen();
        if ($screen->id !== 'tools_page_importwp') {
            return $text;
        }

        return '<a class="iwp-footer-link" target="_blank" href="https://translate.wordpress.org/projects/wp-plugins/jc-importer/"><span class="dashicons dashicons-translation"></span> Translate</a> | 
        <a class="iwp-footer-link" target="_blank" href="https://www.importwp.com/documentation/?utm_campaign=support%2Bdocs&utm_source=Import%2BWP%2BFree&utm_medium=footer">View Documentation</a> | 
        ImportWP v' . IWP_VERSION . (defined('IWP_PRO_VERSION') ? ' | ImportWP PRO v' . IWP_PRO_VERSION : '');
    }

    public function register_tools_menu()
    {
        $title = __('Import WP', $this->properties->plugin_domain);

        $hook_suffix = add_management_page($title, $title, 'export', $this->properties->plugin_domain, array(
            $this->view_manager,
            'plugin_page'
        ));

        add_action('load-' . $hook_suffix, array($this, 'load_assets'));
    }

    public function add_plugin_links($links)
    {
        return array_merge(
            [
                '<a href="' .
                    admin_url('tools.php?page=' . $this->properties->plugin_domain) .
                    '">' . __('Dashboard', 'jc-importer') . '</a>',
                '<a href="' .
                    admin_url('tools.php?page=' . $this->properties->plugin_domain . '&tab=settings') .
                    '">' . __('Settings', 'jc-importer') . '</a>',
            ],
            $links
        );
    }

    public function load_assets()
    {
        $asset_file = include(plugin_dir_path($this->properties->plugin_file_path) . 'dist/index.asset.php');

        wp_register_script($this->properties->plugin_domain . '-bundle', plugin_dir_url($this->properties->plugin_file_path) . 'dist/index.js', $asset_file['dependencies'], $asset_file['version'], 'all');

        $matches = false;
        preg_match('/^https?:\/\/[^\/]+(.*?)$/', admin_url('/tools.php?page=' . $this->properties->plugin_domain), $matches);
        $ajax_base = $matches[1];

        /**
         * Generate template data
         */
        $templates = $this->importer_manager->get_templates();
        $template_data = [];
        foreach ($templates as $template_id => $template) {

            $template_class = $this->template_manager->load_template($template);
            $unique_fields = $this->template_manager->get_template_unique_fields($template_class);

            $template_data[] = [
                'id' => $template_id,
                'label' => $template_class->get_name(),
                'map' => [],
                'settings' => $template_class->register_settings(),
                'options' => $template_class->register_options(),
                'unique_fields' => $unique_fields
            ];
        }

        $migrations = new Migrations();
        $is_setup = $migrations->isSetup() ? 'yes' : 'no';

        $pro_version = '';
        if (defined('IWP_PRO_VERSION') && $this->properties->is_pro) {
            $pro_version .= ' (v' . IWP_PRO_VERSION . ' PRO)';
        }

        wp_localize_script($this->properties->plugin_domain . '-bundle', 'iwp', array(
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'admin_base' => $ajax_base,
            'ajax_base' => rest_url('/' . $this->properties->rest_namespace . '/' . $this->properties->rest_version),
            'templates' => $template_data,
            'is_setup' => $is_setup,
            'plugin_url' => plugin_dir_url($this->properties->plugin_file_path),
            'version' => 'v' . $this->properties->plugin_version . $pro_version,
            'encodings' => $this->properties->encodings,
            'is_pro' => $this->properties->is_pro ? 'yes' : 'no',
            'export_fields' => $this->get_export_fields(),
            'registered_addons' => apply_filters('iwp/register_js', []),
            'global_notices' => apply_filters('iwp/frontent/notices',  [])
        ));

        wp_enqueue_script($this->properties->plugin_domain . '-bundle');

        wp_enqueue_style($this->properties->plugin_domain . '-bundle-styles', plugin_dir_url($this->properties->plugin_file_path) . 'dist/index.css', array(), $asset_file['version'], 'all');

        $this->load_help_tabs();

        do_action('iwp/enqueue_assets');
    }

    private function get_export_fields()
    {

        $fields = array();
        $comments = array();

        $post_types = get_post_types();
        foreach ($post_types as $post_type => $label) {
            $mapper = new PostMapper($post_type);
            $fields[] = array(
                'id' => $post_type,
                'label' => 'Post Type: ' . $label,
                'fields' => $mapper->get_fields()
            );

            if (post_type_supports($post_type, 'comments')) {
                $mapper = new CommentMapper($post_type);
                $comments[] = array(
                    'id' => 'ewp_comment_' . $post_type,
                    'label' => 'Comments: ' . $label,
                    'fields' => $mapper->get_fields()
                );
            }
        }

        $fields = array_merge($fields, $comments);

        $mapper = new UserMapper();
        $fields[] = array(
            'id' => 'user',
            'label' => 'Users',
            'fields' => $mapper->get_fields()
        );

        $taxonomies = get_taxonomies(array(), 'objects');
        foreach ($taxonomies as $taxonomy) {
            $mapper = new TaxMapper($taxonomy->name);
            $fields[] = array(
                'id' => 'ewp_tax_' . $taxonomy->name,
                'label' => 'Taxonomy: ' . $taxonomy->labels->name,
                'fields' => $mapper->get_fields()
            );
        }


        $fields = apply_filters('iwp/exporter/export_field_list', $fields);

        return $fields;
    }

    private function load_help_tabs()
    {
        $screen = get_current_screen();

        $screen->add_help_tab(array(
            'id'    => 'iwp_help_tab',
            'title' => __('Overview'),
            'content'   => '<p>' . __('ImportWP allows you to import any XML or CSV file into WordPress posts, pages, users, categories and tags.', 'jc-importer') . '</p>',
        ));

        $screen->add_help_tab([
            'id' => 'iwp_support_tab',
            'title' => __('Plugin Support', 'jc-importer'),
            'content' => '<p>' . __('Import WP  has the following support:', 'jc-importer') . '</p>'
                . '<p>' . sprintf(__('documentation — Online documentation can be found at %s', 'jc-importer'), '<a href="https://www.importwp.com/docs/?utm_campaign=support%2Bdocs&utm_source=Import%2BWP%2BFree&utm_medium=help%2Btab" target="_blank">https://www.importwp.com/docs/</a>') . '</p>'
                . '<p>' . sprintf(__('Support Tickets — Support requests are handled on our support system at %s', 'jc-importer'), '<a href="https://helpdesk.importwp.com/" target="_blank">https://helpdesk.importwp.com/</a>') . '</p>',
        ]);
    }
}
