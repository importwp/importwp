<?php

namespace ImportWP\Common\Importer\Mapper;

use ImportWP\Common\Attachment\Attachment;
use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Ftp\Ftp;
use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\Template\TemplateManager;
use ImportWP\Common\Util\Logger;
use ImportWP\Container;

class AttachmentMapper extends PostMapper
{
    public function exists(ParsedData $data)
    {
        $unique_fields = TemplateManager::get_template_unique_fields($this->template);

        // allow user to set unique field name, get from importer setting
        $unique_field = $this->importer->getSetting('unique_field');
        if ($unique_field !== null) {
            $unique_fields = is_string($unique_field) ? [$unique_field] : $unique_field;
        }

        $unique_fields = $this->getUniqueIdentifiers($unique_fields);
        $unique_fields = apply_filters('iwp/template_unique_fields', $unique_fields, $this->template, $this->importer);

        $unique_field_found = false;

        $post_type = 'attachment';
        $post_status = 'any, trash, future';

        $meta_args = array();
        $query_args = array(
            'post_type' => $post_type,
            'post_status' => $post_status,
            'fields' => 'ids',
            'cache_results' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'no_found_rows' => true,
        );

        $has_unique_field = false;

        foreach ($unique_fields as $field) {

            if ($field === 'src') {
                /**
                 * @var Attachment $attachment
                 */
                $attachment = Container::getInstance()->get('attachment');

                $location = $data->getValue('post.file.location', 'post');
                $download = $data->getValue('post.file.settings._download', 'post');
                $ftp_path = $data->getValue('post.file.settings._ftp_path', 'post');
                $remote_url = $data->getValue('post.file.settings._remote_url', 'post');
                $local_url = $data->getValue('post.file.settings._local_url', 'post');

                if (empty($location)) {
                    continue;
                }

                $attachment_id = null;

                switch ($download) {
                    case 'remote':
                        $source = $remote_url . $location;
                        $attachment_id = $attachment->get_attachment_by_hash($source);
                        break;
                    case 'ftp':
                        $source = $ftp_path . $location;
                        $attachment_id = $attachment->get_attachment_by_hash($source);
                        break;
                    case 'local':
                        $source = $local_url . $location;
                        $attachment_salt = file_exists($source) ? md5_file($source) : '';
                        $attachment_id = $attachment->get_attachment_by_hash($source, $attachment_salt);
                        break;
                    case 'media':
                        $source = $location;
                        $attachment_id = $attachment->attachment_partial_url_to_postid($source);
                        break;
                }

                if ($attachment_id > 0) {
                    $has_unique_field = true;
                    $query_args['p'] = $attachment_id;
                    break;
                } else {
                    return false;
                }
            } else {

                // check all groups for a unique value
                $unique_value = $data->getValue($field, '*');
                if (empty($unique_value)) {
                    $cf = $data->getData('custom_fields');
                    if (!empty($cf)) {
                        $cf_index = intval($cf['custom_fields._index']);
                        if ($cf_index > 0) {
                            for ($i = 0; $i < $cf_index; $i++) {
                                $row = 'custom_fields.' . $i . '.';
                                $custom_field_key = apply_filters('iwp/custom_field_key', $cf[$row . 'key']);
                                if ($custom_field_key !== $field) {
                                    continue;
                                }
                                $unique_value = $cf[$row . 'value'];
                                break;
                            }
                        }
                    }
                }

                if (!empty($unique_value)) {
                    $has_unique_field = true;

                    if (in_array($field, $this->_post_fields, true)) {

                        if (array_key_exists($field, $this->_query_vars)) {
                            $query_args[$this->_query_vars[$field]] = $unique_value;
                        } else {
                            switch ($field) {
                                case 'post_title':
                                    $query_args['title'] = $unique_value;
                                    break;
                                default:
                                    $query_args[$field] = $unique_value;
                                    break;
                            }
                        }
                    } else {
                        $meta_args[] = array(
                            'key'   => $field,
                            'value' => $unique_value
                        );
                    }
                    $unique_field_found = $field;
                    break;
                }
            }
        }

        if (!$has_unique_field) {
            throw new MapperException("No Unique fields present.");
        }

        if (!empty($meta_args)) {
            $query_args['meta_query'] = $meta_args;
        }

        $query = new \WP_Query($query_args);
        if ($query->post_count > 1) {
            throw new MapperException("Record is not unique: " . $unique_field_found . ", Matching Ids: (" . implode(', ', $query->posts) . ").");
        }

        if ($query->post_count == 1) {
            $this->ID = $query->posts[0];
            return $this->ID;
        }


        return false;
    }

    public function update_post_object($fields, $data)
    {
        $post = array();
        $meta = array();

        // we dont import the  ID
        if (isset($fields['ID'])) {
            unset($fields['ID']);
        }

        $this->sortFields($fields, $post, $meta);

        Logger::debug('AttachmentMapper::update_post_object -wp_update_post=' . wp_json_encode($post));

        if (!empty($post)) {

            $post['ID'] = $this->ID;

            $res = wp_update_post($post);
            if (is_wp_error($res)) {
                throw new MapperException($res->get_error_message());
            }
        }

        $this->template->process($this->ID, $data, $this->importer);

        $meta = array_merge($meta, $data->getData('custom_fields'));
        Logger::debug('AttachmentMapper::update_post_object -meta=' . wp_json_encode($meta));

        // create post meta
        if ($this->ID && !empty($meta)) {
            foreach ($meta as $key => $value) {
                if (is_array($value)) {
                    $this->clear_custom_field($this->ID, $key);
                    foreach ($value as $v) {
                        $this->add_custom_field($this->ID, $key, $v);
                    }
                } else {
                    $this->update_custom_field($this->ID, $key, $value);
                }
            }
        }
    }

    public function insert(ParsedData $data)
    {
        $fields = $data->getData('default');
        if (isset($fields['ID'])) {
            unset($fields['ID']);
        }

        $this->ID = $attachment_id = $this->download_attachment($data);
        if (!$this->ID) {
            throw new MapperException("Unable to download and insert attachment");
        }

        $this->update_post_object($fields, $data);

        $this->add_version_tag();
        $this->template->post_process($this->ID, $data);

        return $attachment_id;
    }

    public function update(ParsedData $data)
    {
        $attachment_id = $data->getId();

        $fields = $data->getData('default');
        if (isset($fields['ID'])) {
            unset($fields['ID']);
        }

        $this->download_attachment($data);

        $this->update_post_object($fields, $data);

        $this->add_version_tag();
        $this->template->post_process($this->ID, $data);

        return $attachment_id;
    }

    /**
     * Download Attachment
     *
     * @param ParsedData $data
     */
    public function download_attachment($data)
    {
        if (!$this->importer->isEnabledField('post.file')) {
            return false;
        }

        $is_allowed = $data->permission()->validate(['file' => ''], $data->getMethod(), 'post');
        if (!isset($is_allowed['file'])) {
            return false;
        }

        $attachment_id = intval($data->getId());

        /**
         * @var Attachment $attachment
         */
        $attachment = Container::getInstance()->get('attachment');

        $location = $data->getValue('post.file.location', 'post');
        $download = $data->getValue('post.file.settings._download', 'post');
        $ftp_path = $data->getValue('post.file.settings._ftp_path', 'post');
        $remote_url = $data->getValue('post.file.settings._remote_url', 'post');
        $local_url = $data->getValue('post.file.settings._local_url', 'post');
        $ftp_host = $data->getValue('post.file.settings._ftp_host', 'post');
        $ftp_user = $data->getValue('post.file.settings._ftp_user', 'post');
        $ftp_pass = $data->getValue('post.file.settings._ftp_pass', 'post');

        if (empty($location)) {
            return $attachment_id;
        }

        $attachment_salt = '';
        $result = false;

        switch ($download) {
            case 'remote':

                $source = $remote_url . $location;
                $attachment_salt = file_exists($source) ? md5_file($source) : '';

                /**
                 * @var Filesystem $filesystem
                 */
                $filesystem = Container::getInstance()->get('filesystem');

                $custom_filename = apply_filters('iwp/attachment/filename', null, $source);
                $result = $filesystem->download_file($source, null, null, $custom_filename);

                break;
            case 'ftp':

                $source = $ftp_path . $location;
                $attachment_salt = file_exists($source) ? md5_file($source) : '';

                /**
                 * @var Ftp $ftp
                 */
                $ftp = Container::getInstance()->get('ftp');

                $custom_filename = apply_filters('iwp/attachment/filename', null, $source);
                $result = $ftp->download_file($source, $ftp_host, $ftp_user, $ftp_pass, $custom_filename);
                break;
            case 'local':

                $source = $local_url . $location;
                $attachment_salt = file_exists($source) ? md5_file($source) : '';

                /**
                 * @var Filesystem $filesystem
                 */
                $filesystem = Container::getInstance()->get('filesystem');

                $custom_filename = apply_filters('iwp/attachment/filename', null, $source);
                $result = $filesystem->copy_file($source, null, $custom_filename);
                break;
        }

        if (is_wp_error($result)) {
            throw new MapperException($result->get_error_message());
        }

        if ($result) {

            // We have downloaded a file
            if ($attachment_id) {

                // updated existing
                $attachment_id = wp_update_post([
                    'ID' => $attachment_id,
                    'post_mime_type' =>  $result['mime'],
                    'file' => $result['dest']
                ]);
            } else {

                // insert new
                $attachment_id = $attachment->insert_attachment(0, $result['dest'], $result['mime']);
            }

            if ($attachment_id) {

                // Attachment has been created or updated
                $attachment->generate_image_sizes($attachment_id, $result['dest']);
                $attachment->store_attachment_hash($attachment_id, $source, $attachment_salt);
            }

            return $attachment_id;
        }

        return false;
    }

    public function get_objects_for_removal()
    {
        if ($this->is_session_tag_enabled()) {
            return $this->get_ids_without_session_tag('pt-' . 'attachment');
        } else {
            $q = new \WP_Query(array(
                'post_type' => 'attachment',
                'meta_query' => array(
                    array(
                        'key' => '_iwp_session_' . $this->importer->getId(),
                        'value' => $this->importer->getStatusId(),
                        'compare' => '!='
                    )
                ),
                'fields' => 'ids',
                'posts_per_page' => -1,
                'cache_results' => false,
                'update_post_meta_cache' => false,
                'post_status' => 'any'
            ));

            if ($q->have_posts()) {
                return $q->posts;
            }
        }

        return false;
    }

    public function delete($id)
    {
        $permissions = $this->importer->getPermission('remove');
        $force = isset($permissions['trash']) ? !$permissions['trash'] : true; // trash = true

        if (!$force) {

            // set trash flag
            update_post_meta($id, '_iwp_trash_status', get_post_status($id));
            update_post_meta($id, '_iwp_trash_importer', $this->importer->getId());
        }

        wp_delete_attachment($id, $force);

        $this->remove_session_tag($id, 'pt-attachment');
    }

    public function add_version_tag()
    {
        if ($this->is_session_tag_enabled()) {
            $this->add_session_tag('pt-attachment');
        } else {
            update_post_meta($this->ID, '_iwp_session_' . $this->importer->getId(), $this->importer->getStatusId());
        }
    }
}
