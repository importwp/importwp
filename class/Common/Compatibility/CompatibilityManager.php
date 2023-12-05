<?php

namespace ImportWP\Common\Compatibility;

use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Properties\Properties;

class CompatibilityManager
{
    /**
     * @var Properties
     */
    public $properties;
    /**
     * 
     * @var Filesystem
     */
    public $filesystem;

    public function __construct($properties, $filesystem)
    {
        $this->properties = $properties;
        $this->filesystem = $filesystem;

        // Checks the compatibility mode MU plugin version and updates if it's out of date.
        add_action('admin_init', array($this, 'muplugin_version_check'), 1);

        add_action('iwp/compat/register_muplugin_uninstall', [$this, 'remove_muplugin_on_deactivation']);
    }

    public function muplugin_version_check()
    {
        if (isset($_GET['page']) && $_GET['page'] === 'importwp') {
            if (true === $this->is_muplugin_update_required()) {
                return $this->copy_muplugin();
            }
        }

        return false;
    }

    public function is_muplugin_update_required()
    {
        if (version_compare(get_option('iwp_muplugin_version', 0), $this->properties->mu_plugin_version, '<')) {
            return true;
        }

        return false;
    }

    public function copy_muplugin()
    {
        if (!wp_mkdir_p(dirname($this->properties->mu_plugin_dest)) || !$this->filesystem->copy($this->properties->mu_plugin_source, $this->properties->mu_plugin_dest)) {
            return false;
        }

        update_option('iwp_muplugin_version', $this->properties->mu_plugin_version);

        return true;
    }

    public function remove_muplugin_on_deactivation()
    {

        if ($this->filesystem->file_exists($this->properties->mu_plugin_dest)) {
            @unlink($this->properties->mu_plugin_dest);
            delete_option('iwp_muplugin_version');
        }
    }
}
