<?php

namespace ImportWP\Common\Importer\Permission;

use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\PermissionInterface;
use ImportWP\Common\Model\ImporterModel;

class Permission implements PermissionInterface
{
    private $importer_model;

    public function __construct(ImporterModel $importer_model)
    {
        $this->importer_model = $importer_model;
    }

    public function validate($fields, $method, $group_id)
    {
        $permission_method = false;

        if ('INSERT' === $method) {
            $permission_method = 'create';
        } elseif ('UPDATE' === $method) {
            $permission_method = 'update';
        } else {
            throw new MapperException(sprintf(__('Not enough permissions to %s record.', 'jc-importer'), 'import'));
        }

        if (false === $this->allowed_method($permission_method)) {
            throw new MapperException(sprintf(__('Not enough permissions to %s record.', 'jc-importer'), $permission_method));
        }

        $permission_data = $this->importer_model->getPermission($permission_method);
        $fields = $this->validate_group($fields, $group_id, $permission_data);

        return $fields;
    }

    public function allowed_method($method)
    {
        $permission_data = $this->importer_model->getPermission($method);
        return isset($permission_data['enabled']) && $permission_data['enabled'] !== true ? false : true;
    }

    public function validate_group($fields, $group_id, $permission_data)
    {
        $permission_type = isset($permission_data['type']) ? $permission_data['type'] : false;
        if (!$permission_type) {
            return $fields;
        }

        $permission_fields = is_array($permission_data['fields']) ? $permission_data['fields'] :  explode("\n", $permission_data['fields']);

        $matches = array();
        foreach ($permission_fields as $field_search) {
            $matches = array_merge($matches, $this->match_permissions($field_search, $fields));
        }

        if ('include' === $permission_type) {
            return $matches;
        } elseif ('exclude' === $permission_type) {
            $result = array();
            foreach ($fields as $field_id => $field_value) {
                if (!isset($matches[$field_id])) {
                    $result[$field_id] = $field_value;
                }
            }
            return $result;
        }

        return $fields;
    }

    /**
     * Match search string against field list
     *
     * @param string $field_search
     * @param array $fields List of field_id => field_value
     *
     * @return array
     */
    private  function match_permissions($field_search, $fields)
    {
        $result = array();
        // replaces * with the regex pattern
        $pattern = '[a-zA-Z\d_\-\.]+';
        if ('*' === $field_search) {
            // *
            return $fields;
        } elseif (1 === preg_match("/^\*{$pattern}/i", $field_search)) {
            $search = substr($field_search, 1);
            $search = str_replace('.', '\.', $search);
            // *_src
            foreach ($fields as $field_id => $field_value) {
                if (1 === preg_match("/^{$pattern}{$search}$/i", $field_id)) {
                    $result[$field_id] = $fields[$field_id];
                }
            }
        } elseif (1 === preg_match("/{$pattern}\*$/i", $field_search)) {
            $search = substr($field_search, 0, -1);
            $search = str_replace('.', '\.', $search);
            // attachment_*
            foreach ($fields as $field_id => $field_value) {
                if (1 === preg_match("/^{$search}{$pattern}$/i", $field_id)) {
                    $result[$field_id] = $fields[$field_id];
                }
            }
        } else {
            if (!isset($fields[$field_search])) {
                return $result;
            }
            $result[$field_search] = $fields[$field_search];
        }
        return $result;
    }
}
