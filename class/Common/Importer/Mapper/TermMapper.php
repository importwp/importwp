<?php

namespace ImportWP\Common\Importer\Mapper;

use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\MapperInterface;
use ImportWP\Common\Importer\ParsedData;

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
        $fields = $data->getData('default');
        $term = !empty($fields['name']) ? $fields['name'] : false;
        $slug = !empty($fields['slug']) ? $fields['slug'] : false;
        $term_id = !empty($fields['term_id']) ? $fields['term_id'] : false;
        $taxonomy = $this->importer->getSetting('taxonomy');

        // escape if required fields are not entered
        if (false === $taxonomy || (false === $term && false === $slug && false === $term_id)) {
            return false;
        }

        if (!empty($term_id)) {
            $term = get_term_by('id', $term_id, $taxonomy);
        } else if (!empty($slug) && 'yes' !== $data->getValue('slug', '_generated')) {
            // use slug if its not been generated from name
            $term = get_term_by('slug', $slug, $taxonomy);
        } else if (!empty($term)) {
            $term = get_term_by('name', $term, $taxonomy);
        }
        if ($term && isset($term->term_id)) {
            $this->ID = $term->term_id;
            return true;
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

            // returns array('term_id' => #, 'taxonomy_id' => #)
            $insert = wp_insert_term($term, $taxonomy, $args);
            if (is_wp_error($insert)) {
                throw new MapperException($insert->get_error_message());
            }

            $this->ID = $insert['term_id'];

            // TODO: Do we merge in the custom fields, or do we process that in post_process
            $this->template->process($this->ID, $data, $this->importer);

            $custom_fields = array_merge($custom_fields, $data->getData('custom_fields'));

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

        // returns array('term_id' => #, 'taxonomy_id' => #)
        $result = wp_update_term($this->ID, $taxonomy, $args);
        if (is_wp_error($result)) {
            throw new MapperException($result->get_error_message());
        }

        // TODO: Do we merge in the custom fields, or do we process that in post_process
        $this->template->process($this->ID, $data, $this->importer);

        // merge meta group
        $custom_fields = array_merge($custom_fields, $data->getData('custom_fields'));

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
        update_term_meta($this->ID, '_iwp_session_' . $this->importer->getId(), $this->importer->getStatusId());
    }

    public function get_objects_for_removal()
    {
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

        return false;
    }

    public function delete($id)
    {
        wp_delete_term($id, $this->importer->getSetting('taxonomy'));
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
        update_term_meta($id, $key, $value);
    }
}
