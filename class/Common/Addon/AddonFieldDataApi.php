<?php

namespace ImportWP\Common\Addon;

use ImportWP\Container;

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

    public function row()
    {
        return $this->_row;
    }

    public function processAttachmentField($value, $post_id, $overrides = [])
    {
        /**
         * @var Filesystem $filesystem
         */
        $filesystem = Container::getInstance()->get('filesystem');

        /**
         * @var Ftp $ftp
         */
        $ftp = Container::getInstance()->get('ftp');

        /**
         * @var Attachment $attachment
         */
        $attachment = Container::getInstance()->get('attachment');

        $raw_records = $this->data();

        $field = $this->field();
        $field_data = $field->data();

        $prefix = "{$field_data['id']}.";

        return $this->template()->process_attachment($post_id, array_merge($raw_records, $overrides), $prefix, $filesystem, $ftp, $attachment);
    }
}
