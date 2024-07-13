<?php

namespace ImportWP\Common\Addon;

use ImportWP\Container;

/**
 * @deprecated 2.14.0
 */
class AddonBaseField extends AddonBaseData implements AddonFieldInterface
{
    /**
     * @var callable
     */
    protected $_process_callback;

    protected $_is_field_processable = false;
    protected $_process_mapper;
    protected $_process_template;
    protected $_process_data;

    public function _enable_processing()
    {
        $this->_is_field_processable = true;
    }

    public function _is_processing_enabled()
    {
        return $this->_is_field_processable;
    }

    /**
     * @param AddonBase $addon
     * @param integer $object_id 
     * @param string $section_id 
     * @param mixed $data 
     * @param \ImportWP\Common\Model\ImporterModel $importer_model 
     * @param \ImportWP\Common\Importer\Template\Template $template 
     * @return void 
     */
    public function _process($addon, $object_id, $section_id, $data, $importer_model, $template, $i = false)
    {
        // setup
        $this->_process_mapper = $template->get_mapper();
        $this->_process_template = $template;
        $this->_process_data = $data;

        if ($this->_process_callback !== false && !is_null($this->_process_callback) && is_callable($this->_process_callback)) {
            call_user_func($this->_process_callback, new AddonFieldDataApi($addon, $this, $object_id, $section_id, $data, $importer_model, $template, $i));
        } else {

            $field = $this->data();

            if ($field['type'] === 'attachment') {
                $meta_value = $this->process_attachment($object_id, $field['id'], $section_id);
            } else {
                $meta_value = isset($data[$field['id']]) ? $data[$field['id']] : '';
            }

            if ($this->_process_callback === false) {
                $addon->store_meta($section_id, $object_id, $field['id'], $meta_value, $i);
            } else {
                $addon->update_meta($object_id, $field['id'], $meta_value);
            }
        }

        // tearmdown
        $this->_process_mapper = null;
        $this->_process_template = null;
        $this->_process_data = null;
    }

    public function process_attachment($object_id, $field_id = false)
    {
        if ($field_id === false) {
            $field_id = $this->get_id();
        }
        /**
         * @var ImportWP\Common\Filesystem\Filesystem $filesystem
         */

        $filesystem = $this->addon()->get_service_provider('filesystem');

        /**
         * @var ImportWP\Common\Ftp\Ftp $ftp
         */
        $ftp = $this->addon()->get_service_provider('ftp');

        /**
         * @var ImportWP\Common\Attachment\Attachment $attachment
         */
        $attachment = $this->addon()->get_service_provider('attachment');

        $attachment_prefix = '';
        $attachment_keys = [
            '_meta._title',
            '_meta._alt',
            '_meta._caption',
            '_meta._description',
            '_enable_image_hash',
            '_download',
            '_featured',
            '_remote_url',
            '_ftp_user',
            '_ftp_host',
            '_ftp_pass',
            '_ftp_path',
            '_local_url',
            '_meta._enabled'
        ];
        $attachment_data = [
            'location' => $this->_process_data[$field_id . '.location'],
        ];

        foreach ($attachment_keys as $k) {
            if (isset($this->_process_data[$field_id . '.settings.' . $k])) {
                $attachment_data[$k] = $this->_process_data[$field_id . '.settings.' . $k];
            } elseif (isset($this->_process_data[$field_id . '.' . $k])) {
                $attachment_data[$k] = $this->_process_data[$field_id . '.' . $k];
            } else {
                $attachment_data[$k] = '';
            }
        }
        return $this->_process_template->process_attachment($object_id, $attachment_data, $attachment_prefix, $filesystem, $ftp, $attachment);
    }

    public function save($callback)
    {
        $this->_process_callback = $callback;
        return $this;
    }

    public function options($options)
    {
        if (!isset($this->_data['settings'])) {
            $this->_data['settings'] = [];
        }

        $this->_data['settings']['options'] = $options;
        return $this;
    }

    public function default($value)
    {
        if (!isset($this->_data['settings'])) {
            $this->_data['settings'] = [];
        }

        $this->_data['settings']['default'] = $value;
        return $this;
    }

    public function tooltip($message)
    {
        if (!isset($this->_data['settings'])) {
            $this->_data['settings'] = [];
        }

        $this->_data['settings']['tooltip'] = $message;
        return $this;
    }

    public function get_id()
    {
        return isset($this->_data['id']) ? $this->_data['id'] : false;
    }
}
