<?php

namespace ImportWP\Common\Importer\Template;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\TemplateInterface;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\EventHandler;

class TermTemplate extends Template implements TemplateInterface
{
    protected $name = 'Term';
    protected $mapper = 'term';

    protected $field_map = [
        'term_id' => 'term.term_id',
        'name' => 'term.name',
        'description' => 'term.description',
        'slug' => 'term.slug',
        'alias_of' => 'term.alias_of'
    ];

    protected $optional_fields = [
        'term_id',
        'description',
        'slug',
        'alias_of',
    ];

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
        $options = [
            ['value' => '', 'label' => 'Choose a taxonomy']
        ];
        foreach (get_taxonomies(array(), 'names') as $key => $tax) {
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
            $this->register_group(__('Term', 'jc-importer'), 'term', [
                $this->register_field(__('ID', 'jc-importer'), 'term_id', [
                    'tooltip' => __('ID is only used to reference existing records', 'jc-importer')
                ]),
                $this->register_core_field(__('Name', 'jc-importer'), 'name', [
                    'tooltip' => __('The term name', 'jc-importer')
                ]),
                $this->register_field(__('Description', 'jc-importer'), 'description', [
                    'tooltip' => __('The term description', 'jc-importer')
                ]),
                $this->register_field(__('Slug', 'jc-importer'), 'slug', [
                    'tooltip' => __('The term slug to use', 'jc-importer')
                ]),
                $this->register_group(__('Parent Settings', 'jc-importer'), '_parent', [
                    $this->register_field(__('Parent', 'jc-importer'), 'parent', [
                        'default' => '',
                        'options' => 'callback',
                        'tooltip' => __('The id of the parent term', 'jc-importer')
                    ]),
                    $this->register_field(__('Parent Field Type', 'jc-importer'), '_parent_type', [
                        'default' => 'id',
                        'options' => [
                            ['value' => 'id', 'label' => __('ID', 'jc-importer')],
                            ['value' => 'slug', 'label' => __('Slug', 'jc-importer')],
                            ['value' => 'name', 'label' => __('Name', 'jc-importer')],
                            ['value' => 'column', 'label' => __('Reference Column', 'jc-importer')],
                            ['value' => 'custom_field', 'label' => __('Custom Field', 'jc-importer')],
                        ],
                        'type' => 'select',
                        'tooltip' => __('Select how the parent field should be handled', 'jc-importer')
                    ]),
                    $this->register_field(__('Parent Reference Column', 'jc-importer'), '_parent_ref', [
                        'condition' => ['_parent_type', '==', 'column'],
                        'tooltip' => __('Select the column/node that the parent field is referencing', 'jc-importer')
                    ]),
                    $this->register_field(__('Custom Field Key', 'jc-importer'), '_custom_field', [
                        'condition' => ['_parent_type', '==', 'custom_field'],
                        'tooltip' => __('Enter the name of the custom field.', 'jc-importer')
                    ])
                ]),
                $this->register_field(__('Alias of', 'jc-importer'), 'alias_of', [
                    'tooltip' => __('Slug of the term to make this term an alias of', 'jc-importer')
                ]),
            ], ['link' => 'https://www.importwp.com/docs/wordpress-taxonomy-importer-template/'])
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
                $temp_id = $this->get_term_by_cf('_iwp_ref_term_parent', $term_field_map['parent']);
                if (intval($temp_id > 0)) {
                    $parent_id = intval($temp_id);
                }
            } elseif ($parent_field_type === 'custom_field') {

                $parent_custom_field = $data->getValue('term._parent._custom_field');
                $temp_id = $this->get_term_by_cf($parent_custom_field, $term_field_map['parent']);
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
                    'key' =>  $field,
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

    /**
     * Convert fields/headings to data map
     * 
     * @param mixed $fields
     * @param ImporterModel $importer
     * @return array 
     */
    public function generate_field_map($fields, $importer)
    {
        $result = parent::generate_field_map($fields, $importer);
        $map = $result['map'];
        $enabled = $result['enabled'];

        $parent = [];

        foreach ($fields as $index => $field) {
            if (preg_match('/^parent\.(.*?)$/', $field, $matches) === 1) {

                // Capture parent
                // parent.id,parent.name,parent.slug
                if (!isset($parent['map'])) {
                    $parent['map'] = [];
                }

                $parent['map'][$matches[1]] = sprintf('{%s}', $index);
            } elseif (isset($this->field_map[$field])) {

                // Handle core fields
                $field_key = $this->field_map[$field];
                $map[$field_key] = sprintf('{%s}', $index);

                if (in_array($field, $this->optional_fields)) {
                    $enabled[] = $field_key;
                }

                if (in_array($field, ['role'])) {
                    $map[$field_key . '._enable_text'] = 'yes';
                }
            }
        }

        if (!empty($parent)) {

            // parent.id,parent.name,parent.slug
            $enabled_key = 'term._parent';
            if (isset($parent['map']['name'])) {

                $enabled[] = $enabled_key;
                $map['term._parent.parent'] = $parent['map']['name'];
                $map['term._parent.parent._enable_text'] = 'yes';
                $map['term._parent._parent_type'] = 'name';
            } elseif (isset($parent['map']['slug'])) {

                $enabled[] = $enabled_key;
                $map['term._parent.parent'] = $parent['map']['slug'];
                $map['term._parent.parent._enable_text'] = 'yes';
                $map['term._parent._parent_type'] = 'slug';
            } elseif (isset($parent['map']['id'])) {

                $enabled[] = $enabled_key;
                $map['term._parent.parent'] = $parent['map']['name'];
                $map['term._parent.parent._enable_text'] = 'yes';
                $map['term._parent._parent_type'] = 'id';
            }
        }

        return [
            'map' => $map,
            'enabled' => $enabled
        ];
    }

    public function get_permission_fields($importer_model)
    {
        $permission_fields = parent::get_permission_fields($importer_model);

        $permission_fields['core'] = [
            'term_id' => __('ID', 'jc-importer'),
            'name' => __('Name', 'jc-importer'),
            'description' => __('Description', 'jc-importer'),
            'slug' => __('Slug', 'jc-importer'),
            'alias_of' => __('Alias Of', 'jc-importer'),
            'parent' => __('Parent', 'jc-importer'),
        ];

        return $permission_fields;
    }

    public function get_unique_identifier_options($importer_model, $unique_fields = [])
    {
        $output = parent::get_unique_identifier_options($importer_model, $unique_fields);

        return array_merge(
            $output,
            $this->get_unique_identifier_options_from_map($importer_model, $unique_fields, $this->field_map, $this->optional_fields)
        );
    }
}
