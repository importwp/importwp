<?php

namespace ImportWP\Common\Exporter;

interface MapperInterface
{
    public function get_fields();
    public function have_records();
    public function found_records();
    public function get_record($i, $columns);
    public function set_filters($filters);
}
