<?php

namespace ImportWP\Common\Importer\Template;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\TemplateInterface;
use ImportWP\EventHandler;

class TermTemplate extends Template implements TemplateInterface
{
    protected $name = 'Term';
    protected $mapper = 'term';

    public function __construct(EventHandler $event_handler)
    {
        parent::__construct($event_handler);

        $this->groups[] = 'term';
        $this->field_options = array_merge($this->field_options, [
            'term._parent.parent' => [$this, 'get_term_parent_options'],
        ]);
    }

    public function register_options()
    {
        $custom_taxonomies = get_taxonomies(['public' => true, '_builtin' => false], 'names');
        $options = [
            ['value' => '', 'label' => 'Choose a taxonomy'],
            ['value' => 'post_tag', 'label' => 'post_tag'],
            ['value' => 'category', 'label' => 'category'],
        ];
        foreach ($custom_taxonomies as $key => $tax) {
            $options[] = ['value' => $key, 'label' => $tax];
        }

        return [
            $this->register_field('Taxonomy', 'taxonomy', [
                'options' => $options
            ])
        ];
    }

    public function register()
    {
        return [
            $this->register_group('Term', 'term', [
                $this->register_field('ID', 'term_id', [
                    'tooltip' => __('ID is only used to reference existing records', 'importwp')
                ]),
                $this->register_core_field('Name', 'name', [
                    'tooltip' => __('The term name', 'importwp')
                ]),
                $this->register_field('Description', 'description', [
                    'tooltip' => __('The term description', 'importwp')
                ]),
                $this->register_field('Slug', 'slug', [
                    'tooltip' => __('The term slug to use', 'importwp')
                ]),
                $this->register_group('Parent Settings', '_parent', [
                    $this->register_field('Parent', 'parent', [
                        'default' => '',
                        'options' => 'callback',
                        'tooltip' => __('The id of the parent term', 'importwp')
                    ]),
                    $this->register_field('Parent Field Type', '_parent_type', [
                        'default' => 'id',
                        'options' => [
                            ['value' => 'id', 'label' => 'ID'],
                            ['value' => 'slug', 'label' => 'Slug'],
                            ['value' => 'name', 'label' => 'Name'],
                            ['value' => 'column', 'label' => 'Reference Column'],
                        ],
                        'type' => 'select',
                        'tooltip' => __('Select how the parent field should be handled', 'importwp')
                    ]),
                    $this->register_field('Parent Reference Column', '_parent_ref', [
                        'condition' => ['_parent_type', '==', 'column'],
                        'tooltip' => __('Select the column/node that the parent field is referencing', 'importwp')
                    ])
                ]),
                $this->register_field('Alias of', 'alias_of', [
                    'tooltip' => __('Slug of the term to make this term an alias of', 'importwp')
                ]),
            ])
        ];
    }

    public function register_settings()
    {
    }

    /**
     * Alter fields before they are parsed
     *
     * @param array $fields
     * @return array
     */
    public function field_map($fields)
    {
        if ($this->importer->isEnabledField('term._parent') && $fields['term._parent._parent_type'] === 'column' && !empty($fields["term._parent._parent_ref"])) {
            $fields['_iwp_ref_term_parent'] = $fields["term._parent._parent_ref"];
            $this->virtual_fields[] = '_iwp_ref_term_parent';
        }
        return $fields;
    }

    /**
     * Process data before record is importer.
     * 
     * Alter data that is passed to the mapper.
     *
     * @param ParsedData $data
     * @return ParsedData
     */
    public function pre_process(ParsedData $data)
    {
        $data = parent::pre_process($data);

        $term_field_map = [
            'term_id' => $data->getValue('term.term_id'),
            'name' => $data->getValue('term.name'),
            'alias_of' => $data->getValue('term.alias_of'),
            'description' => $data->getValue('term.description'),
            'parent' => $data->getValue('term._parent.parent'),
            'slug' => $data->getValue('term.slug'),
        ];

        // remove fields that have not been set
        foreach ($term_field_map as $field_key => $field_map) {

            if ($field_map === false) {
                unset($term_field_map[$field_key]);
                continue;
            }
        }

        $optional_fields = [
            'term_id',
            'alias_of',
            'description',
            'slug'
        ];

        foreach ($optional_fields as $optional_field) {
            if (true !== $this->importer->isEnabledField('term.' . $optional_field)) {
                unset($term_field_map[$optional_field]);
            }
        }

        if (true !== $this->importer->isEnabledField('term._parent')) {
            unset($term_field_map['parent']);
        }

        if (!empty($this->virtual_fields)) {
            foreach ($this->virtual_fields as $virtual_field) {
                $value = $data->getValue($virtual_field);
                if (false !== $value) {
                    $term_field_map[$virtual_field] = $value;
                }
            }
        }

        if ($this->importer->isEnabledField('term._parent') && isset($term_field_map['parent'])) {

            $parent_id = 0;
            $parent_field_type = $data->getValue('term._parent._parent_type');
            $taxonomy = $this->importer->getSetting('taxonomy');

            if ($parent_field_type === 'name') {
                // name 
                $term = get_term_by('name', $term_field_map['parent'], $taxonomy);
                if ($term) {
                    $parent_id = intval($term->term_id);
                }
            } elseif ($parent_field_type === 'slug') {
                // slug
                $term = get_term_by('slug', $term_field_map['parent'], $taxonomy);
                if ($term) {
                    $parent_id = intval($term->term_id);
                }
            } elseif ($parent_field_type === 'id') {
                // ID
                $parent_id = intval($term_field_map['parent']);
            } elseif ($parent_field_type === 'column') {

                // reference column
                $temp_id = $this->get_term_by_cf('term_parent', $term_field_map['parent']);
                if (intval($temp_id > 0)) {
                    $parent_id = intval($temp_id);
                }
            }

            if ($parent_id > 0) {
                $term_field_map['parent'] = $parent_id;
            }
        }

        if ((!isset($term_field_map['slug']) || empty($term_field_map['slug'])) && isset($term_field_map['term']) && !empty($term_field_map['term'])) {
            $term_field_map['slug'] = sanitize_title($term_field_map['term']);

            // set flag to say slug has been generated
            $data->add(['slug' => 'yes'], '_generated');
        }

        foreach ($term_field_map as $key => $value) {
            $term_field_map[$key] = apply_filters('iwp/template/process_field', $value, $key, $this->importer);
        }

        $data->replace($term_field_map, 'default');

        return $data;
    }

    public function get_term_by_cf($field, $value)
    {
        $taxonomy = $this->importer->getSetting('taxonomy');
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'fields' => 'ids',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' =>  '_iwp_ref_' . $field,
                    'value' => $value,
                    'compare' => '='
                ]
            ]
        ]);

        if (!is_wp_error($terms)) {
            return $terms[0];
        }

        return false;
    }

    /**
     * Get list of posts
     *
     * @return array|\WP_Error
     */
    public function get_term_parent_options($importer_data)
    {
        $taxonomy = $importer_data->getSetting('taxonomy');
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);

        $result = [];
        /**
         * @var \WP_Term[] $terms
         */
        foreach ($terms as $term) {
            $result[] = [
                'value' => '' . $term->term_id,
                'label' => $term->name
            ];
        }
        return $result;
    }
}
