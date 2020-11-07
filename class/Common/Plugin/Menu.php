<?php

namespace ImportWP\Common\Plugin;

use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Common\Importer\Template\TemplateManager;
use ImportWP\Common\Migration\Migrations;
use ImportWP\Common\Properties\Properties;
use ImportWP\Common\UI\ViewManager;
use IWP_Debug;

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
    }

    public function register_tools_menu()
    {
        $title = __('ImportWP', $this->properties->plugin_domain);

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
                    '">' . __('Dashboard', 'importwp') . '</a>',
                '<a href="' .
                    admin_url('tools.php?page=' . $this->properties->plugin_domain . '&tab=settings') .
                    '">' . __('Settings', 'importwp') . '</a>',
            ],
            $links
        );
    }

    public function load_assets()
    {
        wp_register_script($this->properties->plugin_domain . '-bundle', plugin_dir_url($this->properties->plugin_file_path) . 'dist/js/bundle.js', array(), $this->properties->plugin_version, 'all');

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

            $template_data[] = [
                'id' => $template_id,
                'label' => $template_class->get_name(),
                'map' => [],
                'settings' => $template_class->register_settings(),
                'options' => $template_class->register_options(),
            ];
        }

        $migrations = new Migrations();
        $is_setup = $migrations->isSetup() ? 'yes' : 'no';

        wp_localize_script($this->properties->plugin_domain . '-bundle', 'wpApiSettings', array(
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'admin_base' => $ajax_base,
            'ajax_base' => rest_url('/' . $this->properties->rest_namespace . '/' . $this->properties->rest_version),
            'templates' => $template_data,
            'is_setup' => $is_setup,
            'plugin_url' => plugin_dir_url($this->properties->plugin_file_path),
            'version' => $this->properties->plugin_version,
            'encodings' => $this->properties->encodings,
            'is_pro' => $this->properties->is_pro ? 'yes' : 'no'
        ));

        wp_enqueue_script($this->properties->plugin_domain . '-bundle');
        wp_add_inline_script($this->properties->plugin_domain . '-bundle', '', 'before');

        wp_enqueue_style($this->properties->plugin_domain . '-bundle-styles', plugin_dir_url($this->properties->plugin_file_path) . 'dist/css/style.bundle.css', array(), $this->properties->plugin_version, 'all');

        $this->load_help_tabs();
    }

    private function load_help_tabs()
    {
        $screen = get_current_screen();

        $screen->add_help_tab(array(
            'id'    => 'iwp_help_tab',
            'title' => __('Overview'),
            'content'   => '<p>' . __('ImportWP allows you to import any XML or CSV file into WordPress posts, pages, users, categories and tags.', 'importwp') . '</p>',
        ));

        $screen->add_help_tab([
            'id' => 'iwp_support_tab',
            'title' => __('Plugin Support', 'importwp'),
            'content' => '<p>' . __('ImportWP  has the following support:', 'importwp') . '</p>'
                . '<p>' . __('<strong>Plugin documentation</strong> — Online documentation can be found at <a href="https://www.importwp.com/documentation/" target="_blank">https://www.importwp.com/documentation/</a>', 'importwp') . '</p>'
                . '<p>' . __('<strong>Support Tickets</strong> — Support requests are handled on our support system at <a href="https://support.jclabs.co.uk/" target="_blank">https://support.jclabs.co.uk/</a>', 'importwp') . '</p>',
        ]);
    }
}
