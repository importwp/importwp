<?php

namespace ImportWP;

use ImportWP\Common\Attachment\Attachment;
use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Ftp\Ftp;
use ImportWP\Common\Http\Http;
use ImportWP\Common\Importer\ImporterStatusManager;
use ImportWP\Common\Importer\Template\TemplateManager;
use ImportWP\Common\Properties\Properties;
use ImportWP\Common\Rest\RestManager;
use ImportWP\Common\UI\ViewManager;

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
     * @var ImporterStatusManager
     */
    public $importer_status_manager;

    /**
     * @var TemplateManager
     */
    protected $template_manager;

    public function __construct($event_handler)
    {
        $this->properties = new Properties();
        $this->filesystem = new Filesystem($event_handler);
        $this->attachment = new Attachment();
        $this->ftp = new Ftp($this->filesystem);
        $this->http = new Http($this->properties);

        $this->view_manager = new ViewManager($this->properties);
        $this->importer_status_manager = new ImporterStatusManager();
        $this->template_manager = new TemplateManager($event_handler);
    }
}
