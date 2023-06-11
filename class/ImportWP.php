<?php

namespace ImportWP;

use ImportWP\Common\Model\ImporterModel;
use ImportWP\Common\Rest\RestManager;

class ImportWP
{
    /**
     * @var RestManager
     */
    private $rest_manager;

    /**
     * @var ImporterModel
     */
    public $importer;

    public function __construct($is_pro = false)
    {
        $container = Container::getInstance();

        $container->setupServiceProviders($is_pro);

        $this->rest_manager = $container->get('rest_manager');
    }

    public function register()
    {
        if ($this->rest_manager) {
            $this->rest_manager->register();
        }
    }
}
