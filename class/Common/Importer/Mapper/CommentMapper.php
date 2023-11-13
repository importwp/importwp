<?php

namespace ImportWP\Common\Importer\Mapper;

use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\MapperInterface;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\Template\TemplateManager;
use ImportWP\Common\Util\Logger;

class CommentMapper extends AbstractMapper implements MapperInterface
{
    protected $_core_fields = [
        'comment_ID',
        'comment_agent',
        'comment_approved',
        'comment_author',
        'comment_author_email',
        'comment_author_IP',
        'comment_author_url',
        'comment_content',
        'comment_date',
        'comment_date_gmt',
        'comment_karma',
        'comment_parent',
        'comment_post_ID',
        'comment_type',
        'user_id',
    ];

    public function setup()
    {
    }

    public function teardown()
    {
    }

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

        $query_args = [
            'fields' => 'ids',
            'update_comment_meta_cache' => false,
            'update_comment_post_cache' => false,
            'no_found_rows' => true,
        ];

        $unique_field_found = false;
        $has_unique_field = false;

        foreach ($unique_fields as $field) {

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

                if (in_array($field, $this->_core_fields, true)) {

                    switch ($field) {
                        case 'comment_ID':
                            $query_args['comment__in'] = [intval($unique_value)];
                            break;
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

        if (!$has_unique_field) {
            throw new MapperException("No Unique fields present.");
        }

        if (!empty($meta_args)) {
            $query_args['meta_query'] = $meta_args;
        }

        $query = new \WP_Comment_Query($query_args);

        // $query->found_comments doesnt work when using field ids
        if (count($query->comments) > 1) {
            throw new MapperException("Record is not unique: " . $unique_field_found . ", Matching Ids: (" . implode(', ', $query->comments) . ").");
        }

        if (count($query->comments) == 1) {
            $this->ID = $query->comments[0];
            return $this->ID;
        }

        return false;
    }

    /**
     * Sort fields into comment and meta array
     *
     * @param  array $fields list of fields
     * @param  array $post comment_data pointer array
     * @param  array $meta comment_meta pointer array
     *
     * @return void
     */
    function sortFields($fields = array(), &$comment = array(), &$meta = array())
    {

        foreach ($fields as $id => $value) {

            if (in_array($id, $this->_core_fields, true)) {

                // comment field
                $comment[$id] = $value;
            } else {

                // meta field
                $meta[$id] = $value;
            }
        }
    }

    public function insert(ParsedData $data)
    {
        $fields = $data->getData('default');

        $comment = array();
        $meta = array();

        // we dont import the comment_ID
        if (isset($fields['comment_ID'])) {
            unset($fields['comment_ID']);
        }

        $this->sortFields($fields, $comment, $meta);

        Logger::debug('CommentMapper::insert -wp_insert_comment=' . wp_json_encode($comment));
        $this->ID = wp_insert_comment($comment);
        if (!$this->ID) {
            throw new MapperException("Unable to insert comment");
        }

        $this->template->process($this->ID, $data, $this->importer);

        $meta = array_merge($meta, $data->getData('custom_fields'));
        Logger::debug('CommentMapper::insert -meta=' . wp_json_encode($meta));

        // create meta
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

        $this->add_version_tag();
        $this->template->post_process($this->ID, $data);

        clean_comment_cache($this->ID);

        return $this->ID;
    }

    public function update(ParsedData $data)
    {
        $fields = $data->getData('default');

        $comment = array();
        $meta = array();

        // we dont import the comment_ID
        if (isset($fields['comment_ID'])) {
            unset($fields['comment_ID']);
        }

        $this->sortFields($fields, $comment, $meta);
        if (!$this->ID) {
            return false;
        }

        Logger::debug('CommentMapper::update -wp_update_comment=' . wp_json_encode($comment));

        if (!empty($comment)) {
            $comment['comment_ID'] = $this->ID;
            $result = wp_update_comment($comment, true);
            if (is_wp_error($result)) {
                throw new MapperException($result->get_error_message());
            }
        }

        $this->template->process($this->ID, $data, $this->importer);

        // update post meta
        $meta = array_merge($meta, $data->getData('custom_fields'));
        Logger::debug('CommentMapper::update -meta=' . wp_json_encode($meta));

        if (!empty($meta)) {
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

        $this->add_version_tag();
        $this->template->post_process($this->ID, $data);

        clean_comment_cache($this->ID);

        return $this->ID;
    }

    public function get_objects_for_removal()
    {
        if ($this->is_session_tag_enabled()) {
            return $this->get_ids_without_session_tag('comment');
        } else {
            $q = new \WP_Comment_Query(array(
                'meta_query' => array(
                    array(
                        'key' => '_iwp_session_' . $this->importer->getId(),
                        'value' => $this->importer->getStatusId(),
                        'compare' => '!='
                    )
                ),
                'fields' => 'ids',
                'update_comment_meta_cache' => false,
                'update_comment_post_cache' => false,
                'no_found_rows' => true,
            ));

            if ($q->have_posts()) {
                return $q->posts;
            }
        }

        return false;
    }

    public function delete($id)
    {
        wp_delete_comment($id);
        $this->remove_session_tag($id, 'comment');
    }

    /**
     * Add custom field, allow for multiple records using the same key
     *
     * @param int $id
     * @param string $key
     * @param string $value
     * @return void
     */
    public function add_custom_field($id, $key, $value)
    {
        // Stop double serialization
        if (is_serialized($value)) {
            $value = unserialize($value);
        }

        add_comment_meta($id, $key, $value);
    }

    public function update_custom_field($id, $key, $value, $unique = false, $skip_permissions = false)
    {

        $old_value = get_comment_meta($id, $key, true);

        // Stop double serialization
        if (is_serialized($value)) {
            $value = unserialize($value);
        }

        // check if new value
        if ($old_value === $value) {
            return;
        }

        if ($value !== '' && '' == $old_value) {
            add_comment_meta($id, $key, $value, $unique);
        } elseif ($value !== '' && $value !== $old_value) {
            update_comment_meta($id, $key, $value);
        } elseif ('' === $value && $old_value) {
            delete_comment_meta($id, $key, $value);
        }
    }

    /**
     * Clear all meta before adding custom field
     */
    public function clear_custom_field($id, $key)
    {
        delete_comment_meta($id, $key);
    }

    public function add_version_tag()
    {
        if ($this->is_session_tag_enabled()) {
            $this->add_session_tag('comment');
        } else {
            update_comment_meta($this->ID, '_iwp_session_' . $this->importer->getId(), $this->importer->getStatusId());
        }
    }
}
