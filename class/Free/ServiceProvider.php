<?php

namespace ImportWP\Free;

use ImportWP\Common\Exporter\ExporterManager;
use ImportWP\Common\Rest\RestManager;
use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Common\Plugin\Menu;

class ServiceProvider extends \ImportWP\ServiceProvider
{
    /**
     * @var ImporterManager
     */
    public $importer_manager;

    /**
     * @var ExporterManager
     */
    public $exporter_manager;

    /**
     * @var Menu
     */
    public $menu;

    /**
     * @var RestManager
     */
    public $rest_manager;

    public function __construct($event_handler)
    {
        parent::__construct($event_handler);

        $this->importer_manager = new ImporterManager($this->filesystem, $this->template_manager, $event_handler);
        $this->exporter_manager = new ExporterManager();
        $this->menu = new Menu($this->properties, $this->view_manager, $this->importer_manager, $this->template_manager);
        $this->rest_manager = new RestManager($this->importer_manager, $this->exporter_manager, $this->properties, $this->http, $this->filesystem, $this->template_manager, $event_handler);

        do_action('iwp/register_events', $event_handler, $this);
    }
}
