<?php

namespace ImportWP\Common\Addon;

use ImportWP\Container;

/**
 * @deprecated 2.14.0
 */
class AddonFieldDataApi extends AddonDataApi
{
    /**
     * @var AddonBaseField
     */
    protected $_field;

    protected $_row;

    public function __construct($addon, $field, $object_id, $section_id, $data, $importer_model, $template, $i = 0)
    {
        parent::__construct($addon, $object_id, $section_id, $data, $importer_model, $template);

        $this->_field = $field;
        $this->_row = $i;
    }

    public function field()
    {
        return $this->_field;
    }

    public function get_field_id()
    {
        return $this->_field->get_id();
    }

    public function get_field_data()
    {
        return $this->data($this->get_field_id());
    }

    public function row()
    {
        return $this->_row;
    }

    public function process_attachment($object_id = null)
    {

        if (is_null($object_id)) {
            $object_id = $this->object_id();
        }

        return $this->field()->process_attachment($object_id);
    }

    public function processAttachmentField($value, $post_id, $overrides = [])
    {

        /**
         * @var Filesystem $filesystem
         */
        $filesystem = $this->addon()->get_service_provider('filesystem');

        /**
         * @var Ftp $ftp
         */
        $ftp = $this->addon()->get_service_provider('ftp');

        /**
         * @var Attachment $attachment
         */
        $attachment = $this->addon()->get_service_provider('attachment');

        $raw_records = $this->data();

        $field = $this->field();
        $field_data = $field->data();

        $prefix = "{$field_data['id']}.";

        return $this->template()->process_attachment($post_id, array_merge($raw_records, $overrides), $prefix, $filesystem, $ftp, $attachment);
    }
}
