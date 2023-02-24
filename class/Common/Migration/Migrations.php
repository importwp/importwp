<?php

namespace ImportWP\Common\Migration;

use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Common\Util\Logger;
use ImportWP\Container;

class Migrations
{
    private $_version = 0;
    private $_migrations = array();

    public function __construct()
    {

        $starting_version = intval(get_site_option('iwp_db_version', 0));
        if ($starting_version <= 3 && $starting_version > 0) {
            // v1 migrations
            $this->_migrations[] = array($this, 'migration_01');
            $this->_migrations[] = array($this, 'migration_02');
            $this->_migrations[] = array($this, 'migration_03');
            $this->_migrations[] = array($this, 'migration_04_migrate_v1_to_v2_data');
        } else {
            // skip v1 migrations
            $this->_migrations[] = null;
            $this->_migrations[] = null;
            $this->_migrations[] = null;
            $this->_migrations[] = null;
        }

        // v2 migrations
        $this->_migrations[] = array($this, 'migration_05_multiple_crons');
        $this->_migrations[] = array($this, 'migration_06_cron_update');
        $this->_migrations[] = array($this, 'migration_07_add_session_table');
        $this->_migrations[] = array($this, 'migration_08_migrate_taxonomy_settings');
        $this->_migrations[] = array($this, 'migration_09_migrate_attachment_settings');

        $this->_version = count($this->_migrations);
    }

    public function isSetup()
    {
        $version = intval(get_site_option('iwp_db_version', get_site_option('jci_db_version', 0)));
        if ($version < count($this->_migrations)) {
            return false;
        }
        return true;
    }

    public function install()
    {

        //run through schema migrations only
        $this->migrate(false);
    }

    public function uninstall()
    {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS `" . $wpdb->prefix . "importer_log`;");
        $wpdb->query("DROP TABLE IF EXISTS `" . $wpdb->prefix . "importer_files`;");
        delete_site_option('iwp_db_version');
        delete_site_option('jci_db_version');
        delete_site_option('iwp_is_migrating');
    }

    public function migrate($migrate_data = true)
    {

        $verion_key = 'iwp_db_version';
        $version = intval(get_site_option('iwp_db_version', get_site_option('jci_db_version', 0)));
        $migrating = get_site_option('iwp_is_migrating', 'no');
        if ('yes' === $migrating) {
            return;
        }

        if ($version < count($this->_migrations)) {

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            for ($i = 0; $i < count($this->_migrations); $i++) {

                $migration_version = $i + 1;
                if ($version < $migration_version) {

                    update_site_option('iwp_is_migrating', 'yes');

                    set_time_limit(0);

                    // Run migration
                    if (!is_null($this->_migrations[$i])) {
                        call_user_func($this->_migrations[$i], $migrate_data);
                    }

                    // Flag as migrated
                    update_site_option($verion_key, $migration_version);
                    update_site_option('iwp_is_migrating', 'no');
                }
            }
        }

        // update_site_option('iwp_is_setup', 'yes');
    }

    public function get_charset()
    {

        global $wpdb;
        $charset_collate = "";

        if (!empty($wpdb->charset)) {
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        }
        if (!empty($wpdb->collate)) {
            $charset_collate .= " COLLATE $wpdb->collate";
        }
        return $charset_collate;
    }

    public function migration_01($migrate_data = true)
    {

        global $wpdb;
        $charset_collate = $this->get_charset();

        $sql = "CREATE TABLE `" . $wpdb->prefix . "importer_log` (
					  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					  `importer_name` varchar(255) DEFAULT NULL,
					  `object_id` int(11) DEFAULT NULL,
					  `template` varchar(255) DEFAULT NULL,
					  `type` varchar(255) DEFAULT NULL,
					  `file` varchar(255) DEFAULT NULL,
					  `version` int(11) DEFAULT NULL,
					  `row` int(11) DEFAULT NULL,
					  `src` text,
					  `value` text,
					  `created` datetime DEFAULT NULL,
					  `import_settings` TEXT NULL,
					  `mapped_fields` TEXT NULL,
					  `attachments` TEXT NULL,
					  `taxonomies` TEXT NULL,
					  `parser_settings` TEXT NULL,
					  `template_settings` TEXT NULL,
					  PRIMARY KEY (`id`)
					) $charset_collate; ";

        dbDelta($sql);

        $sql = "CREATE TABLE `" . $wpdb->prefix . "importer_files`(  
					  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					  `importer_id` INT(11),
					  `author_id` INT(11),
					  `mime_type` VARCHAR(255),
					  `name` VARCHAR(255),
					  `src` VARCHAR(255),
					  `created` DATETIME,
					  PRIMARY KEY (`id`)
					) $charset_collate;";
        dbDelta($sql);
    }

    public function migration_02($migrate_data = true)
    {

        if (!$migrate_data) {
            return;
        }

        global $wpdb;

        // return list of importer file links
        $importer_file_ids = $this->migration_02_get_importer_file_ids();

        // get ids of all importers and fetch all their import files
        $importers = $wpdb->get_col("SELECT id FROM " . $wpdb->posts . " WHERE post_type = 'jc-imports'");

        //
        $or_query = '';
        if (!empty($importers)) {
            $or_query = "
				OR (
					post_type = 'attachment'
					AND post_parent IN ( " . implode(',', $importers) . ")
				)
			";
        }

        $results = $wpdb->get_results("
			SELECT ID, guid, post_parent, post_author, post_mime_type, post_name, post_date 
			FROM " . $wpdb->posts . " 
			WHERE 
				(post_type = 'jc-import-files')
				" . $or_query . "
		");


        if (!empty($results)) {

            // print_r($importer_attachments);
            $upload_dir = wp_upload_dir();
            $baseurl    = $upload_dir['baseurl'];
            $records    = array();

            foreach ($results as $importer) {

                $src = $importer->guid;
                if (strpos($src, $baseurl) === 0) {
                    $src = substr($src, strlen($baseurl));
                }
                // $record = array(
                $importer_id    = $importer->post_parent;
                $author_id      = $importer->post_author;
                $mime           = $importer->post_mime_type;
                $name           = $importer->post_name;
                $attachment_src = $src;
                $created        = $importer->post_date;
                // );

                $query_result = $wpdb->query($wpdb->prepare("INSERT INTO `" . $wpdb->prefix . "importer_files`(importer_id, author_id, mime_type, name, src, created) VALUES(%d, %d, %s, %s, %s, %s)", $importer_id, $author_id, $mime, $name, $attachment_src, $created));

                // check if importer_file_id exists in importer settings array
                if (is_array($importer_file_ids) && array_key_exists($importer->ID, $importer_file_ids)) {
                    $importer_file_ids[$importer->ID] = $wpdb->insert_id;
                    set_transient('jci_db_import_file_ids', $importer_file_ids);
                }

                if ($query_result) {
                    wp_delete_post($importer->ID, true);
                }
            }
        }

        // loop through all importer_file meta data
        $importer_settings = $wpdb->get_results("SELECT * FROM `" . $wpdb->prefix . "postmeta` WHERE meta_key='_import_settings'");
        if ($importer_settings) {

            foreach ($importer_settings as $settings) {

                $post_id = $settings->post_id;
                $value   = maybe_unserialize($settings->meta_value);

                if (is_array($importer_file_ids) && array_key_exists($value['import_file'], $importer_file_ids)) {
                    $value['import_file'] = $importer_file_ids[$value['import_file']];
                    update_post_meta($post_id, '_import_settings', $value);
                    continue;
                }
            }
        }
    }

    /**
     * Fetch list of current importer_file id's
     * @return array
     */
    private function migration_02_get_importer_file_ids()
    {
        global $wpdb;

        $transient = get_transient('jci_db_import_file_ids');
        if (get_transient('jci_db_import_file_ids') === false) {

            $importer_files = array();

            $importer_settings = $wpdb->get_results("SELECT * FROM `" . $wpdb->prefix . "postmeta` WHERE meta_key='_import_settings'");
            if ($importer_settings) {

                foreach ($importer_settings as $settings) {
                    $value                                   = maybe_unserialize($settings->meta_value);
                    $importer_files[$value['import_file']] = null;
                }
            }
            set_transient('jci_db_import_file_ids', $importer_files);

            return $importer_files;
        } else {
            return $transient;
        }
    }

    /**
     * Migration 03
     * Refactor logs table, so duplication of data.
     *
     * @since 1.1.0
     */
    public function migration_03($migrate_data = true)
    {

        global $wpdb;
        $wpdb->query("ALTER TABLE `" . $wpdb->prefix . "importer_log` 
		DROP COLUMN importer_name, 
		DROP COLUMN src, 
		DROP COLUMN template, 
		DROP COLUMN type, 
		DROP COLUMN import_settings,
		DROP COLUMN mapped_fields,
		DROP COLUMN attachments,
		DROP COLUMN taxonomies,
		DROP COLUMN parser_settings,
		DROP COLUMN template_settings");
    }

    public function migration_04_migrate_v1_to_v2_data($migrate_data = true)
    {
        global $wpdb;
        // remove log table
        // $wpdb->query("DROP TABLE `" . $wpdb->prefix . "importer_log`");

        $query = new \WP_Query([
            'post_type' => 'jc-imports',
            'posts_per_page' => -1
        ]);

        /**
         * @var ImporterManager $importer_manager
         */
        $importer_manager = Container::getInstance()->get('importer_manager');

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $v2_id = get_post_meta($post->ID, '_iwp_v2_importer', true);
                $import_settings = get_post_meta($post->ID, '_import_settings', true);
                $mapped_fields = get_post_meta($post->ID, '_mapped_fields', true);
                $attachments = get_post_meta($post->ID, '_attachments', true);
                $taxonomies = get_post_meta($post->ID, '_taxonomies', true);
                $parser_settings = get_post_meta($post->ID, '_parser_settings', true);
                $field_permissions = get_post_meta($post->ID, 'field_permissions', true);

                $import_file_id = $import_settings['import_file'];


                $file_result = $wpdb->get_var($wpdb->prepare("SELECT src FROM {$wpdb->prefix}importer_files WHERE id=%d", [$import_file_id]));
                $filepath = false;
                if ($file_result) {
                    $wp_upload_dir = wp_upload_dir();
                    $filepath = $wp_upload_dir['basedir'] . $file_result;
                }

                $post_type = '';
                $template = $import_settings['template'];
                if ($template === 'taxonomy') {
                    // Skip taxonomy template as this is now term (same but without the taxonomy column)
                    continue;
                }

                if ($template === 'page') {
                    $post_type = 'page';
                } elseif ($template === 'post') {
                    $post_type = 'post';
                } elseif ($template === 'custom-post-type') {
                    $post_type = $import_settings['general']['custom_post_type'];
                }

                $settings = [
                    'post_type' => $post_type,
                    'max_row' => intval($import_settings['row_count']) > 0 ? intval($import_settings['row_count']) : '',
                    'start_row' => intval($import_settings['start_line']) > 1 ? intval($import_settings['start_line']) : '',
                ];

                $converted_mapped_fields = [];
                $enabled = [];
                foreach ($mapped_fields as $group => $field_data) {
                    foreach ($field_data as $k => $v) {

                        $group_id = $group;
                        if ($group === 'page') {
                            $group_id = 'post';
                        }

                        switch ($k) {

                                // TODO: Migrate user fields
                            case 'generate_pass':
                                $settings['generate_pass'] = boolval($v) === true ? true : false;
                                break;
                            case 'notify_reg':
                                $settings['notify_users'] = boolval($v) === true ? true : false;
                                break;
                            case 'enable_user_nicename':
                                $enabled[$group_id . '.user_nicename'] = boolval($v) === true ? true : false;
                                break;
                            case 'enable_display_name':
                                $enabled[$group_id . '.display_name'] = boolval($v) === true ? true : false;
                                break;
                            case 'enable_description':
                                $enabled[$group_id . '.description'] = boolval($v) === true ? true : false;
                                break;
                            case 'enable_role':
                                $enabled[$group_id . '.role'] = boolval($v) === true ? true : false;
                                break;
                            case 'enable_pass':
                                $enabled[$group_id . '.user_pass'] = boolval($v) === true ? true : false;
                                break;
                            case 'user_url':
                                if (!empty($v)) {
                                    $enabled[$group_id . '.user_url'] = true;
                                    $converted_mapped_fields[$group_id . '.' . $k] = $v;
                                }
                                break;

                                // post_type
                            case 'post_author':
                                $converted_mapped_fields[$group_id . '._author.post_author'] = $v;
                                break;
                            case 'post_author_field_type':
                                $converted_mapped_fields[$group_id . '._author._author_type'] = $v;
                                break;
                            case 'post_parent':
                                $converted_mapped_fields[$group_id . '._parent.parent'] = $v;
                                break;
                            case 'post_parent_field_type':
                                $converted_mapped_fields[$group_id . '._parent._parent_type'] = $v;
                                break;
                            case 'post_parent_ref':
                                $converted_mapped_fields[$group_id . '._parent._parent_ref'] = $v;
                                break;
                            case 'post_excerpt':
                                $converted_mapped_fields[$group_id . '.' . $k] = $v;
                                if (!empty($v)) {
                                    $enabled[$group_id . '.post_excerpt'] = true;
                                }
                                break;
                            case 'post_name':
                                $converted_mapped_fields[$group_id . '.' . $k] = $v;
                                if (!empty($v)) {
                                    $enabled[$group_id . '.post_name'] = true;
                                }
                                break;
                                // Enable Fields
                            case 'enable_post_parent':
                                $enabled[$group_id . '._parent'] = boolval($v) === true ? true : false;
                                break;
                            case 'enable_post_status':
                                $enabled[$group_id . '.post_status'] = boolval($v) === true ? true : false;
                                break;
                            case 'enable_post_author':
                                $enabled[$group_id . '._author'] = boolval($v) === true ? true : false;
                                break;
                            case 'enable_menu_order':
                                $enabled[$group_id . '.menu_order'] = boolval($v) === true ? true : false;
                                break;
                            case 'enable_post_password':
                                $enabled[$group_id . '.post_password'] = boolval($v) === true ? true : false;
                                break;
                            case 'enable_post_date':
                                $enabled[$group_id . '.post_date'] = boolval($v) === true ? true : false;
                                break;
                            case 'enable_comment_status':
                                $enabled[$group_id . '.comment_status'] = boolval($v) === true ? true : false;
                                break;
                            case 'enable_ping_status':
                                $enabled[$group_id . '.ping_status'] = boolval($v) === true ? true : false;
                                break;
                            case 'enable_page_template':
                                $enabled[$group_id . '._wp_page_template'] = boolval($v) === true ? true : false;
                                break;
                            default:
                                $converted_mapped_fields[$group_id . '.' . $k] = $v;
                                break;
                        }
                    }
                }

                if ($taxonomies) {
                    foreach ($taxonomies as $group => $field_data) {
                        foreach ($field_data['tax'] as $row_id => $tax) {

                            $term = $field_data['term'][$row_id];

                            // TODO: what do we do with permissions
                            $permissions = $field_data['permissions'][$row_id];

                            $converted_mapped_fields['taxonomies.' . $row_id . '.tax'] = $tax;
                            $converted_mapped_fields['taxonomies.' . $row_id . '.term'] = $term;
                        }
                    }

                    $converted_mapped_fields['taxonomies._index'] = count($taxonomies);
                }

                if ($attachments) {
                    foreach ($attachments as $group => $field_data) {

                        // attachments used to be gloablly set ftp|remote|local
                        $type = $field_data['type'];
                        $ftp_server = $field_data['ftp']['server'];
                        $ftp_user = $field_data['ftp']['user'];
                        $ftp_pass = $field_data['ftp']['pass'];
                        $local_base_path = $field_data['local']['base_path'];

                        if ($type === 'url') {
                            $type = 'remote';
                        }

                        foreach ($field_data['location'] as $row_id => $location) {
                            $alt = $field_data['alt'][$row_id];
                            $title = $field_data['title'][$row_id];
                            $caption = $field_data['caption'][$row_id];
                            $description = $field_data['description'][$row_id];
                            $permissions = $field_data['permissions'][$row_id];
                            $featured_image = $field_data['featured_image'][$row_id];

                            $converted_mapped_fields['attachments.' . $row_id . '.location'] = $location;
                            $converted_mapped_fields['attachments.' . $row_id . '._featured'] = $featured_image == 1 ? 'yes' : 'no';
                            $converted_mapped_fields['attachments.' . $row_id . '._download'] = $type;
                            $converted_mapped_fields['attachments.' . $row_id . '._ftp_host'] = $ftp_server;
                            $converted_mapped_fields['attachments.' . $row_id . '._ftp_user'] = $ftp_user;
                            $converted_mapped_fields['attachments.' . $row_id . '._ftp_pass'] = $ftp_pass;
                            $converted_mapped_fields['attachments.' . $row_id . '._ftp_path'] = '';
                            $converted_mapped_fields['attachments.' . $row_id . '._remote_url'] = '';
                            $converted_mapped_fields['attachments.' . $row_id . '._local_url'] = $local_base_path;

                            $converted_mapped_fields['attachments.' . $row_id . '._meta._enabled'] = 'yes';
                            $converted_mapped_fields['attachments.' . $row_id . '._meta._alt'] = $alt;
                            $converted_mapped_fields['attachments.' . $row_id . '._meta._title'] = $title;
                            $converted_mapped_fields['attachments.' . $row_id . '._meta._caption'] = $caption;
                            $converted_mapped_fields['attachments.' . $row_id . '._meta._description'] = $description;
                        }
                    }

                    $converted_mapped_fields['attachments._index'] = count($attachments);
                }

                // custom fields
                $custom_fields = isset($import_settings['_custom_fields'], $import_settings['_custom_fields'][$template]) ? $import_settings['_custom_fields'][$template] :  [];
                if (!empty($custom_fields)) {
                    $row_id = 0;
                    foreach ($custom_fields as $custom_field) {

                        $cf_prefix = 'custom_fields.' . $row_id . '.';
                        $converted_mapped_fields[$cf_prefix . 'key'] = $custom_field['key'];
                        $converted_mapped_fields[$cf_prefix . 'value'] = $custom_field['value'];
                        $converted_mapped_fields[$cf_prefix . '_field_type'] = $custom_field['type'];

                        if ('attachment' === $custom_field['type']) {
                            $converted_mapped_fields[$cf_prefix . '_return'] = $custom_field['settings']['attachment_return'];

                            $converted_mapped_fields[$cf_prefix . '_ftp_host'] = $custom_field['settings']['attachment_ftp_server'];
                            $converted_mapped_fields[$cf_prefix . '_ftp_user'] = $custom_field['settings']['attachment_ftp_user'];
                            $converted_mapped_fields[$cf_prefix . '_ftp_pass'] = $custom_field['settings']['attachment_ftp_pass'];


                            $converted_mapped_fields[$cf_prefix . '_ftp_path'] = '';
                            $converted_mapped_fields[$cf_prefix . '_remote_url'] = '';
                            $converted_mapped_fields[$cf_prefix . '_local_url'] = '';

                            switch ($custom_field['settings']['attachment_download']) {
                                case 'ftp':
                                    $converted_mapped_fields[$cf_prefix . '_download'] = 'ftp';
                                    $converted_mapped_fields[$cf_prefix . '_ftp_path'] = $custom_field['settings']['attachment_base_url'];
                                    break;
                                case 'url':
                                    $converted_mapped_fields[$cf_prefix . '_download'] = 'remote';
                                    $converted_mapped_fields[$cf_prefix . '_remote_url'] = $custom_field['settings']['attachment_base_url'];
                                    break;
                                case 'local':
                                    $converted_mapped_fields[$cf_prefix . '_download'] = 'local';
                                    $converted_mapped_fields[$cf_prefix . '_local_url'] = $custom_field['settings']['attachment_base_url'];
                                    break;
                            }

                            $converted_mapped_fields[$cf_prefix . '_enabled'] = 'no';
                            $converted_mapped_fields[$cf_prefix . '_alt'] = '';
                            $converted_mapped_fields[$cf_prefix . '_title'] = '';
                            $converted_mapped_fields[$cf_prefix . '_caption'] = '';
                            $converted_mapped_fields[$cf_prefix . '_description'] = '';
                        }
                        $row_id++;
                    }

                    $converted_mapped_fields['custom_fields._index'] = $row_id;
                }

                // TODO: Migrate cron
                // _jci_cron_enabled: yes
                // _jci_cron_minutes: 60
                // _jci_cron_last_ran: 1579960020
                // _cron_last_updated: 1579960020

                $cron_enabled = get_post_meta($post->ID, '_jci_cron_enabled', true);
                if ($cron_enabled === 'yes') {
                    $settings['import_method'] = 'schedule';
                    $settings['cron_day'] = 0;
                    $settings['cron_hour'] = 0;
                    $settings['cron_minute'] = 0;

                    $minutes = intval(get_post_meta($post->ID, '_jci_cron_minutes', true));
                    if ($minutes < 60) {
                        // Hourly
                        $settings['cron_schedule'] = 'hour';
                    } elseif ($minutes < 60 * 24) {
                        $settings['cron_schedule'] = 'day';
                    } elseif ($minutes < 60 * 24 * 7) {
                        $settings['cron_schedule'] = 'week';
                    } else {
                        $settings['cron_schedule'] = 'month';
                    }
                } else {
                    $settings['import_method'] = 'run';
                }



                $importer_model_data = [
                    'name' => $post->post_title,
                    'template' => $import_settings['template'],
                    'template_type' => '',
                    'parser' => $import_settings['template_type'],
                    'permissions' => [
                        'create' => [
                            'enabled' => $import_settings['permissions']['create'] === 1 ? true : false,
                            'type' => $field_permissions['create_type'],
                            'fields' => $field_permissions['create_fields']
                        ],
                        'update' => [
                            'enabled' => $import_settings['permissions']['update'] === 1 ? true : false,
                            'type' => $field_permissions['update_type'],
                            'fields' => $field_permissions['update_fields']
                        ],
                        'remove' => [
                            'enabled' => $import_settings['permissions']['delete'] === 1 ? true : false
                        ],
                    ],
                    'datasource' => [
                        'type' => $import_settings['import_type'],
                        'settings' => [
                            'remoute_url' => '',
                            'local_url' => ''
                        ]
                    ],
                    'settings' => $settings,
                    'file' => [
                        'id' => null,
                        'settings' => [
                            'count' => 0,
                            'processed' => false,
                            'setup' => false,
                            // csv
                            'delimiter' => stripslashes($parser_settings['csv_delimiter']),
                            'enclosure' => stripslashes($parser_settings['csv_enclosure']),
                            'show_headings' => false,
                            // xml
                            'base_path' => $parser_settings['import_base'],
                            'nodes' => [],
                        ]
                    ],
                    'map' => $converted_mapped_fields,
                    'enabled' => $enabled
                ];

                if (intval($v2_id) > 0) {
                    $importer_model_data['id'] = intval($v2_id);
                }

                $importer_model = new ImporterModel($importer_model_data);
                $result = $importer_model->save();

                if (!is_wp_error($result)) {

                    // clear existing importer files
                    $query = "DELETE FROM {$wpdb->postmeta} WHERE post_id={$result} AND meta_key LIKE '_importer_file%'";
                    $wpdb->query($query);

                    if ($filepath && file_exists($filepath)) {
                        $file_id = $importer_manager->link_importer_file($result, $filepath);
                        $importer_model->setFileId($file_id);
                        $importer_model->save();
                    }

                    update_post_meta($result, '_iwp_v1_importer', $post->ID);
                    update_post_meta($post->ID, '_iwp_v2_importer', $result);
                }
            }
        }
    }

    public function migration_05_multiple_crons()
    {

        /**
         * @var \wpdb $wpdb
         */
        global $wpdb;

        // TODO: loop through serialsed post_content, switching from single cron to array
        $importers = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_type='" . IWP_POST_TYPE . "'", ARRAY_A);
        foreach ($importers as $importer) {
            $id = $importer['ID'];
            $data = unserialize($importer['post_content']);
            if ($data['settings']['import_method'] !== 'schedule') {
                continue;
            }

            $cron = [[
                'setting_cron_schedule' => $data['settings']['cron_schedule'],
                'setting_cron_day' => $data['settings']['cron_day'],
                'setting_cron_hour' => $data['settings']['cron_hour'],
                'setting_cron_minute' => $data['settings']['cron_minute'],
                'setting_cron_disabled' => $data['settings']['cron_disabled'],
            ]];

            unset($data['settings']['cron_schedule']);
            unset($data['settings']['cron_day']);
            unset($data['settings']['cron_hour']);
            unset($data['settings']['cron_minute']);
            unset($data['settings']['cron_disabled']);

            $data['settings']['cron'] = $cron;

            remove_filter('content_save_pre', 'wp_filter_post_kses');
            wp_update_post(['ID' => $id, 'post_content' => serialize($data)]);
            add_filter('content_save_pre', 'wp_filter_post_kses');
        }
    }

    public function migration_06_cron_update()
    {
        wp_unschedule_hook('iwp_runner');

        /**
         * @var \wpdb $wpdb
         */
        global $wpdb;

        // TODO: loop through serialsed post_content, switching from single cron to array
        $importers = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_type='" . IWP_POST_TYPE . "'", ARRAY_A);
        foreach ($importers as $importer) {

            $id = $importer['ID'];
            $data = unserialize($importer['post_content']);
            if ($data['settings']['import_method'] !== 'schedule') {
                continue;
            }

            delete_post_meta($id, '_iwp_session');
            delete_post_meta($id, '_iwp_cron_updated');
            delete_post_meta($id, '_iwp_cron_status');
            delete_post_meta($id, '_iwp_cron_version');
            wp_update_post(['ID' => $id, 'post_excerpt' => '']);

            Logger::write(__CLASS__ . '::migration_06_cron_update -rest', $id);
        }
    }

    public function migration_07_add_session_table($migrate_data = true)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;
        $charset_collate = $this->get_charset();

        $sql = "CREATE TABLE `" . $wpdb->prefix . "iwp_sessions` (
					  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                      `site_id` int(11) DEFAULT NULL,
					  `importer_id` int(11) DEFAULT NULL,
					  `item_id` int(11) DEFAULT NULL,
					  `item_type` varchar(255) DEFAULT NULL,
					  `session` varchar(255) DEFAULT NULL,
					  PRIMARY KEY (`id`)
					) $charset_collate; ";

        dbDelta($sql);

        if (!$migrate_data) {
            return;
        }

        // Migrate post sessions
        $posts = $wpdb->get_results("SELECT {$wpdb->postmeta}.*, {$wpdb->posts}.post_type FROM {$wpdb->postmeta} INNER JOIN {$wpdb->posts} ON {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID WHERE meta_key LIKE '\_iwp\_session\_%'", ARRAY_A);
        if (!empty($posts)) {
            foreach ($posts as $post) {

                if (preg_match('/_iwp_session_(\d+)/', $post['meta_key'], $matches) !== 1) {
                    continue;
                }

                $data = [
                    'importer_id' => $matches[1],
                    'item_id' => $post['post_id'],
                    'item_type' =>  'pt-' . $post['post_type'],
                    'session' => $post['meta_value']
                ];
                $format = ['%d', '%d', '%s', '%s'];

                if (is_multisite()) {
                    $data['site_id'] = $wpdb->siteid;
                    $format[] = '%d';
                }

                $wpdb->insert($wpdb->prefix . 'iwp_sessions', $data, $format);
            }
        }

        // Migrate term sessions
        $terms = $wpdb->get_results("SELECT {$wpdb->termmeta}.*, {$wpdb->term_taxonomy}.taxonomy FROM {$wpdb->termmeta}  INNER JOIN {$wpdb->term_taxonomy} ON {$wpdb->termmeta}.term_id = {$wpdb->term_taxonomy}.term_id WHERE meta_key LIKE '\_iwp\_session\_%'", ARRAY_A);
        if (!empty($terms)) {
            foreach ($terms as $post) {

                if (preg_match('/_iwp_session_(\d+)/', $post['meta_key'], $matches) !== 1) {
                    continue;
                }

                $data = [
                    'importer_id' => $matches[1],
                    'item_id' => $post['term_id'],
                    'item_type' => 't-' . $post['taxonomy'],
                    'session' => $post['meta_value']
                ];
                $format = ['%d', '%d', '%s', '%s'];

                if (is_multisite()) {
                    $data['site_id'] = $wpdb->siteid;
                    $format[] = '%d';
                }

                $wpdb->insert($wpdb->prefix . 'iwp_sessions', $data, $format);
            }
        }

        // Migrate user sessions
        $users = $wpdb->get_results("SELECT * FROM {$wpdb->usermeta} WHERE meta_key LIKE '\_iwp\_session\_%'", ARRAY_A);
        if (!empty($users)) {
            foreach ($users as $post) {

                if (preg_match('/_iwp_session_(\d+)/', $post['meta_key'], $matches) !== 1) {
                    continue;
                }

                $data = [
                    'importer_id' => $matches[1],
                    'item_id' => $post['user_id'],
                    'item_type' =>  'user',
                    'session' => $post['meta_value']
                ];
                $format = ['%d', '%d', '%s', '%s'];

                if (is_multisite()) {
                    $data['site_id'] = $wpdb->siteid;
                    $format[] = '%d';
                }

                $wpdb->insert($wpdb->prefix . 'iwp_sessions', $data, $format);
            }
        }
    }

    public function migration_08_migrate_taxonomy_settings($migrate_data = true)
    {
        /**
         * @var \wpdb $wpdb
         */
        global $wpdb;

        // TODO: loop through serialsed post_content, switching from single cron to array
        $importers = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_type='" . IWP_POST_TYPE . "'", ARRAY_A);

        foreach ($importers as $importer) {

            $data = maybe_unserialize($importer['post_content']);
            $modified = false;

            $tmp = [];
            foreach ($data['map'] as $field_id => $field_value) {
                $count = 0;
                $tmp[preg_replace('/^(taxonomies\.\d+\.)(_.*?)$/', '$1settings.$2', $field_id, -1, $count)] = $field_value;
                if ($count > 0) {
                    $modified = true;
                }
            }

            if (!$modified) {
                continue;
            }

            $data['map'] = $tmp;


            remove_filter('content_save_pre', 'wp_filter_post_kses');
            wp_update_post(['ID' => $importer['ID'], 'post_content' => serialize($data)]);
            add_filter('content_save_pre', 'wp_filter_post_kses');
        }
    }

    // TODO: do we need this? can we get around this with manipulating the data if it exists?
    public function migration_09_migrate_attachment_settings($migrate_data = true)
    {
        /**
         * @var \wpdb $wpdb
         */
        global $wpdb;

        $importers = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_type='" . IWP_POST_TYPE . "'", ARRAY_A);

        foreach ($importers as $importer) {

            $data = maybe_unserialize($importer['post_content']);
            $modified = false;

            $tmp = [];
            foreach ($data['map'] as $field_id => $field_value) {
                $count = 0;

                $ends_with = [
                    '_download',
                    '_enable_image_hash',
                    '_featured',
                    '_ftp_host',
                    '_ftp_pass',
                    '_ftp_path',
                    '_ftp_user',
                    '_local_url',
                    '_meta\._alt',
                    '_meta\._caption',
                    '_meta\._description',
                    '_meta\._enabled',
                    '_meta\._title',
                    '_remote_url',
                    '_return'
                ];

                $tmp[preg_replace('/^(.+)(?<!\.settings)\.(' . implode('|', $ends_with) . ')$/', '$1.settings.$2', $field_id, -1, $count)] = $field_value;

                if ($count > 0) {
                    $modified = true;
                }
            }

            if (!$modified) {
                continue;
            }

            $data['map'] = $tmp;


            remove_filter('content_save_pre', 'wp_filter_post_kses');
            wp_update_post(['ID' => $importer['ID'], 'post_content' => serialize($data)]);
            add_filter('content_save_pre', 'wp_filter_post_kses');
        }
    }
}
