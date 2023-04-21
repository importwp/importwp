<?php

namespace ImportWP\Common\Exporter;

interface MapperInterface
{
    public function get_fields();
    public function have_records($exporter_id);
    public function found_records();
    public function get_records();
    public function set_records($records);
    public function set_filters($filters);
    public function setup($i);
}
