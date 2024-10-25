<?php

namespace ImportWP\Common\Importer\Template;

use ImportWP\Common\Attachment\Attachment;
use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Ftp\Ftp;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\TemplateInterface;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Common\Util\Logger;
use ImportWP\Container;
use ImportWP\EventHandler;

class PostTemplate extends Template implements TemplateInterface
{
    protected $name = 'Post';
    protected $mapper = 'post';
    protected $field_map = [
        'ID' => 'post.ID',
        'post_name' => 'post.post_name',
        'post_title' => 'post.post_title',
        'post_content' => 'post.post_content',
        'post_excerpt' => 'post.post_excerpt',
        'post_status' => 'post.post_status',
        'menu_order' => 'post.menu_order',
        'post_password' => 'post.post_password',
        'post_date' => 'post.post_date',
        'comment_status' => 'post.comment_status',
        'ping_status' => 'post.ping_status',
        'post_parent' => 'post._parent.parent',
    ];

    protected $optional_fields = [
        'ID',
        'post_excerpt',
        'post_name',
        'post_status',
        'menu_order',
        'post_password',
        'post_date',
        'comment_status',
        'ping_status'
    ];

    protected $_taxonomies = [];
    protected $_attachments = [];

    private $virtual_fields = [];

    public function __construct(EventHandler $event_handler)
    {
        parent::__construct($event_handler);

        $this->groups[] = 'post';
        $this->groups[] = 'taxonomies';
        $this->groups[] = 'attachments';

        $this->default_template_options['post_type'] = 'post';

        $this->field_options = array_merge($this->field_options, [
            'post._parent.parent' => [$this, 'get_post_parent_options'],
            'taxonomies.*.tax' => [$this, 'get_taxonomy_options'],
        ]);

        $this->default_enabled_fields[] = 'post.post_status';
    }

    /**
     * @param string $message
     * @param int $id
     * @param ParsedData $data
     * @return $string
     */
    public function display_record_info($message, $id, $data)
    {
        $message = parent::display_record_info($message, $id, $data);

        if (!empty($this->_taxonomies)) {
            foreach ($this->_taxonomies as $tax => $terms) {
                $message .= ', ' . $tax . ': (' . implode(', ', $terms) . ')';
            }
        }

        if (!empty($this->_attachments)) {
            $message .= sprintf(__(', Attachments: (%s)', 'jc-importer'), implode(', ', $this->_attachments));
        }

        return $message;
    }

    public function register()
    {
        $groups = [];

        // Post
        $groups[] = $this->register_group(__('Post Fields', 'jc-importer'), 'post', [
            $this->register_field(__('ID', 'jc-importer'), 'ID', [
                'tooltip' => __('ID is only used to reference existing records', 'jc-importer')
            ]),
            $this->register_core_field(__('Title', 'jc-importer'), 'post_title', [
                'tooltip' => __('Title of the post.', 'jc-importer')
            ]),
            $this->register_core_field(__('Content', 'jc-importer'), 'post_content', [
                'tooltip' => __('Main WYSIWYG editor content of the post.', 'jc-importer')
            ]),
            $this->register_field(__('Excerpt', 'jc-importer'), 'post_excerpt', [
                'tooltip' => __('A custom short extract for the post.', 'jc-importer')
            ]),
            $this->register_field(__('Slug', 'jc-importer'), 'post_name', [
                'tooltip' => __('The slug is the user friendly and URL valid name of the post.', 'jc-importer')
            ]),
            $this->register_field(__('Status', 'jc-importer'), 'post_status', [
                'default' => 'publish',
                'options'         => [
                    ['value' => 'draft', 'label' => __('Draft', 'jc-importer')],
                    ['value' => 'publish', 'label' => __('Published', 'jc-importer')],
                    ['value' => 'pending', 'label' => __('Pending', 'jc-importer')],
                    ['value' => 'future', 'label' => __('Future', 'jc-importer')],
                    ['value' => 'private', 'label' => __('Private', 'jc-importer')],
                    ['value' => 'trash', 'label' => __('Trash', 'jc-importer')]
                ],
                'tooltip' => __('The status of a given post determines how WordPress handles that post', 'jc-importer')
            ]),
            $this->register_group(__('Parent Settings', 'jc-importer'), '_parent', [
                $this->register_field(__('Parent', 'jc-importer'), 'parent', [
                    'default' => '',
                    'options' => 'callback',
                    'tooltip' => __('Set this for the post it belongs to', 'jc-importer')
                ]),
                $this->register_field(__('Parent Field Type', 'jc-importer'), '_parent_type', [
                    'default' => 'id',
                    'options' => [
                        ['value' => 'id', 'label' => __('ID', 'jc-importer')],
                        ['value' => 'slug', 'label' => __('Slug', 'jc-importer')],
                        ['value' => 'name', 'label' => __('Name', 'jc-importer')],
                        ['value' => 'column', 'label' => __('Reference Column', 'jc-importer')],
                    ],
                    'type' => 'select',
                    'tooltip' => __('Select how the parent field should be handled', 'jc-importer')
                ]),
                $this->register_field(__('Parent Reference Column', 'jc-importer'), '_parent_ref', [
                    'condition' => ['_parent_type', '==', 'column'],
                    'tooltip' => __('Select the column/node that the parent field is referencing', 'jc-importer')
                ])
            ]),
            $this->register_field(__('Order', 'jc-importer'), 'menu_order', [
                'tooltip' => __('The order the post should be displayed in', 'jc-importer')
            ]),
            $this->register_group(__('Author Settings', 'jc-importer'), '_author', [
                $this->register_field(__('Author', 'jc-importer'), 'post_author', [
                    'tooltip' => __('The user of who added this post', 'jc-importer')
                ]),
                $this->register_field(__('Author Field Type', 'jc-importer'), '_author_type', [
                    'default' => 'id',
                    'options' => [
                        ['value' => 'id', 'label' => __('ID', 'jc-importer')],
                        ['value' => 'login', 'label' => __('Login', 'jc-importer')],
                        ['value' => 'email', 'label' => __('Email', 'jc-importer')],
                    ],
                    'tooltip' => __('Select how the author field should be handled', 'jc-importer')
                ])
            ]),
            $this->register_field(__('Password', 'jc-importer'), 'post_password', [
                'tooltip' => __('The password to access the post', 'jc-importer')
            ]),
            $this->register_field(__('Date', 'jc-importer'), 'post_date', [
                'tooltip' => __('The date of the post , enter in the format "YYYY-MM-DD HH:ii:ss"', 'jc-importer')
            ]),
            $this->register_field(__('Allow Comments', 'jc-importer'), 'comment_status', [
                'options' => [
                    ['value' => '0', 'label' => __('Disabled', 'jc-importer')],
                    ['value' => '1', 'label' => __('Enabled', 'jc-importer')]
                ],
                'default' => '0',
                'tooltip' => __('Whether the post can accept comments', 'jc-importer')
            ]),
            $this->register_field(__('Allow Pingbacks', 'jc-importer'), 'ping_status', [
                'options' => [
                    ['value' => 'closed', 'label' => __('Closed', 'jc-importer')],
                    ['value' => 'open', 'label' => __('Open', 'jc-importer')]
                ],
                'default' => 'closed',
                'tooltip' => __('Whether the post can accept pings', 'jc-importer')
            ])
        ], ['link' => 'https://www.importwp.com/docs/wordpress-page-importer-template/']);

        // Taxonomies
        $groups[] = $this->register_taxonomy_fields();

        // Attachments
        $groups[] = $this->register_attachment_fields();

        return $groups;
    }

    public function register_taxonomy_fields()
    {
        return $this->register_group(__('Taxonomies', 'jc-importer'), 'taxonomies', [
            $this->register_field(__('Taxonomy', 'jc-importer'), 'tax', [
                'default' => 'category',
                'options' => 'callback',
                'tooltip' => __('Select the type of taxonomy you are importing to.', 'jc-importer')
            ]),
            $this->register_field(__('Terms', 'jc-importer'), 'term', [
                'tooltip' => __('Name of the taxonomy term or terms (entered as a comma seperated list).', 'jc-importer')
            ]),
            $this->register_group(__('Settings', 'jc-importer'), 'settings', [
                $this->register_field(__('Delimiter', 'jc-importer'), '_delimiter', [
                    'type' => 'text',
                    'tooltip' => sprintf(__('A single character used to seperate terms when listing multiple, Leave empty to use default: %s', 'jc-importer'), ',')
                ]),
                $this->register_field(__('Term Type', 'jc-importer'), '_type', [
                    'tooltip' => __('Select what type the term values are (e.g. Name, Slug, or ID)', 'jc-importer'),
                    'default' => 'name',
                    'options' => [
                        ['value' => 'name', 'label' => __('Name', 'jc-importer')],
                        ['value' => 'slug', 'label' => __('Slug', 'jc-importer')],
                        ['value' => 'term_id', 'label' => __('ID', 'jc-importer')],
                        ['value' => 'custom_field', 'label' => __('Custom Field', 'jc-importer')],
                    ],
                    'type' => 'select'
                ]),
                $this->register_field(__('Custom Field', 'jc-importer'), '_type_cf', [
                    'condition' => [
                        ['_type', '==', 'custom_field']
                    ]
                ]),
                $this->register_field(__('Enable Hierarchy', 'jc-importer'), '_hierarchy', [
                    'default' => 'no',
                    'options' => [
                        ['value' => 'no', 'label' => __('No', 'jc-importer')],
                        ['value' => 'yes', 'label' => __('Yes', 'jc-importer')],
                    ],
                    'type' => 'select'
                ]),
                $this->register_field(__('Hierarchy Character', 'jc-importer'), '_hierarchy_character', [
                    'default' => '>',
                    'condition' => ['_hierarchy', '==', 'yes'],
                ]),
                $this->register_field(__('Hierarchy Relationship', 'jc-importer'), '_hierarchy_relationship', [
                    'default' => 'all',
                    'options' => [
                        ['value' => 'all', 'label' => __('Connect all terms', 'jc-importer')],
                        ['value' => 'last', 'label' => __('Connect last term', 'jc-importer')],
                    ],
                    'type' => 'select',
                    'condition' => ['_hierarchy', '==', 'yes'],
                ]),
                $this->register_field(__('Append Terms', 'jc-importer'), '_append', [
                    'default' => 'no',
                    'options' => [
                        ['value' => 'no', 'label' => __('No', 'jc-importer')],
                        ['value' => 'yes', 'label' => __('Yes', 'jc-importer')],
                    ],
                    'type' => 'select'
                ]),
            ], ['type' => 'settings'])
        ], ['type' => 'repeatable', 'row_base' => true, 'link' => 'https://www.importwp.com/docs/how-to-import-wordpress-taxonomies-onto-a-post-type/']);
    }

    public function register_settings() {}

    public function register_options()
    {
        return [];
    }

    /**
     * Alter fields before they are parsed
     *
     * @param array $fields
     * @return array
     */
    public function field_map($fields)
    {
        if ($this->importer->isEnabledField('post._parent') && $fields['post._parent._parent_type'] === 'column' && !empty($fields["post._parent._parent_ref"])) {
            $fields['_iwp_ref_post_parent'] = $fields["post._parent._parent_ref"];
            $this->virtual_fields[] = '_iwp_ref_post_parent';
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

        $post_field_map = [];
        foreach ($this->field_map as $field_id => $field_map_key) {
            $post_field_map[$field_id] = $data->getValue($field_map_key);
        }

        // remove fields that have not been set
        foreach ($post_field_map as $field_key => $field_map) {

            if ($field_map === false) {
                unset($post_field_map[$field_key]);
                continue;
            }
        }

        foreach ($this->optional_fields as $optional_field) {
            if (true !== $this->importer->isEnabledField('post.' . $optional_field)) {
                unset($post_field_map[$optional_field]);
            }
        }

        if (isset($post_field_map['post_date'])) {
            $post_field_map['post_date_gmt'] = get_gmt_from_date($post_field_map['post_date']);
        }

        if (true !== $this->importer->isEnabledField('post._parent')) {
            unset($post_field_map['post_parent']);
        }

        if (true !== $this->importer->isEnabledField('post._author')) {
            unset($post_field_map['post_author']);
        }

        if (!empty($this->virtual_fields)) {
            foreach ($this->virtual_fields as $virtual_field) {
                $value = $data->getValue($virtual_field);
                if (false !== $value) {
                    $post_field_map[$virtual_field] = $value;
                }
            }
        }

        if ($this->importer->isEnabledField('post._author')) {
            $post_author = $data->getValue('post._author.post_author');
            $post_author_type = $data->getValue('post._author._author_type');

            $user_id = 0;

            if ($post_author_type === 'id') {

                $user = get_user_by('ID', $post_author);
                if ($user) {
                    $user_id = intval($user->ID);
                }
            } elseif ($post_author_type === 'login') {

                $user = get_user_by('login', $post_author);
                if ($user) {
                    $user_id = intval($user->ID);
                }
            } elseif ($post_author_type === 'email') {

                $user = get_user_by('email', $post_author);
                if ($user) {
                    $user_id = intval($user->ID);
                }
            }

            if ($user_id > 0) {
                $post_field_map['post_author'] = $user_id;
            } else {
                $post_field_map['post_author'] = '';
            }
        }

        // post_name is a unique field, so generate slug from title if no slug present
        // if (!isset($post_field_map['post_name']) || empty($post_field_map['post_name'])) {
        //     $post_field_map['post_name'] = sanitize_title($post_field_map['post_title']);

        //     // set flag to say slug has been generated
        //     $data->add(['post_name' => 'yes'], '_generated');
        // }

        if ($this->importer->isEnabledField('post._parent') && isset($post_field_map['post_parent']) && !empty($post_field_map['post_parent'])) {

            $parent_id = 0;
            $parent_field_type = $data->getValue('post._parent._parent_type');

            if ($parent_field_type === 'name' || $parent_field_type === 'slug') {

                // name or slug
                $page = get_posts(array('name' => sanitize_title($post_field_map['post_parent']), 'post_type' => $this->importer->getSetting('post_type')));
                if ($page) {
                    $parent_id = intval($page[0]->ID);
                }
            } elseif ($parent_field_type === 'id') {

                // ID
                $parent_id = intval($post_field_map['post_parent']);
            } elseif ($parent_field_type === 'column') {

                // reference column
                $temp_id = $this->get_post_by_cf('post_parent', $post_field_map['post_parent']);
                if (intval($temp_id > 0)) {
                    $parent_id = intval($temp_id);
                }
            }

            if ($parent_id > 0) {
                $post_field_map['post_parent'] = $parent_id;
            }
        }

        foreach ($post_field_map as $key => $value) {
            $post_field_map[$key] = apply_filters('iwp/template/process_field', $value, $key, $this->importer);
        }

        $data->replace($post_field_map, 'default');

        return $data;
    }

    /**
     * Find existing post/page/custom by reference field
     *
     * @param string $field Reference Field Name
     * @param string $value Value to check against reference
     *
     * @return bool
     */
    public function get_post_by_cf($field, $value)
    {

        $query = new \WP_Query(array(
            'post_type' => $this->importer->getSetting('post_type'),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'cache_results' => false,
            'update_post_meta_cache' => false,
            'meta_query' => array(
                array(
                    'key' => '_iwp_ref_' . $field,
                    'value' => $value
                )
            ),
            'post_status' => 'any, trash, future'
        ));
        if ($query->have_posts()) {
            return $query->posts[0];
        }
        return false;
    }

    /**
     * Process data after record is importer.
     * 
     * Use data that is returned from the mapper.
     *
     * @param int $post_id
     * @param ParsedData $data
     * @return void
     */
    public function post_process($post_id, ParsedData $data)
    {
        /**
         * @var Filesystem $filesystem
         */
        $filesystem = Container::getInstance()->get('filesystem');

        /**
         * @var Ftp $ftp
         */
        $ftp = Container::getInstance()->get('ftp');

        /**
         * @var Attachment $attachment
         */
        $attachment = Container::getInstance()->get('attachment');

        $this->process_taxonomies($post_id, $data);
        $this->process_attachments($post_id, $data, $filesystem, $ftp, $attachment);

        parent::post_process($post_id, $data);
    }

    /**
     * Process taxonomy group
     * 
     * TODO: Possibly move this into parser?
     *
     * @param int $post_id
     * @param ParsedData $data
     * @return void
     */
    public function process_taxonomies($post_id, $data)
    {
        // reset list of taxonomies
        $this->_taxonomies = [];

        $group = 'taxonomies';
        $taxonomes_data = $data->getData($group);
        $total_rows = isset($taxonomes_data[$group . '._index']) ? intval($taxonomes_data[$group . '._index']) : 0;
        $base_delimiter = apply_filters('iwp/value_delimiter', ',');
        $base_delimiter = apply_filters('iwp/taxonomy/value_delimiter', $base_delimiter);

        $processed_taxonomies = [];
        $term_hierarchy = [];
        $term_hierarchy_enabled = [];
        $term_types = [];
        $term_append = [];

        // Pre-Process taxonomy data
        for ($i = 0; $i < $total_rows; $i++) {

            $prefix = $group . '.' . $i . '.';

            $sub_rows = [$taxonomes_data];
            if (isset($taxonomes_data[$prefix . 'row_base']) && !empty($taxonomes_data[$prefix . 'row_base'])) {
                $sub_group_id = $group . '.' . $i;
                $sub_rows = $data->getData($sub_group_id);
            }

            foreach ($sub_rows as $row) {
                $tax = isset($row[$prefix . 'tax']) ? $row[$prefix . 'tax'] : null;
                $terms = isset($row[$prefix . 'term']) ? $row[$prefix . 'term'] : null;

                $term_append[$tax] = isset($row[$prefix . 'settings._append']) && $row[$prefix . 'settings._append'] == 'yes';

                $delimiter = apply_filters('iwp/taxonomy=' . $tax . '/value_delimiter', $base_delimiter);

                $term_types[$tax] = isset($row[$prefix . 'settings._type']) && !empty($row[$prefix . 'settings._type']) ? $row[$prefix . 'settings._type'] : 'name';
                $term_types_cf[$tax] = isset($row[$prefix . 'settings._type_cf']) && !empty($row[$prefix . 'settings._type_cf']) ? $row[$prefix . 'settings._type_cf'] : '';

                $hierarchy_enabled = isset($row[$prefix . 'settings._hierarchy']) && $row[$prefix . 'settings._hierarchy'] === 'yes' ? true : false;
                $hierarchy_character = isset($row[$prefix . 'settings._hierarchy_character']) ? $row[$prefix . 'settings._hierarchy_character'] : null;
                $hierarchy_terms = isset($row[$prefix . 'settings._hierarchy_relationship']) ? $row[$prefix . 'settings._hierarchy_relationship'] : null;


                $delimiter = isset($row[$prefix . 'settings._delimiter']) && strlen(trim($row[$prefix . 'settings._delimiter'])) === 1 ? trim($row[$prefix . 'settings._delimiter']) : $delimiter;

                if (empty($hierarchy_character)) {
                    $hierarchy_enabled = false;
                }

                if (!is_null($tax) && !is_null($terms)) {
                    $terms = explode($delimiter, $terms);
                    foreach ($terms as $term) {

                        $term = trim($term);
                        $permission_key = 'taxonomy.' . $tax; //taxonomy.category | taxonomy.post_tag

                        if ($data->permission()) {
                            $allowed = $data->permission()->validate([$permission_key => ''], $data->getMethod(), $group);
                            $is_allowed = isset($allowed[$permission_key]) ? true : false;

                            if (!$is_allowed || empty($term)) {
                                continue;
                            }
                        }

                        // handle taxonomies
                        if (!isset($processed_taxonomies[$tax])) {
                            $processed_taxonomies[$tax] = [];
                        }

                        $processed_taxonomies[$tax][] = $term;

                        // Handle hierarchy
                        if (!isset($term_hierarchy[$tax])) {
                            $term_hierarchy[$tax] = [];
                        }

                        if ($hierarchy_enabled) {
                            $hierarchy_parts = explode($hierarchy_character, $term);
                            if (count($hierarchy_parts) > 0) {
                                $hierarchy_parts = array_filter(array_map('trim', $hierarchy_parts));
                                $term_hierarchy[$tax][] = $hierarchy_parts;
                                $term_hierarchy_enabled[$tax] = true;
                            }
                        } else {
                            $term_hierarchy[$tax][] = [$term];
                        }
                    }
                }
            }
        }

        // TODO: Process term hierarchy
        foreach ($term_hierarchy as $processed_tax => $term_hierarchy_list) {

            // clear existing taxonomies
            $clear_existing_terms = !isset($term_append[$processed_tax]) || !$term_append[$processed_tax];

            if (empty($term_hierarchy_list)) {
                continue;
            }

            $new_terms = [];

            foreach ($term_hierarchy_list as $hierarchy_list) {

                $prev_term = isset($term_hierarchy_enabled[$processed_tax]) ? 0 : null;
                $type = $term_types[$processed_tax];

                foreach ($hierarchy_list as $term_i => $term) {
                    if (!isset($this->_taxonomies[$processed_tax])) {
                        $this->_taxonomies[$processed_tax] = [];
                    }

                    $connect_terms = true;
                    if ($hierarchy_enabled && $hierarchy_terms === 'last') {
                        $connect_terms = $term_i == count($hierarchy_list) - 1;
                    }

                    $search_term = $term;
                    if ($type === 'custom_field') {
                        $search_term = [
                            $term_types_cf[$processed_tax],
                            $term
                        ];
                    }

                    $term_result = $this->create_or_get_taxonomy_term($post_id, $processed_tax, $search_term, $prev_term, $type, false);
                    if ($term_result) {
                        $prev_term = $term_result->term_id;
                        $this->_taxonomies[$processed_tax][] = $term_result->name;
                    }

                    if ($connect_terms && $term_result) {
                        $new_terms[] = $term_result->term_id;
                    }
                }
            }

            // Connect all terms in one go.
            wp_set_object_terms($post_id, $new_terms, $processed_tax, !$clear_existing_terms);
        }
    }

    public function create_or_get_taxonomy_term($post_id, $tax, $term, $parent, $term_type = 'name', $set = true)
    {
        if (!in_array($term_type, ['slug', 'name', 'term_id', 'custom_field'])) {
            $term_type = 'name';
        }

        if (is_null($parent)) {

            // we do not care about parent, just fetch first

            if ($term_type === 'custom_field') {

                if (!isset($term[0], $term[1]) || empty($term[0]) || empty($term[1])) {
                    return false;
                }

                $terms = get_terms([
                    'taxonomy' => $tax,
                    'meta_key' => $term[0],
                    'meta_value' => $term[1],
                    'hide_empty' => false
                ]);
                if (empty($terms)) {
                    return false;
                }

                $tmp_term = $terms[0];
            } else {
                $tmp_term = get_term_by($term_type, $term, $tax);
            }

            if ($tmp_term) {
                $tmp_term = apply_filters('iwp/importer/template/post_term', $tmp_term, $tax);
                if ($set) {
                    wp_set_object_terms($post_id, $tmp_term->term_id, $tax, true);
                }
                return $tmp_term;
            }
        } else {

            if ($term_type === 'slug') {

                $terms = get_terms([
                    'taxonomy' => $tax,
                    'slug' => $term,
                    'hide_empty' => false
                ]);
            } elseif ($term_type === 'term_id') {

                // ID will only return a single record
                $terms = [];
                $tmp_term = get_term_by($term_type, $term, $tax);
                if ($tmp_term) {
                    $terms[] = $tmp_term;
                }
            } else if ($term_type === 'custom_field') {

                if (!isset($term[0], $term[1]) || empty($term[0]) || empty($term[1])) {
                    return false;
                }

                $terms = get_terms([
                    'taxonomy' => $tax,
                    'meta_key' => $term[0],
                    'meta_value' => $term[1],
                    'hide_empty' => false
                ]);
            } else {

                $terms = get_terms([
                    'taxonomy' => $tax,
                    'name' => $term,
                    'hide_empty' => false
                ]);
            }

            if (!empty($terms)) {
                foreach ($terms as $tmp_term) {
                    if (intval($tmp_term->parent) === intval($parent)) {

                        // attach term to post
                        $tmp_term = apply_filters('iwp/importer/template/post_term', $tmp_term, $tax);
                        if ($set) {
                            wp_set_object_terms($post_id, $tmp_term->term_id, $tax, true);
                        }
                        return $tmp_term;
                    }
                }
            }
        }

        // should not create new term since we are using the term_id
        if ($term_type === 'term_id' || $term_type === 'custom_field') {
            return false;
        }

        // allow the option to disable the creation of terms
        if (false === apply_filters('iwp/importer/template/post_create_term', true)) {
            return false;
        }

        // add term
        $term_id = wp_insert_term($term, $tax, ['parent' => $parent]);
        if (!is_wp_error($term_id)) {
            if ($set) {
                wp_set_object_terms($post_id, $term_id['term_id'], $tax, true);
            }
            return get_term($term_id['term_id'], $tax);
        }

        return false;
    }

    /**
     * Process attachments group
     * 
     * TODO: Possibly move this into parser?
     *
     * @param int $post_id
     * @param ParsedData $data
     * @param Filesystem $filesystem
     * @param FTP $ftp
     * @param Attachment $attachment
     * @return void
     */
    public function process_attachments($post_id, $data, $filesystem, $ftp, $attachment, $group = 'attachments')
    {
        // reset list of attachments
        $this->_attachments = [];
        $attachment_ids = [];

        $attachment_data = $data->getData($group);
        $total_rows = isset($attachment_data[$group . '._index']) ? intval($attachment_data[$group . '._index']) : 0;
        $skipped = 0;

        for ($i = 0; $i < $total_rows; $i++) {

            $permission_key = $group . '.' . $i; //attachment.0 | attachment.1
            if ($data->permission()) {
                $allowed = $data->permission()->validate([$permission_key => ''], $data->getMethod(), $group);
                $is_allowed = isset($allowed[$permission_key]) ? true : false;
                if (!$is_allowed) {
                    $skipped++;
                    continue;
                }
            }

            // Process Attachments
            $row_prefix = $group . '.' . $i . '.';

            $sub_rows = [$attachment_data];
            if (isset($attachment_data[$row_prefix . 'row_base']) && !empty($attachment_data[$row_prefix . 'row_base'])) {
                $sub_group_id = $group . '.' . $i;
                $sub_rows = $data->getData($sub_group_id);
            }

            foreach ($sub_rows as $row) {
                $ids = $this->process_attachment($post_id, $row, $row_prefix, $filesystem, $ftp, $attachment);
                $attachment_ids = array_merge($attachment_ids, $ids);
            }
        }

        if ($skipped == $total_rows) {
            return false;
        }

        return $attachment_ids;
    }

    /**
     * Get list of posts
     * 
     * @param ImporterModel $importer_model
     *
     * @return array
     */
    public function get_post_parent_options($importer_model)
    {
        $query = new \WP_Query(array(
            'post_type' => $importer_model->getSetting('post_type'),
            'posts_per_page' => -1,
            'cache_results' => false,
            'update_post_meta_cache' => false,
        ));

        if (is_wp_error($query)) {
            return $query;
        }

        $result = [];
        foreach ($query->posts as $post) {
            $result[] = [
                'value' => '' . $post->ID,
                'label' => $post->post_title
            ];
        }

        return $result;
    }

    /**
     * Get list taxonomies
     * 
     * @param ImporterModel $importer_model
     *
     * @return array
     */
    public function get_taxonomy_options($importer_model)
    {
        $taxonomies = get_object_taxonomies($importer_model->getSetting('post_type'), 'objects');

        if (empty($taxonomies)) {
            return [];
        }

        $result = [];
        /**
         * @var \WP_Taxonomy[] $taxonomies
         */
        foreach ($taxonomies as $key => $taxonomy) {
            $result[] = [
                'value' => '' . $key,
                'label' => $taxonomy->label
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

        $taxonomies = [];
        $attachments = [];
        $author = [];
        $parent = [];

        foreach ($fields as $index => $field) {

            if (preg_match('/^image\.(.*?)$/', $field, $matches) === 1) {

                // Capture featured image.
                // image.id,url, title, alt, caption, description
                if (!isset($attachments['image'])) {
                    $attachments['image'] = [
                        'map' => []
                    ];
                }

                $attachments['image']['map'][$matches[1]] = sprintf('{%s}', $index);
                $attachments['image']['map']['featured'] = 'yes';
            } elseif (preg_match('/^tax_([a-zA-Z0-9_]+)\.(.*?)$/', $field, $matches) === 1) {

                // Capture Taxonomies.
                // tax_{taxonomy}.name,slug,id,hierarchy::id,hierarchy::name,hierarchy::slug

                $taxonomy = $matches[1];
                if (!isset($taxonomies[$taxonomy])) {
                    $taxonomies[$taxonomy] = [
                        'map' => []
                    ];
                }

                $taxonomies[$taxonomy]['map'][$matches[2]] = sprintf('{%s}', $index);
            } elseif (preg_match('/^author\.(.*?)$/', $field, $matches) === 1) {

                // Capture author.
                // author.ID, author.user_login,author.user_nicename,author.user_email,author.user_url,author.display_name
                if (!isset($author['map'])) {
                    $author['map'] = [];
                }

                $author['map'][$matches[1]] = sprintf('{%s}', $index);
            } elseif (preg_match('/^parent\.(.*?)$/', $field, $matches) === 1) {

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

                if (in_array($field, ['post_status', 'comment_status', 'ping_status'])) {
                    $map[$field_key . '._enable_text'] = 'yes';
                }
            }
        }

        $taxonomies = apply_filters('iwp/importer/generate_field_map/taxonomies', $taxonomies, $fields, $importer);
        $attachments = apply_filters('iwp/importer/generate_field_map/attachments', $attachments, $fields, $importer);
        $author = apply_filters('iwp/importer/generate_field_map/author', $author, $fields, $importer);
        $parent = apply_filters('iwp/importer/generate_field_map/parent', $parent, $fields, $importer);

        if (!empty($taxonomies)) {

            $taxonomy_counter = 0;
            $defaults = [
                'row_base' => '',
                'tax' => '',
                'term' => '',
                'settings._append' => 'no',
                'settings._delimiter' => '',
                'settings._hierarchy' => 'no',
                'settings._hierarchy_character' => '>',
            ];

            foreach ($taxonomies as $taxonomy => $taxonomy_data) {
                $data = [];

                if (isset($taxonomy_data['map']['hierarchy::name'])) {
                    $data['term'] = $taxonomy_data['map']['hierarchy::name'];
                    $data['settings._hierarchy'] = 'yes';
                } elseif (isset($taxonomy_data['map']['hierarchy::slug'])) {
                    $data['term'] = $taxonomy_data['map']['hierarchy::slug'];
                    $data['settings._hierarchy'] = 'yes';
                } elseif (isset($taxonomy_data['map']['name'])) {
                    $data['term'] = $taxonomy_data['map']['name'];
                } elseif (isset($taxonomy_data['map']['slug'])) {
                    $data['term'] = $taxonomy_data['map']['slug'];
                } else {
                    continue;
                }

                $data['tax'] = $taxonomy;

                $data = wp_parse_args($data, $defaults);

                $map = array_merge($map, array_reduce(array_keys($data), function ($carry, $key) use ($data, $taxonomy_counter) {
                    $carry[sprintf('taxonomies.%d.%s', $taxonomy_counter, $key)] = $data[$key];
                    return $carry;
                }, []));

                $taxonomy_counter++;
            }

            $map['taxonomies._index'] = $taxonomy_counter;
        }

        if (!empty($attachments)) {

            $attachment_counter = 0;
            $defaults = [
                'row_base' => '',
                'location' => '',
                'settings._delimiter' => '',
                'settings._download' => 'remote',
                'settings._enable_image_hash' => 'yes',
                'settings._featured' => 'no',
                'settings._ftp_host' => '',
                'settings._ftp_pass' => '',
                'settings._ftp_path' => '',
                'settings._ftp_user' => '',
                'settings._local_url' => '',
                'settings._meta._alt' => '',
                'settings._meta._caption' => '',
                'settings._meta._description' => '',
                'settings._meta._enabled' => 'no',
                'settings._meta._title' => '',
                'settings._remote_url' => '',
            ];

            foreach ($attachments as $attachment_data) {

                $data = [];

                if (!isset($attachment_data['map']['url'])) {
                    continue;
                }

                // location
                $data['location'] = $attachment_data['map']['url'];

                if (isset($attachment_data['map']['featured'])) {
                    $data['settings._featured'] = $attachment_data['map']['featured'];
                }

                // Meta
                if (isset($attachment_data['map']['title'])) {
                    $data['settings._meta._title'] = $attachment_data['map']['title'];
                    $data['settings._meta._enabled'] = 'yes';
                }
                if (isset($attachment_data['map']['alt'])) {
                    $data['settings._meta._alt'] = $attachment_data['map']['alt'];
                    $data['settings._meta._enabled'] = 'yes';
                }
                if (isset($attachment_data['map']['description'])) {
                    $data['settings._meta._description'] = $attachment_data['map']['description'];
                    $data['settings._meta._enabled'] = 'yes';
                }
                if (isset($attachment_data['map']['caption'])) {
                    $data['settings._meta._caption'] = $attachment_data['map']['caption'];
                    $data['settings._meta._enabled'] = 'yes';
                }

                $data = wp_parse_args($data, $defaults);

                $map = array_merge($map, array_reduce(array_keys($data), function ($carry, $key) use ($data, $attachment_counter) {
                    $carry[sprintf('attachments.%d.%s', $attachment_counter, $key)] = $data[$key];
                    return $carry;
                }, []));

                $attachment_counter++;
            }

            $map['attachments._index'] = $attachment_counter;
        }

        if (!empty($author)) {

            // TODO: enable field? this is a seperate field list
            $enabled_key = 'post._author';
            if (isset($author['map']['user_email'])) {

                $enabled[] = $enabled_key;
                $map['post._author.post_author'] = $author['map']['user_email'];
                $map['post._author._author_type'] = 'email';
            } elseif (isset($author['map']['user_login'])) {

                $enabled[] = $enabled_key;
                $map['post._author.post_author'] = $author['map']['user_login'];
                $map['post._author._author_type'] = 'login';
            } elseif (isset($author['map']['ID'])) {

                $enabled[] = $enabled_key;
                $map['post._author.post_author'] = $author['map']['ID'];
                $map['post._author._author_type'] = 'id';
            }
        }

        if (!empty($parent)) {

            // parent.id,parent.name,parent.slug
            $enabled_key = 'post._parent';
            if (isset($parent['map']['name'])) {

                $enabled[] = $enabled_key;
                $map['post._parent.parent'] = $parent['map']['name'];
                $map['post._parent.parent._enable_text'] = 'yes';
                $map['post._parent._parent_type'] = 'name';
            } elseif (isset($parent['map']['slug'])) {

                $enabled[] = $enabled_key;
                $map['post._parent.parent'] = $parent['map']['slug'];
                $map['post._parent.parent._enable_text'] = 'yes';
                $map['post._parent._parent_type'] = 'slug';
            } elseif (isset($parent['map']['id'])) {

                $enabled[] = $enabled_key;
                $map['post._parent.parent'] = $parent['map']['name'];
                $map['post._parent.parent._enable_text'] = 'yes';
                $map['post._parent._parent_type'] = 'id';
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
            'ID' => __('ID', 'jc-importer'),
            'post_title' => __('Title', 'jc-importer'),
            'post_content' => __('Content', 'jc-importer'),
            'post_excerpt' => __('Excerpt', 'jc-importer'),
            'post_name' => __('Slug', 'jc-importer'),
            'post_status' => __('Post Status', 'jc-importer'),
            'menu_order' => __('Menu order', 'jc-importer'),
            'post_password' => __('password', 'jc-importer'),
            'post_date' => __('Date', 'jc-importer'),
            'comment_status' => __('Comment Status', 'jc-importer'),
            'ping_status' => __('Ping Status', 'jc-importer'),
            'post_parent' => __('Parent', 'jc-importer'),
            'post_author' => __('Author', 'jc-importer'),
        ];

        $permission_fields['taxonomies'] = [];
        $taxonomies = $this->get_taxonomy_options($importer_model);
        foreach ($taxonomies as $taxonomy) {
            $permission_fields['taxonomies']['taxonomy.' . $taxonomy['value']] = $taxonomy['label'];
        }

        $field_map = $importer_model->getMap();
        if (isset($field_map['attachments._index']) && $field_map['attachments._index'] > 0) {
            $permission_fields['attachments'] = [];
            for ($i = 0; $i < $field_map['attachments._index']; $i++) {
                $permission_fields['attachments']['attachments.' . $i] = 'Attachment Row ' . ($i + 1);
            }
        }

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
