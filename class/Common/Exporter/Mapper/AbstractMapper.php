<?php

namespace ImportWP\Common\Exporter\Mapper;

abstract class AbstractMapper
{
    protected $filters;

    abstract function get_field($column, $record, $meta);

    public function set_filters($filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Filter record
     *
     * @param array $post
     * @return boolean
     */
    public function filter($row, $record, $meta)
    {
        $result = false;

        if (empty($this->filters)) {
            return $result;
        }

        foreach ($this->filters as $group) {

            $result = true;

            if (empty($group)) {
                continue;
            }

            foreach ($group as $row) {

                $left = $this->get_field($row['left'], $record, $meta);
                $right = $row['right'];
                $right_parts = array_map('trim', explode(',', $right));

                switch ($row['condition']) {
                    case 'equal':
                        if ($left != $right) {
                            $result = false;
                        }
                        break;
                    case 'contains':
                        if (stripos($left, $right) === false) {
                            $result = false;
                        }
                        break;
                    case 'in':
                        if (!in_array($left, $right_parts)) {
                            $result = false;
                        }
                        break;
                    case 'not-equal':
                        if ($left == $right) {
                            $result = false;
                        }
                        break;
                    case 'not-contains':
                        if (stripos($left, $right) !== false) {
                            $result = false;
                        }
                        break;
                    case 'not-in':
                        if (in_array($left, $right_parts)) {
                            $result = false;
                        }
                        break;
                }
            }

            if ($result) {
                return true;
            }
        }


        return $result;
    }
}
