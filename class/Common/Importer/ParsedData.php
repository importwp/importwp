<?php

namespace ImportWP\Common\Importer;

use ImportWP\Common\Importer\Exception\RecordUpdatedSkippedException;

class ParsedData
{
    private $id;
    private $method;
    private $data;

    /**
     * @var MapperInterface $mapper
     */
    private $mapper;

    public function __construct(MapperInterface $mapper)
    {
        $this->mapper      = $mapper;
    }

    public function add($data, $group = 'default')
    {
        $group_id = 'default';
        if (is_string($group)) {
            $group_id = $group;
        } elseif (is_array($group)) {
            $group_id = isset($group['id']) ? $group['id'] : 'default';
        }

        if (!isset($this->data[$group_id])) {
            $this->data[$group_id] = [];
        }

        $this->data[$group_id] = array_merge($this->data[$group_id], $data);
    }

    /**
     * Update data with new values
     *
     * @param $data
     * @param string $group
     */
    public function update($data, $group = 'default')
    {

        $group_id = 'default';
        if (is_string($group)) {
            $group_id = $group;
        } elseif (is_array($group)) {
            $group_id = isset($group['id']) ? $group['id'] : 'default';
        }

        foreach ($data as $key => $value) {
            $this->data[$group_id][$key] = $value;
        }
    }

    public function replace($data, $group = 'default')
    {

        $group_id = 'default';
        if (is_string($group)) {
            $group_id = $group;
        } elseif (is_array($group)) {
            $group_id = isset($group['id']) ? $group['id'] : 'default';
        }

        $this->data[$group_id] = $data;
    }

    /**
     * Get data by group
     *
     * @param string $group Group id.
     *
     * @return array
     */
    public function getData($group = 'default')
    {
        return isset($this->data[$group]) ? $this->data[$group] : array();
    }

    public function getValue($field, $group = 'default')
    {
        // Escape early if no data is set
        if (empty($this->data)) {
            return false;
        }

        if ($group === '*') {
            foreach ($this->data as $group => $group_data) {
                if (isset($group_data[$field])) {
                    return $group_data[$field];
                }
            }
        } else {
            $data = $this->getData($group);
            return isset($data[$field]) ? $data[$field] : false;
        }

        return false;
    }

    public function getId()
    {
        return $this->id;
    }

    public function map()
    {
        do_action('iwp/importer/mapper/init', $this);

        $this->id = $this->mapper->exists($this);

        $this->method = false === $this->id ? 'INSERT' : 'UPDATE';

        // TODO: Should we pre process the data before permissions?

        if ($this->mapper->permission()) {
            $allowed_fields = $this->mapper->permission()->validate($this->getData('default'), $this->method, 'default');
            $this->replace($allowed_fields);
        }

        do_action('iwp/importer/mapper/before', $this);

        if (false === $this->id) {
            do_action('iwp/importer/mapper/before_insert', $this);
            $hash_check = $this->get_hash();
            $this->id = $this->mapper->insert($this);
            $this->mapper->update_custom_field($this->id, '_iwp_hash', $hash_check);
        } else {

            do_action('iwp/importer/mapper/before_update', $this);
            $hash = $this->mapper->get_custom_field($this->id, '_iwp_hash', true);
            $hash_check = $this->get_hash();
            if ($hash !== $hash_check) {
                $this->id = $this->mapper->update($this);
                $this->mapper->update_custom_field($this->id, '_iwp_hash', $hash_check);
            } else {
                // we need to log this result
                $this->mapper->add_version_tag();
                $this->mapper->update_custom_field($this->id, '_iwp_hash', $hash_check);
                throw new RecordUpdatedSkippedException("Data matches previous import");
                return;
            }
        }


        do_action('iwp/importer/mapper/after', $this);
    }

    public function getGroupKeys()
    {
        return array_keys($this->data);
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getLog()
    {
        return $this->mapper->getLog();
    }

    public function permission()
    {
        return $this->mapper->permission();
    }

    public function isInsert()
    {
        return $this->method === 'INSERT';
    }

    public function isUpdate()
    {
        return $this->method === 'UPDATE';
    }

    public function check_hash($hash = '')
    {
        return $hash == $this->get_hash();
    }

    public function get_hash()
    {
        return $this->array_md5($this->data);
        // $data = (array)$this->data;

        // $original = (array)$this->data;

        // array_multisort($array);

        // return md5(json_encode($data));
    }

    function array_md5($array)
    {
        //since we're inside a function (which uses a copied array, not 
        //a referenced array), you shouldn't need to copy the array
        array_multisort($array);

        // update_site_option('hash_' . time(), json_encode($array));

        return md5(json_encode(apply_filters('iwp/hash_compare', $array)));
    }

    // function recur_ksort(&$array)
    // {
    //     foreach ($array as &$value) {
    //         if (is_array($value))
    //             $this->recur_ksort($value);
    //     }
    //     ksort($array);
    // }
}
