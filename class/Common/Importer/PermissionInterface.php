<?php

namespace ImportWP\Common\Importer;

interface PermissionInterface
{
    public function validate($fields, $method, $group_id);
}
