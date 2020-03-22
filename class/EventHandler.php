<?php

namespace ImportWP;

class EventHandler
{

    /**
     * List of stored events
     *
     * @var array $events
     */
    private $events;

    /**
     * Trigger event
     *
     * @param string $event
     * @param array $args
     *
     * @return mixed
     */
    public function run($event, $orignal_args = array())
    {
        $is_empty = false;
        if (empty($orignal_args)) {
            $is_empty = true;
        } else {
            $result = array_shift($orignal_args);
        }

        if (isset($this->events[$event]) && is_array($this->events[$event]) && !empty($this->events[$event])) {
            foreach ($this->events[$event] as $callback) {

                if (true === $is_empty) {
                    $args = [];
                } else {
                    $args = array_merge([$result], $orignal_args);
                }

                $result = call_user_func_array($callback, (array) $args);
            }
        }

        return $result;
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
