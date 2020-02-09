<?php

namespace ImportWP\Common\Importer;

interface MapperInterface
{
    public function setup();

    public function teardown();

    public function permission();

    public function exists(ParsedData $data);

    public function insert(ParsedData $data);

    public function update(ParsedData $data);

    public function get_objects_for_removal();

    public function delete($id);
}
