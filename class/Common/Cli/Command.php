<?php

namespace ImportWP\Common\Cli;

class Command
{
    public static function register()
    {
        \WP_CLI::add_command('importwp', 'ImportWP\Common\Cli\Command');
    }

    /**
     * Run a ImportWP Importer
     *
     * ## OPTIONS
     *
     * <importer-id>
     * : ID of an existing importer.
     *
     * [--debug]
     * : Log all php notices.
     *
     * ## EXAMPLES
     *
     *     wp importwp import 1
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function import($args, $assoc_args)
    {
        $assoc_args['action']      = 'importwp';
        $assoc_args['importer_id'] = trim($args[0]);

        if (empty($assoc_args['importer_id'])) {
            \WP_CLI::error("You must provide an importer id.");
        }

        \WP_CLI::success("Import Complete");
    }
}
