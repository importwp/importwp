<?php

namespace ImportWP\Free;

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
     * @var Menu
     */
    public $menu;

    /**
     * @var RestManager
     */
    public $rest_manager;

    public function __construct()
    {
        parent::__construct();

        $this->importer_manager = new ImporterManager($this->importer_status_manager, $this->filesystem);
        $this->menu = new Menu($this->properties, $this->view_manager, $this->importer_manager);
        $this->rest_manager = new RestManager($this->importer_manager, $this->importer_status_manager, $this->properties, $this->http, $this->filesystem);
    }
}
