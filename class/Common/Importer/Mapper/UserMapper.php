<?php

namespace ImportWP\Common\Importer\Mapper;

use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\MapperInterface;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Util\Logger;

class UserMapper extends AbstractMapper implements MapperInterface
{
    /**
     * Reserved Field Names for user table
     * @var array
     */
    protected $_user_fields = array(
        'ID',
        'user_pass',
        'user_login',
        'user_nicename',
        'user_url',
        'user_email',
        'display_name',
        'nickname',
        'first_name',
        'last_name',
        'description',
        'rich_editing',
        'user_registered',
        'role',
    );
    protected $_user_required = array('user_login');

    public function setup()
    {
    }

    public function teardown()
    {
    }

    public function exists(ParsedData $data)
    {
        list($unique_fields, $meta_args, $has_unique_field) = $this->exists_get_identifier($data);
        if (!empty($meta_args)) {
            foreach ($meta_args as &$meta_arg_v) {
                $meta_arg_v['compare'] = '=';
                $meta_arg_v['type'] = 'CHAR';
            }
        }

        $unique_field_found = false;

        $query_args = array();
        $search         = array(); // store search values
        $search_columns = array(); // store search columns

        if (!$has_unique_field) {
            foreach ($unique_fields as $field) {

                // check all groups for a unique value
                $unique_value = $this->find_unique_field_in_data($data, $field);

                if (!empty($unique_value)) {
                    $has_unique_field = true;

                    if (in_array($field, ['ID', 'user_login', 'user_nicename', 'user_email', 'user_url'])) {
                        $search_columns[] = $field;
                        $search[]         = $unique_value;
                    } else {
                        $meta_args[] = array(
                            'key'     => $field,
                            'value'   => $unique_value,
                            'compare' => '=',
                            'type'    => 'CHAR'
                        );
                    }
                    $unique_field_found = $field;
                    $this->set_unique_identifier_settings($unique_field_found, $unique_value);
                    break;
                }
            }
        }

        if (!$has_unique_field) {
            throw new MapperException(__("No Unique fields present.", 'jc-importer'));
        }

        // create search
        $query_args['search']         = implode(', ', $search);
        $query_args['search_columns'] = $search_columns;
        $query_args['meta_query']     = $meta_args;
        $query_args = apply_filters('iwp/importer/mapper/user_exists_query', $query_args);
        Logger::debug("UserMapper::exists -query=" . wp_json_encode($query_args));
        $query = new \WP_User_Query($query_args);

        if ($query->total_users > 1) {
            $ids = [];
            foreach ($query->results as $result) {
                $ids[] = $result->ID;
            }
            throw new MapperException(sprintf(__("Record is not unique: %s, Matching Ids: (%s).", 'jc-importer'), $unique_field_found, implode(', ', $ids)));
        }

        if ($query->total_users == 1) {
            $this->ID = $query->results[0]->ID;
            return $this->ID;
        }
        return false;
    }

    public function insert(ParsedData $data)
    {
        $fields = $data->getData('default');

        // ID Cant be inserted or updated, it is just used for reference
        if (isset($fields['ID'])) {
            unset($fields['ID']);
        }

        $core = [];
        $meta = [];

        foreach ($fields as $field_name => $field_value) {
            if (in_array($field_name, $this->_user_fields)) {
                $core[$field_name] = $field_value;
            } else {
                $meta[$field_name] = $field_value;
            }
        }

        if (!isset($core['user_login']) || empty($core['user_login'])) {
            throw new MapperException(__("No username present", 'jc-importer'));
        }

        if (!isset($core['user_pass']) || empty($core['user_pass'])) {
            throw new MapperException(__("No password present", 'jc-importer'));
        }

        if (!empty($core['user_email']) && !is_email($core['user_email'])) {
            throw new MapperException(sprintf(__("%s is not a valid email address", 'jc-importer'), strval($core['user_email'])));
        }

        Logger::debug('UserMapper::insert -wp_insert_user=' . wp_json_encode($core));

        $this->ID = wp_insert_user($core);
        if (is_wp_error($this->ID)) {
            throw new MapperException($this->ID->get_error_message());
        }

        // TODO: Do we merge in the custom fields, or do we process that in post_process
        $this->template->process($this->ID, $data, $this->importer);

        // TODO: merge in custom fields from $data->getData('custom_fields');
        $meta = array_merge($meta, $data->getData('custom_fields'));
        Logger::debug('UserMapper::insert -meta=' . wp_json_encode($meta));

        // update user meta
        if (!empty($meta)) {
            foreach ($meta as $key => $value) {
                if (!in_array($key, $this->_user_fields)) {
                    if (is_array($value)) {
                        foreach ($value as $v) {
                            $this->add_custom_field($this->ID, $key, $v);
                        }
                    } else {
                        $this->update_custom_field($this->ID, $key, $value);
                    }
                }
            }
        }

        $this->add_version_tag();
        $this->add_reference_tag($data);
        $this->template->post_process($this->ID, $data);

        return $this->ID;
    }

    public function update(ParsedData $data)
    {
        $fields = $data->getData('default');

        // ID Cant be inserted or updated, it is just used for reference
        if (isset($fields['ID'])) {
            unset($fields['ID']);
        }

        $core = [];
        $meta = [];

        foreach ($fields as $field_name => $field_value) {
            if (in_array($field_name, $this->_user_fields)) {
                $core[$field_name] = $field_value;
            } else {
                $meta[$field_name] = $field_value;
            }
        }

        Logger::debug('UserMapper::update -wp_update_user=' . wp_json_encode($core));

        if (!empty($core)) {
            $core['ID'] = $this->ID;
            $result = wp_update_user($core);
            if (is_wp_error($result)) {
                throw new MapperException($result->get_error_message());
            }
        }

        // TODO: Do we merge in the custom fields, or do we process that in post_process
        $this->template->process($this->ID, $data, $this->importer);

        $meta = array_merge($meta, $data->getData('custom_fields'));
        Logger::debug('UserMapper::update -meta=' . wp_json_encode($meta));

        // update user meta
        if (!empty($meta)) {
            foreach ($meta as $key => $value) {
                if (!in_array($key, $this->_user_fields)) {
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
        }

        $this->add_version_tag();
        $this->add_reference_tag($data);
        $this->template->post_process($this->ID, $data);

        return $this->ID;
    }

    public function get_objects_for_removal()
    {
        if ($this->is_session_tag_enabled()) {
            return $this->get_ids_without_session_tag('user');
        } else {
            $wp_user_query = new \WP_User_Query([
                'fields' => 'ID',
                'meta_query' => array(
                    array(
                        'key' => '_iwp_session_' . $this->importer->getId(),
                        'value' => $this->importer->getStatusId(),
                        'compare' => '!='
                    )
                ),
                'number' => -1
            ]);

            $results = $wp_user_query->get_results();
            if (!empty($results)) {
                return $results;
            }
        }

        return false;
    }

    public function delete($id)
    {
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($id);

        $this->remove_session_tag($id, 'user');
    }

    public function get_custom_field($id, $key = '', $single = false)
    {
        return get_user_meta($id, $key, $single);
    }

    /**
     * Clear all post meta before adding custom field
     */
    public function clear_custom_field($user_id, $key)
    {
        delete_user_meta($user_id, $key);
    }

    /**
     * Add custom field, allow for multiple records using the same key
     *
     * @param int $user_id
     * @param string $key
     * @param string $value
     * @return void
     */
    public function add_custom_field($user_id, $key, $value)
    {
        // Stop double serialization
        if (is_serialized($value)) {
            $value = unserialize($value);
        }

        add_user_meta($user_id, $key, $value);
    }

    public function update_custom_field($id, $key, $value, $unique = false, $skip_permissions = false)
    {
        // Stop double serialization
        if (is_serialized($value)) {
            $value = unserialize($value);
        }

        update_user_meta($id, $key, $value);
    }

    public function add_version_tag()
    {
        if ($this->is_session_tag_enabled()) {
            $this->add_session_tag('user');
        } else {
            update_user_meta($this->ID, '_iwp_session_' . $this->importer->getId(), $this->importer->getStatusId());
        }
    }
}
