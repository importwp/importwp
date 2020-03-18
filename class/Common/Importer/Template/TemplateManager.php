<?php

namespace ImportWP\Common\Importer\Template;

use ImportWP\EventHandler;

class TemplateManager
{
    /**
     * @var EventHandler
     */
    protected $event_handler;

    public function __construct(EventHandler $event_handler)
    {
        $this->event_handler = $event_handler;
    }

    public function load_template($name)
    {
        $template =  new $name($this->event_handler);
        return $template;
    }
}
