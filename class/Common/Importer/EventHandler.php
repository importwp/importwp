<?php

namespace ImportWP\Common\Importer;

class EventHandler
{
    /**
     * Single instance of class
     */
    protected static $_instance = null;
    /**
     * List of stored events
     *
     * @var array $events
     */
    private $events;

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Trigger event
     *
     * @param string $event
     * @param array $args
     *
     * @return void
     */
    public function run($event, $args = array())
    {

        if (isset($this->events[$event]) && is_array($this->events[$event]) && !empty($this->events[$event])) {
            foreach ($this->events[$event] as $callback) {

                call_user_func_array($callback, (array) $args);
            }
        }
    }

    /**
     * Add event
     *
     * @param string $event
     * @param $callback
     */
    public function listen($event, $callback)
    {

        if (!isset($this->events[$event])) {
            $this->events[$event] = array();
        }

        $this->events[$event][] = $callback;
    }

    /**
     * Remove event
     *
     * @param string $event
     * @param $callback
     */
    public function unlisten($event, $callback)
    {
        if (isset($this->events[$event]) && in_array($callback, $this->events[$event], true)) {

            if (($key = array_search($callback, $this->events[$event])) !== false) {
                unset($this->events[$event][$key]);
            }

            if (empty($this->events[$event])) {
                unset($this->events[$event]);
            }
        }
    }
}
