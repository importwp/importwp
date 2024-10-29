<?php

namespace ImportWP\Common\AddonAPI\Importer\Template;

class Template extends Group
{
    /**
     * @var Panel[]
     */
    private $_panels = [];

    /**
     * @var CustomFields[]
     */
    private $_custom_fields = [];

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

    public function register_custom_fields($name, $prefix)
    {
        $tmp = new CustomFields($name, $prefix);
        $this->_custom_fields[] = $tmp;
        return $tmp;
    }

    public function get_custom_fields()
    {
        return $this->_custom_fields;
    }
}
