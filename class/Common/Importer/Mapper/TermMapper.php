<?php

namespace ImportWP\Common\Importer\Mapper;

use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\MapperInterface;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Util\Logger;

class TermMapper extends AbstractMapper implements MapperInterface
{
    protected $_term_fields = array(
        'term_id',
        'alias_of',
        'description',
        'parent',
        'slug',
        'name'
    );

    public function setup()
    {
    }

    public function teardown()
    {
    }

    public function exists(ParsedData $data)
    {
        list($unique_fields, $meta_args, $has_unique_field) = $this->exists_get_identifier($data);
        $unique_field_found = false;

        $taxonomy = $this->importer->getSetting('taxonomy');
        $query_args = [
            'fields' => 'ids',
            'hide_empty' => false,
            'update_term_meta_cache' => false,
            'taxonomy' => $taxonomy
        ];

        if (!$has_unique_field) {
            foreach ($unique_fields as $field) {

                // check all groups for a unique value
                $unique_value = $this->find_unique_field_in_data($data, $field);

                if (empty($unique_value)) {
                    continue;
                }

                $has_unique_field = true;

                if (in_array($field, $this->_term_fields, true)) {

                    switch ($field) {
                        case 'term_id':
                            $query_args['include'] = [intval($unique_value)];
                            break;
                        default:
                            $query_args[$field] = $unique_value;
                            break;
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

        if (!$has_unique_field) {
            throw new MapperException(__("No Unique fields present.", 'jc-importer'));
        }

        if (!empty($meta_args)) {
            $query_args['meta_query'] = $meta_args;
        }

        $query_args = apply_filters('iwp/importer/mapper/term_exists_query', $query_args);
        Logger::debug("TermMapper::exists -query=" . wp_json_encode($query_args));
        $query = new \WP_Term_Query($query_args);
        if (!$query->terms) {
            return false;
        }

        if (count($query->terms) > 1) {
            throw new MapperException(sprintf(__("Record is not unique: %s, Matching Ids: (%s).", 'jc-importer'), $unique_field_found, implode(', ', $query->terms)));
        }

        if (count($query->terms) == 1) {
            $this->ID = $query->terms[0];
            return $this->ID;
        }

        return false;
    }

    public function insert(ParsedData $data)
    {
        $fields = $data->getData('default');

        $this->ID = false;
        $args = array();
        $custom_fields = array();

        // term_id Cant be inserted or updated, it is just used for reference
        if (isset($fields['term_id'])) {
            unset($fields['term_id']);
        }

        // escape if required fields are not entered
        if (!isset($fields['name']) || empty($fields['name'])) {
            throw new MapperException('Term name is missing.');
        }

        $term = !empty($fields['name']) ? $fields['name'] : false;
        $taxonomy = $this->importer->getSetting('taxonomy');

        if ($term && $taxonomy) {

            unset($fields['name']);
            unset($fields['taxonomy']);
            foreach ($fields as $key => $value) {
                //
                if (empty($value)) {
                    continue;
                }
                if (in_array($key, $this->_term_fields)) {
                    $args[$key] = $value;
                } else {
                    $custom_fields[$key] = $value;
                }
            }

            Logger::debug('TermMapper::insert -term=' . $term . ' -tax=' . $taxonomy . ' -wp_insert_term=' . wp_json_encode($args));

            $insert = wp_insert_term($term, $taxonomy, $args);
            if (is_wp_error($insert)) {
                throw new MapperException($insert->get_error_message());
            }

            $this->ID = $insert['term_id'];

            // TODO: Do we merge in the custom fields, or do we process that in post_process
            $this->template->process($this->ID, $data, $this->importer);

            $custom_fields = array_merge($custom_fields, $data->getData('custom_fields'));
            Logger::debug('TermMapper::insert -meta=' . wp_json_encode($custom_fields));

            if (!is_wp_error($this->ID) && intval($this->ID) > 0 && !empty($custom_fields)) {
                foreach ($custom_fields as $key => $value) {
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
        $args          = array();
        $custom_fields = array();

        // term_id Cant be inserted or updated, it is just used for reference
        if (isset($fields['term_id'])) {
            unset($fields['term_id']);
        }

        $taxonomy = $this->importer->getSetting('taxonomy');
        foreach ($fields as $key => $value) {
            //
            if (empty($value)) {
                continue;
            }
            if (in_array($key, $this->_term_fields)) {
                $args[$key] = $value;
            } else {
                $custom_fields[$key] = $value;
            }
        }

        Logger::debug('TermMapper::update -tax=' . $taxonomy . ' -wp_update_term=' . wp_json_encode($args));

        $result = wp_update_term($this->ID, $taxonomy, $args);
        if (is_wp_error($result)) {
            throw new MapperException($result->get_error_message());
        }

        // TODO: Do we merge in the custom fields, or do we process that in post_process
        $this->template->process($this->ID, $data, $this->importer);

        // merge meta group
        $custom_fields = array_merge($custom_fields, $data->getData('custom_fields'));
        Logger::debug('TermMapper::update -meta=' . wp_json_encode($custom_fields));

        if (!empty($custom_fields)) {
            foreach ($custom_fields as $key => $value) {
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

        return $this->ID;
    }

    public function add_version_tag()
    {
        if ($this->is_session_tag_enabled()) {
            $this->add_session_tag('t-' . $this->importer->getSetting('taxonomy'));
        } else {
            update_term_meta($this->ID, '_iwp_session_' . $this->importer->getId(), $this->importer->getStatusId());
        }
    }

    public function get_objects_for_removal()
    {
        if ($this->is_session_tag_enabled()) {
            return $this->get_ids_without_session_tag('t-' . $this->importer->getSetting('taxonomy'));
        } else {
            $taxonomy = $this->importer->getSetting('taxonomy');
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'fields' => 'ids',
                'hide_empty' => false,
                'meta_query' => [
                    [
                        'key' =>  '_iwp_session_' . $this->importer->getId(),
                        'value' => $this->importer->getStatusId(),
                        'compare' => '!='
                    ]
                ]
            ]);

            if (!is_wp_error($terms)) {
                return $terms;
            }
        }

        return false;
    }

    public function delete($id)
    {
        wp_delete_term($id, $this->importer->getSetting('taxonomy'));

        $this->remove_session_tag($id, 't-' . $this->importer->getSetting('taxonomy'));
    }

    /**
     * Clear all post meta before adding custom field
     */
    public function clear_custom_field($term_id, $key)
    {
        delete_term_meta($term_id, $key);
    }

    /**
     * Add custom field, allow for multiple records using the same key
     *
     * @param int $term_id
     * @param string $key
     * @param string $value
     * @return void
     */
    public function add_custom_field($term_id, $key, $value)
    {
        // Stop double serialization
        if (is_serialized($value)) {
            $value = unserialize($value);
        }

        add_term_meta($term_id, $key, $value);
    }

    public function get_custom_field($id, $key = '', $single = false)
    {
        return get_term_meta($id, $key, $single);
    }

    public function update_custom_field($id, $key, $value, $unique = false, $skip_permissions = false)
    {
        // Stop double serialization
        if (is_serialized($value)) {
            $value = unserialize($value);
        }

        update_term_meta($id, $key, $value);
    }
}
