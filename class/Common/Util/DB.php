<?php

namespace ImportWP\Common\Util;

class DB
{

    public static function get_table_name($table)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        switch ($table) {
            case 'claim':
                return $wpdb->prefix . 'iwp_queue_claims';
            case 'queue':
                return $wpdb->prefix . 'iwp_queues';
            case 'import':
                return $wpdb->prefix . 'iwp_imports';
            case 'queue_error':
                return $wpdb->prefix . 'iwp_queue_errors';
        }

        return false;
    }

    public static function migrate()
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $tables = 0;

        if ($table_name = DB::get_table_name('import')) {
            $wpdb_collate = $wpdb->collate;

            $wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");

            $sql =
                "CREATE TABLE `{$table_name}` (
                `id` bigint(20) unsigned NOT NULL auto_increment ,
                `importer_id` bigint(20) unsigned NULL,
                `config` LONGTEXT NULL,
                `step` varchar(10) DEFAULT 'draft',
                `status` char(1)  DEFAULT 'R',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
                PRIMARY KEY  (`id`)
                )
                COLLATE {$wpdb_collate}";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                $tables++;
            }
        }

        if ($table_name = DB::get_table_name('claim')) {
            $wpdb_collate = $wpdb->collate;

            $wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");

            $sql =
                "CREATE TABLE `{$table_name}` (
                `id` bigint(20) unsigned NOT NULL auto_increment ,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
                PRIMARY KEY  (`id`)
                )
                COLLATE {$wpdb_collate}";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                $tables++;
            }
        }

        if ($table_name = DB::get_table_name('queue')) {
            $wpdb_collate = $wpdb->collate;

            $wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");

            $sql =
                "CREATE TABLE `{$table_name}` (
                `id` bigint(20) unsigned NOT NULL auto_increment ,
                `claim_id` bigint(20) unsigned DEFAULT 0,
                `import_id` bigint(20) unsigned NULL,
                `record` bigint(20) unsigned NULL,
                `pos` bigint(20) unsigned NULL,
                `len` bigint(20) unsigned NULL,
                `data` TEXT NULL DEFAULT NULL,
	            `type` CHAR(1) NULL DEFAULT NULL,
                `status` char(1)  DEFAULT 'Q',
                `attempts` tinyint(8) DEFAULT 0,
                `attempted_at` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
                PRIMARY KEY  (`id`)
                )
                COLLATE {$wpdb_collate}";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                $tables++;
            }
        }

        if ($table_name = DB::get_table_name('queue_error')) {
            $wpdb_collate = $wpdb->collate;

            $wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");

            $sql =
                "CREATE TABLE `{$table_name}` (
                `id` bigint(20) unsigned NOT NULL auto_increment ,
                `queue_id` bigint(20) unsigned NOT NULL,
                `message` LONGTEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
                PRIMARY KEY  (`id`)
                )
                COLLATE {$wpdb_collate}";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                $tables++;
            }
        }
    }
}
