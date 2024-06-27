<?php

namespace ImportWP\Common\Importer;

interface MapperInterface
{
    public function setup();

    public function teardown();

    /**
     * @return PermissionInterface 
     */
    public function permission();

    public function exists(ParsedData $data);

    public function insert(ParsedData $data);

    public function update(ParsedData $data);

    public function get_objects_for_removal();

    public function delete($id);

    public function get_custom_field($id, $key = '', $single = false);

    public function clear_custom_field($term_id, $key);

    public function update_custom_field($id, $key, $value, $unique = false, $skip_permissions = false);

    public function add_version_tag();
}
