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
        'post_title' => 'post.post_title',
        'post_content' => 'post.post_content',
        'post_excerpt' => 'post.post_excerpt',
        'post_name' => 'post.post_name',
        'post_status' => 'post.post_status',
        'menu_order' => 'post.menu_order',
        'post_password' => 'post.post_password',
        'post_date' => 'post.post_date',
        'comment_status' => 'post.comment_status',
        'ping_status' => 'post.ping_status',
        'post_parent' => 'post._parent.parent',
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
            $message .= ', Attachments: (' . implode(', ', $this->_attachments) . ')';
        }

        return $message;
    }

    public function register()
    {
        $groups = [];

        // Post
        $groups[] = $this->register_group('Post Fields', 'post', [
            $this->register_field('ID', 'ID', [
                'tooltip' => __('ID is only used to reference existing records', 'importwp')
            ]),
            $this->register_core_field('Title', 'post_title', [
                'tooltip' => __('Title of the post.', 'importwp')
            ]),
            $this->register_core_field('Content', 'post_content', [
                'tooltip' => __('Main WYSIWYG editor content of the post.', 'importwp')
            ]),
            $this->register_field('Excerpt', 'post_excerpt', [
                'tooltip' => __('A custom short extract for the post.', 'importwp')
            ]),
            $this->register_field('Slug', 'post_name', [
                'tooltip' => __('The slug is the user friendly and URL valid name of the post.', 'importwp')
            ]),
            $this->register_field('Status', 'post_status', [
                'default' => 'publish',
                'options'         => [
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'publish', 'label' => 'Published'],
                    ['value' => 'pending', 'label' => 'Pending'],
                    ['value' => 'future', 'label' => 'Future'],
                    ['value' => 'private', 'label' => 'Private'],
                    ['value' => 'trash', 'label' => 'Trash']
                ],
                'tooltip' => __('Whether the post can accept comments. Accepts open or closed', 'importwp')
            ]),
            $this->register_group('Parent Settings', '_parent', [
                $this->register_field('Parent', 'parent', [
                    'default' => '',
                    'options' => 'callback',
                    'tooltip' => __('Set this for the post it belongs to', 'importwp')
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
            $this->register_field('Order', 'menu_order', [
                'tooltip' => __('The order the post should be displayed in', 'importwp')
            ]),
            $this->register_group('Author Settings', '_author', [
                $this->register_field('Author', 'post_author', [
                    'tooltip' => __('The user of who added this post', 'importwp')
                ]),
                $this->register_field('Author Field Type', '_author_type', [
                    'default' => 'id',
                    'options' => [
                        ['value' => 'id', 'label' => 'ID'],
                        ['value' => 'login', 'label' => 'Login'],
                        ['value' => 'email', 'label' => 'Email'],
                    ],
                    'tooltip' => __('Select how the author field should be handled', 'importwp')
                ])
            ]),
            $this->register_field('Password', 'post_password', [
                'tooltip' => __('The password to access the post', 'importwp')
            ]),
            $this->register_field('Date', 'post_date', [
                'tooltip' => __('The date of the post , enter in the format "YYYY-MM-DD HH:ii:ss"', 'importwp')
            ]),
            $this->register_field('Allow Comments', 'comment_status', [
                'options' => [
                    ['value' => '0', 'label' => 'Disabled'],
                    ['value' => '1', 'label' => 'Enabled']
                ],
                'default' => '0',
                'tooltip' => __('Whether the post can accept comments', 'importwp')
            ]),
            $this->register_field('Allow Pingbacks', 'ping_status', [
                'options' => [
                    ['value' => 'closed', 'label' => 'Closed'],
                    ['value' => 'open', 'label' => 'Open']
                ],
                'default' => 'closed',
                'tooltip' => __('Whether the post can accept pings', 'importwp')
            ])
        ]);

        // Taxonomies
        $groups[] = $this->register_taxonomy_fields();

        // Attachments
        $groups[] = $this->register_attachment_fields();

        return $groups;
    }

    public function register_taxonomy_fields()
    {
        return $this->register_group('Taxonomies', 'taxonomies', [
            $this->register_field('Taxonomy', 'tax', [
                'default' => 'category',
                'options' => 'callback',
                'tooltip' => __('Select the type of taxonomy you are importing to.', 'importwp')
            ]),
            $this->register_field('Terms', 'term', [
                'tooltip' => __('Name of the taxonomy term or terms (entered as a comma seperated list).', 'importwp')
            ]),
            $this->register_field('Enable Hierarchy', '_hierarchy', [
                'default' => 'no',
                'options' => [
                    ['value' => 'no', 'label' => 'No'],
                    ['value' => 'yes', 'label' => 'Yes'],
                ],
                'type' => 'select'
            ]),
            $this->register_field('Hierarchy Character', '_hierarchy_character', [
                'default' => '>',
                'condition' => ['_hierarchy', '==', 'yes'],
            ]),
        ], ['type' => 'repeatable', 'row_base' => true]);
    }

    public function register_settings()
    {
    }

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

        $optional_fields = [
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
        foreach ($optional_fields as $optional_field) {
            if (true !== $this->importer->isEnabledField('post.' . $optional_field)) {
                unset($post_field_map[$optional_field]);
            }
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

        if ($this->importer->isEnabledField('post._parent') && isset($post_field_map['post_parent'])) {

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
            'post_status' => 'any'
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

        $this->featured_set = false;
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
        $delimiter = apply_filters('iwp/value_delimiter', ',');
        $delimiter = apply_filters('iwp/taxonomy/value_delimiter', $delimiter);

        $processed_taxonomies = [];
        $term_hierarchy = [];

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

                $hierarchy_enabled = isset($row[$prefix . '_hierarchy']) && $row[$prefix . '_hierarchy'] === 'yes' ? true : false;
                $hierarchy_character = isset($row[$prefix . '_hierarchy_character']) ? $row[$prefix . '_hierarchy_character'] : null;

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
            wp_set_object_terms($post_id, null, $processed_tax);

            if (empty($term_hierarchy_list)) {
                continue;
            }

            foreach ($term_hierarchy_list as $hierarchy_list) {
                $prev_term = null;
                foreach ($hierarchy_list as $term) {
                    if (!isset($this->_taxonomies[$processed_tax])) {
                        $this->_taxonomies[$processed_tax] = [];
                    }

                    $term_result = $this->create_or_get_taxonomy_term($post_id, $processed_tax, $term, $prev_term);
                    if ($term_result) {
                        $prev_term = $term_result;
                        $this->_taxonomies[$processed_tax][] = $term_result;
                    }
                }
            }
        }

        // Process taxonomy data
        // foreach ($processed_taxonomies as $processed_tax => $processed_terms) {

        //     // clear existing taxonomies
        //     wp_set_object_terms($post_id, null, $processed_tax);

        //     // insert terms
        //     if (!empty($processed_terms)) {
        //         foreach ($processed_terms as $term) {

        //             if (!isset($this->_taxonomies[$processed_tax])) {
        //                 $this->_taxonomies[$processed_tax] = [];
        //             }

        //             $term_result = $this->create_or_get_taxonomy_term($post_id, $processed_tax, $term);
        //             if ($term_result) {
        //                 $this->_taxonomies[$processed_tax][] = $term_result;
        //             }
        //         }
        //     }
        // }
    }

    private function create_or_get_taxonomy_term($post_id, $tax, $term, $parent = null)
    {
        $parent_id = false;
        $args = [];
        if (!empty($parent)) {
            $term_obj = get_term_by('name', $parent, $tax);
            $parent_id = $term_obj->term_id;
            $args['parent'] = $parent_id;
        }

        $term_result = term_exists($term, $tax);
        if ($term_result) {

            if (!empty($args)) {
                $term_id = $term_result['term_id'];
                wp_update_term($term_id, $tax, $args);
            }

            // attach term to post
            wp_set_object_terms($post_id, $term, $tax, true);
            return $term;
        } else {
            // add term
            $term_id = wp_insert_term($term, $tax, $args);
            if (!is_wp_error($term_id)) {
                wp_set_object_terms($post_id, $term_id, $tax, true);
                return $term;
            }
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
        $delimiter = apply_filters('iwp/value_delimiter', ',');
        $delimiter = apply_filters('iwp/attachment/value_delimiter', $delimiter);

        for ($i = 0; $i < $total_rows; $i++) {

            $permission_key = $group . '.' . $i; //attachment.0 | attachment.1
            if ($data->permission()) {
                $allowed = $data->permission()->validate([$permission_key => ''], $data->getMethod(), $group);
                $is_allowed = isset($allowed[$permission_key]) ? true : false;
                if (!$is_allowed) {
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
}
