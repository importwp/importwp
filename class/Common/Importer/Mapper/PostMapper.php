<?php

namespace ImportWP\Common\Importer\Mapper;

use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\MapperInterface;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Util\Logger;

class PostMapper extends AbstractMapper implements MapperInterface
{
    /**
     * Reserved Field Names for post table
     * @var array
     */
    protected $_post_fields = array(
        'ID',
        'menu_order',
        'comment_status',
        'ping_status',
        'pinged',
        'post_author',
        'post_category',
        'post_content',
        'post_date',
        'post_date_gmt',
        'post_excerpt',
        'post_name',
        'post_parent',
        'post_password',
        'post_status',
        'post_title',
        'post_type',
        'tags_input',
        'to_ping',
        'tax_input'
    );

    protected $_query_vars = array(
        'post_name' => 'name',
        'ID'        => 'p'
    );

    public function setup()
    {
        wp_defer_term_counting(true);
        wp_defer_comment_counting(true);
    }

    public function teardown()
    {
        wp_defer_term_counting(false);
        wp_defer_comment_counting(false);
    }

    public function exists(ParsedData $data)
    {
        list($unique_fields, $meta_args, $has_unique_field) = $this->exists_get_identifier($data);
        $unique_field_found = false;

        $post_type = $this->importer->getSetting('post_type');
        $post_status = 'any, trash, future';

        $query_args = array(
            'post_type' => $post_type,
            'post_status' => $post_status,
            'fields' => 'ids',
            'cache_results' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'no_found_rows' => true,
        );

        if (!$has_unique_field) {
            foreach ($unique_fields as $field) {

                // check all groups for a unique value
                $unique_value = $this->find_unique_field_in_data($data, $field);

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
                    $this->set_unique_identifier_settings($unique_field_found, $unique_value);
                    break;
                }
            }
        }




        if (!$has_unique_field) {

            // fallback to post_title
            $unique_value = $data->getValue('post_title');
            if (empty($unique_value) || !$this->importer->has_legacy_unique_identifier()) {
                throw new MapperException(__("No Unique fields present.", 'jc-importer'));
            }

            $query_args['title'] = $unique_value;
            $this->set_unique_identifier_settings('title', $unique_value);
        }

        if (!empty($meta_args)) {
            $query_args['meta_query'] = $meta_args;
        }

        $query_args = apply_filters('iwp/importer/mapper/post_exists_query', $query_args);
        Logger::debug("PostMapper::exists -query=" . wp_json_encode($query_args));

        $query = new \WP_Query($query_args);
        if ($query->post_count > 1) {
            throw new MapperException(sprintf(__("Record is not unique: %s, Matching Ids: (%s).", 'jc-importer'), $unique_field_found, implode(', ', $query->posts)));
        }

        if ($query->post_count == 1) {
            $this->ID = $query->posts[0];
            return $this->ID;
        }

        return false;
    }

    public function insert(ParsedData $data)
    {
        $fields = $data->getData('default');

        $post = array();
        $meta = array();

        // if we are trying to insert a post with a specific id then used import_id instead.
        // if (isset($fields['ID']) && !empty($fields['ID'])) {
        //     $post['import_id'] = $fields['ID'];
        //     unset($fields['ID']);
        // }

        // we dont import the  ID
        if (isset($fields['ID'])) {
            unset($fields['ID']);
        }

        $this->sortFields($fields, $post, $meta);

        // set post_type
        $post['post_type'] = $this->importer->getSetting('post_type');

        Logger::debug('PostMapper::insert -wp_insert_post=' . wp_json_encode($post));

        $this->ID = $this->create_post($post, $data);

        if (is_wp_error($this->ID)) {
            throw new MapperException($this->ID->get_error_message());
        }

        // TODO: Do we merge in the custom fields, or do we process that in post_process
        $this->template->process($this->ID, $data, $this->importer);

        $meta = array_merge($meta, $data->getData('custom_fields'));
        Logger::debug('PostMapper::update -meta=' . wp_json_encode($meta));

        // create post meta
        if ($this->ID && !empty($meta)) {
            foreach ($meta as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $this->add_custom_field($this->ID, $key, $v);
                    }
                } else {
                    $this->update_custom_field($this->ID, $key, $value);
                }
            }
        }

        $this->add_version_tag();
        $this->add_reference_tag($data);
        $this->template->post_process($this->ID, $data);

        clean_post_cache($this->ID);

        return $this->ID;
    }

    public function create_post($post, ParsedData $data)
    {
        return wp_insert_post($post, true);
    }

    public function update(ParsedData $data)
    {
        $fields = $data->getData('default');

        $post_type = $this->importer->getSetting('post_type');

        $post = array();
        $meta = array();

        // we dont import the  ID
        if (isset($fields['ID'])) {
            unset($fields['ID']);
        }

        $this->sortFields($fields, $post, $meta);

        if (!$this->ID) {
            return false;
        }

        // Check to see if trash flag is set, only the importer that removed it can restore it
        $trash_status = get_post_meta($this->ID, '_iwp_trash_status', true);
        if (!empty($trash_status)) {
            $trash_importer_id = get_post_meta($this->ID, '_iwp_trash_importer', true);
            if ($trash_importer_id == $this->importer->getId()) {
                $post['post_status'] = $trash_status;
            } else {
                $trash_status = false;
            }
        }

        // update post type
        if (!empty($post)) {

            // check to see if fields need updating
            $query = new \WP_Query(array(
                'post_type' => $post_type,
                'p' => $this->ID,
                'posts_per_page' => 1,
                'cache_results' => false,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'no_found_rows' => true,
                'post_status' => 'any, trash, future'
            ));
            if ($query->found_posts == 1) {
                $old_post = $query->post;

                foreach ($post as $k => $p) {
                    if ($p == $old_post->$k) {
                        unset($post[$k]);
                    }
                }
            }

            Logger::debug('PostMapper::update -wp_update_post=' . wp_json_encode($post));

            if (!empty($post)) {
                // update remaining
                $post['ID'] = $this->ID;
                $res = wp_update_post($post, true);
                if (is_wp_error($res)) {
                    throw new MapperException($res->get_error_message());
                }
            }
        }

        // TODO: Do we merge in the custom fields, or do we process that in post_process
        $this->template->process($this->ID, $data, $this->importer);

        // update post meta
        $meta = array_merge($meta, $data->getData('custom_fields'));
        Logger::debug('PostMapper::update -meta=' . wp_json_encode($meta));

        if (!empty($meta)) {
            foreach ($meta as $key => $value) {
                $this->clear_custom_field($this->ID, $key);
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $this->add_custom_field($this->ID, $key, $v);
                    }
                } else {
                    $this->update_custom_field($this->ID, $key, $value);
                }
            }
        }

        $this->add_version_tag();
        $this->add_reference_tag($data);

        $this->template->post_process($this->ID, $data);

        // Delete trash flag once post has been updated.
        if (!empty($trash_status)) {
            delete_post_meta($this->ID, '_iwp_trash_status');
            delete_post_meta($this->ID, '_iwp_trash_importer');
        }

        clean_post_cache($this->ID);

        return $this->ID;
    }

    public function get_objects_for_removal()
    {
        if ($this->is_session_tag_enabled()) {
            return $this->get_ids_without_session_tag('pt-' . implode('|', (array)$this->importer->getSetting('post_type')));
        } else {
            $q = new \WP_Query(array(
                'post_type' => $this->importer->getSetting('post_type'),
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
            wp_trash_post($id);
        } else {
            wp_delete_post($id, $force);
        }

        $this->remove_session_tag($id, 'pt-' . implode('|', (array)$this->importer->getSetting('post_type')));
    }

    /**
     * Sort fields into post and meta array
     *
     * @param  array $fields list of fields
     * @param  array $post post_data pointer array
     * @param  array $meta post_meta pointer array
     *
     * @return void
     */
    function sortFields($fields = array(), &$post = array(), &$meta = array())
    {

        foreach ($fields as $id => $value) {

            if (in_array($id, $this->_post_fields, true)) {

                // post field
                $post[$id] = $value;
            } else {

                // meta field
                $meta[$id] = $value;
            }
        }
    }

    /**
     * Clear all post meta before adding custom field
     */
    public function clear_custom_field($post_id, $key)
    {
        delete_post_meta($post_id, $key);
    }

    /**
     * Add custom field, allow for multiple records using the same key
     *
     * @param int $post_id
     * @param string $key
     * @param string $value
     * @return void
     */
    public function add_custom_field($post_id, $key, $value)
    {
        // Stop double serialization
        if (is_serialized($value)) {
            $value = unserialize($value);
        }

        add_post_meta($post_id, $key, $value);
    }

    public function get_custom_field($id, $key = '', $single = false)
    {
        return get_post_meta($id, $key, $single);
    }

    public function update_custom_field($post_id, $key, $value, $unique = false, $skip_permissions = false)
    {

        $old_value = get_post_meta($post_id, $key, true);

        // Stop double serialization
        if (is_serialized($value)) {
            $value = unserialize($value);
        }

        // check if new value
        if ($old_value === $value) {
            return;
        }

        if ($value !== '' && '' == $old_value) {
            add_post_meta($post_id, $key, $value, $unique);
        } elseif ($value !== '' && $value !== $old_value) {
            update_post_meta($post_id, $key, $value);
        } elseif ('' === $value && $old_value) {
            delete_post_meta($post_id, $key, $value);
        }
    }

    public function add_version_tag()
    {
        if ($this->is_session_tag_enabled()) {
            $this->add_session_tag('pt-' . implode('|', (array)$this->importer->getSetting('post_type')));
        } else {
            update_post_meta($this->ID, '_iwp_session_' . $this->importer->getId(), $this->importer->getStatusId());
        }
    }
}
