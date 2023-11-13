<?php

namespace ImportWP\Common\Importer\Mapper;

use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\MapperInterface;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\Template\TemplateManager;
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
        $unique_fields = TemplateManager::get_template_unique_fields($this->template);

        // allow user to set unique field name, get from importer setting
        $unique_field = $this->importer->getSetting('unique_field');
        if ($unique_field !== null) {
            $unique_fields = is_string($unique_field) ? [$unique_field] : $unique_field;
        }

        $unique_fields = $this->getUniqueIdentifiers($unique_fields);
        $unique_fields = apply_filters('iwp/template_unique_fields', $unique_fields, $this->template, $this->importer);

        $unique_field_found = false;

        $taxonomy = $this->importer->getSetting('taxonomy');

        $query_args = [
            'fields' => 'ids',
            'hide_empty' => false,
            'update_term_meta_cache' => false,
            'taxonomy' => $taxonomy
        ];

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
            break;
        }

        if (!$has_unique_field) {
            throw new MapperException("No Unique fields present.");
        }

        if (!empty($meta_args)) {
            $query_args['meta_query'] = $meta_args;
        }

        $query = new \WP_Term_Query($query_args);
        if (!$query->terms) {
            return false;
        }

        if (count($query->terms) > 1) {
            throw new MapperException("Record is not unique: " . $unique_field_found . ", Matching Ids: (" . implode(', ', $query->terms) . ").");
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

        $this->add_version_tag();
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

    public function update_custom_field($id, $key, $value)
    {
        // Stop double serialization
        if (is_serialized($value)) {
            $value = unserialize($value);
        }

        update_term_meta($id, $key, $value);
    }
}
