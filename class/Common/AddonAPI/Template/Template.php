<?php

namespace ImportWP\Common\AddonAPI\Template;

class Template extends Group
{
    /**
     * @var Panel[]
     */
    private $_panels = [];

    public function register_panel($name, $args = [])
    {
        $panel = new Panel($name, $args);
        $this->_panels[] = $panel;
        return $panel;
    }

    public function get_panel_ids()
    {
        $output = [];
        foreach ($this->_panels as $panel) {
            $output[] = $panel->get_id();
        }

        return $output;
    }

    public function get_panels()
    {
        return $this->_panels;
    }

    public function get_panel($panel_id)
    {
        foreach ($this->_panels as $panel) {
            if ($panel->get_id() == $panel_id) {
                return $panel;
            }
        }

        return false;
    }
}
