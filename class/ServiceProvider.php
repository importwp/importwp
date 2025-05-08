<?php

namespace ImportWP;

use ImportWP\Common\Attachment\Attachment;
use ImportWP\Common\Compatibility\CompatibilityManager;
use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Filesystem\ZipArchive;
use ImportWP\Common\Ftp\Ftp;
use ImportWP\Common\Http\Http;
use ImportWP\Common\Importer\Template\TemplateManager;
use ImportWP\Common\Properties\Properties;
use ImportWP\Common\Rest\RestManager;
use ImportWP\Common\UI\ViewManager;
use ImportWP\Common\Util\Util;

class ServiceProvider
{
    /**
     * @var Properties
     */
    public $properties;
    /**
     * @var Filesystem
     */
    public $filesystem;
    /**
     * @var Attachment
     */
    public $attachment;
    /**
     * @var Ftp
     */
    public $ftp;
    /**
     * @var Http
     */
    public $http;
    /**
     * @var ViewManager
     */
    public $view_manager;
    /**
     * @var RestManager
     */
    public $rest_manager;

    /**
     * @var TemplateManager
     */
    protected $template_manager;

    /**
     * @var ZipArchive
     */
    protected $zip_archive;

    /**
     * @var Util
     */
    public $util;

    /**
     * @var CompatibilityManager
     */
    public $compatibility_manager;

    public function __construct($event_handler)
    {
        $this->util = new Util();
        $this->properties = new Properties();
        $this->filesystem = new Filesystem($event_handler);
        $this->attachment = new Attachment();
        $this->ftp = new Ftp($this->filesystem);
        $this->http = new Http($this->properties);

        $this->view_manager = new ViewManager($this->properties);
        $this->template_manager = new TemplateManager($event_handler);
        $this->zip_archive = new ZipArchive();

        $this->properties->mu_plugin_version = 2;
        $this->properties->mu_plugin_dir    = (defined('WPMU_PLUGIN_DIR') && defined('WPMU_PLUGIN_URL')) ? WPMU_PLUGIN_DIR : trailingslashit(WP_CONTENT_DIR) . 'mu-plugins';
        $this->properties->mu_plugin_source = trailingslashit($this->properties->plugin_dir_path) . 'compatibility/importwp-compatibility.php';
        $this->properties->mu_plugin_dest   = trailingslashit($this->properties->mu_plugin_dir) . 'importwp-compatibility.php';

        $this->compatibility_manager = new CompatibilityManager($this->properties, $this->filesystem);
    }
}
