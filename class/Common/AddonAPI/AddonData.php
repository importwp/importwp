<?php

namespace ImportWP\Common\AddonAPI;

use ImportWP\Common\Importer\ParsedData;

class AddonData
{
    private $_id;
    /**
     * @var ParsedData
     */
    private $_data;

    /**
     * @var \ImportWP\Common\AddonAPI\Template\Template
     */
    private $_template;

    /**
     * 
     * @param int $id 
     * @param \ImportWP\Common\Importer\ParsedData $data
     * @param \ImportWP\Common\AddonAPI\Template\Template $template
     * @return void 
     */
    public function __construct($id, $data, $template)
    {
        $this->_id = $id;
        $this->_data = $data;
        $this->_template = $template;
    }

    public function get_id()
    {
        return $this->_id;
    }

    public function get_value($field_id, $group = null)
    {
        $fields = $this->_template->get_fields();

        $current_field = null;
        foreach ($fields as $field) {
            if ($field->get_id() !== $field_id) {
                continue;
            }

            $current_field = $field;
        }

        if (!$current_field) {
            throw new \Exception("Missing field: " . $field_id . ", group: " . $group);
        }

        if (is_null($group)) {
            $group = $current_field->get_group();
        }

        if ($current_field->get_type() === 'text') {
            return $this->_data->getValue($group . '.' . $field->get_id(), $group);
        } elseif ($current_field->get_type() === 'attachment') {
            // TODO: how do we handle attachment fields
        }

        return false;
    }
}
