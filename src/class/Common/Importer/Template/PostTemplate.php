<?php

namespace ImportWP\Common\Importer\Template;

use ImportWP\Common\Attachment\Attachment;
use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Ftp\Ftp;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\TemplateInterface;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Container;

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

    public function __construct()
    {
        $this->groups[] = 'post';
        $this->groups[] = 'taxonomies';
        $this->groups[] = 'attachments';

        $this->field_options = [
            'post._parent.parent' => [$this, 'get_post_parent_options'],
            'taxonomies.*.tax' => [$this, 'get_taxonomy_options'],
        ];
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
        $groups[] = $this->register_group('Taxonomies', 'taxonomies', [
            $this->register_field('Taxonomy', 'tax', [
                'default' => 'category',
                'options' => 'callback',
                'tooltip' => __('Select the type of taxonomy you are importing to.', 'importwp')
            ]),
            $this->register_field('Terms', 'term', [
                'tooltip' => __('Name of the taxonomy term or terms (entered as a comma seperated list).', 'importwp')
            ])
        ], ['type' => 'repeatable']);

        // Attachments
        $groups[] = $this->register_group('Attachments', 'attachments', [
            $this->register_field('Location', 'location', [
                'tooltip' => __('The source location of the file being attached.', 'importwp')
            ]),
            $this->register_field('Is Featured?', '_featured', [
                'default' => 'no',
                'options' => [
                    ['value' => 'no', 'label' => 'No'],
                    ['value' => 'yes', 'label' => 'Yes'],
                ],
                'tooltip' => __('Is the attachment the featured image for the current post.', 'importwp')
            ]),
            $this->register_field('Download', '_download', [
                'default' => 'remote',
                'options' => [
                    ['value' => 'remote', 'label' => 'Remote URL'],
                    ['value' => 'ftp', 'label' => 'FTP'],
                    ['value' => 'local', 'label' => 'Local Filesystem'],
                ],
                'tooltip' => __('Select how the attachment is being downloaded.', 'importwp')
            ]),
            $this->register_field('Host', '_ftp_host', [
                'condition' => ['_download', '==', 'ftp'],
                'tooltip' => __('Enter the FTP hostname', 'importwp')
            ]),
            $this->register_field('Username', '_ftp_user', [
                'condition' => ['_download', '==', 'ftp'],
                'tooltip' => __('Enter the FTP username', 'importwp')
            ]),
            $this->register_field('Password', '_ftp_pass', [
                'condition' => ['_download', '==', 'ftp'],
                'tooltip' => __('Enter the FTP password', 'importwp')
            ]),
            $this->register_field('Path', '_ftp_path', [
                'condition' => ['_download', '==', 'ftp'],
                'tooltip' => __('Enter the FTP base path, this is prefixed onto the Location field, leave empty to be ignore', 'importwp')
            ]),
            $this->register_field('Base URL', '_remote_url', [
                'condition' => ['_download', '==', 'remote'],
                'tooltip' => __('Enter the base url, this is prefixed onto the Location field, leave empty to be ignore', 'importwp')
            ]),
            $this->register_field('Base URL', '_local_url', [
                'condition' => ['_download', '==', 'local'],
                'tooltip' => __('Enter the base path from this servers root file system, this is prefixed onto the Location field, leave empty to be ignore', 'importwp')
            ]),
            $this->register_group('Attachment Meta', '_meta', [
                $this->register_field('Enable Meta', '_enabled', [
                    'default' => 'no',
                    'options' => [
                        ['value' => 'no', 'label' => 'No'],
                        ['value' => 'yes', 'label' => 'Yes'],
                    ],
                    'type' => 'select',
                    'tooltip' => __('Enable/Disable the fields to import attachment meta data.', 'importwp')
                ]),
                $this->register_field('Alt Text', '_alt', [
                    'condition' => ['_enabled', '==', 'yes'],
                    'tooltip' => __('Image attachment alt text.', 'importwp'),
                ]),
                $this->register_field('Title Text', '_title', [
                    'condition' => ['_enabled', '==', 'yes'],
                    'tooltip' => __('Attachments title text.', 'importwp')
                ]),
                $this->register_field('Caption Text', '_caption', [
                    'condition' => ['_enabled', '==', 'yes'],
                    'tooltip' => __('Image attachments caption text.', 'importwp')
                ]),
                $this->register_field('Description Text', '_description', [
                    'condition' => ['_enabled', '==', 'yes'],
                    'tooltip' => __('Attachments description text.', 'importwp')
                ])
            ]),
        ], ['type' => 'repeatable']);

        return $groups;
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

        $this->process_taxonomies($post_id, $data);
        $this->process_attachments($post_id, $data, $filesystem, $ftp, $attachment);
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

        for ($i = 0; $i < $total_rows; $i++) {

            $prefix = $group . '.' . $i . '.';
            $tax = isset($taxonomes_data[$prefix . 'tax']) ? $taxonomes_data[$prefix . 'tax'] : null;
            $terms = isset($taxonomes_data[$prefix . 'term']) ? $taxonomes_data[$prefix . 'term'] : null;
            $fields = [
                'taxonomy_name' => $tax,
                'taxonomy_terms' => []
            ];

            if (!is_null($tax) && !is_null($terms)) {
                $terms = explode($delimiter, $terms);
                foreach ($terms as $term) {

                    $term = trim($term);
                    $permission_key = 'taxonomy.' . $tax; //taxonomy.category | taxonomy.post_tag
                    $allowed = $data->permission()->validate([$permission_key => ''], $data->getMethod(), $group);
                    $is_allowed = isset($allowed[$permission_key]) ? true : false;

                    if (!$is_allowed || empty($term)) {
                        continue;
                    }

                    $fields['taxonomy_terms'][] = $term;
                }

                // clear existing taxonomies
                wp_set_object_terms($post_id, null, $fields['taxonomy_name']);

                // insert terms
                if (!empty($fields['taxonomy_terms'])) {
                    foreach ($fields['taxonomy_terms'] as $term) {

                        if (!isset($this->_taxonomies[$fields['taxonomy_name']])) {
                            $this->_taxonomies[$fields['taxonomy_name']] = [];
                        }

                        if (term_exists($term, $fields['taxonomy_name'])) {
                            // attach term to post
                            wp_set_object_terms($post_id, $term, $fields['taxonomy_name'], true);
                            $this->_taxonomies[$fields['taxonomy_name']][] = $term;
                        } else {
                            // add term
                            $term_id = wp_insert_term($term, $fields['taxonomy_name']);
                            if (!is_wp_error($term_id)) {
                                wp_set_object_terms($post_id, $term_id, $fields['taxonomy_name'], true);
                                $this->_taxonomies[$fields['taxonomy_name']][] = $term;
                            }
                        }
                    }
                }
            }
        }
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
    public function process_attachments($post_id, $data, $filesystem, $ftp, $attachment)
    {
        // reset list of attachments
        $this->_attachments = [];

        $group = 'attachments';
        $attachment_data = $data->getData($group);
        $total_rows = isset($attachment_data[$group . '._index']) ? intval($attachment_data[$group . '._index']) : 0;
        $delimiter = apply_filters('iwp/value_delimiter', ',');
        $delimiter = apply_filters('iwp/attachment/value_delimiter', $delimiter);

        for ($i = 0; $i < $total_rows; $i++) {

            $permission_key = 'attachment.' . $i; //attachment.0 | attachment.1
            $allowed = $data->permission()->validate([$permission_key => ''], $data->getMethod(), $group);
            $is_allowed = isset($allowed[$permission_key]) ? true : false;
            if (!$is_allowed) {
                continue;
            }

            // Process Attachments
            $row_prefix = $group . '.' . $i . '.';
            $location = isset($attachment_data[$row_prefix . 'location']) ? $attachment_data[$row_prefix . 'location'] : null;
            $download = isset($attachment_data[$row_prefix . '_download']) ? $attachment_data[$row_prefix . '_download'] : null;
            $featured = isset($attachment_data[$row_prefix . '_featured']) ? $attachment_data[$row_prefix . '_featured'] : null;
            $source = null;
            $result = false;

            $location = trim($location);

            switch ($download) {
                case 'remote':
                    $base_url = isset($attachment_data[$row_prefix . '_remote_url']) ? $attachment_data[$row_prefix . '_remote_url'] : null;

                    // check if file hash is already stored
                    $source = $base_url . $location;
                    $source = apply_filters('iwp/attachment/filename', $source);
                    $attachment_id = $attachment->get_attachment_by_hash($source);
                    if ($attachment_id <= 0) {
                        $result = $filesystem->download_file($source);
                    }
                    break;
                case 'ftp':
                    $ftp_user = isset($attachment_data[$row_prefix . '_ftp_user']) ? $attachment_data[$row_prefix . '_ftp_user'] : null;
                    $ftp_host = isset($attachment_data[$row_prefix . '_ftp_host']) ? $attachment_data[$row_prefix . '_ftp_host'] : null;
                    $ftp_pass = isset($attachment_data[$row_prefix . '_ftp_pass']) ? $attachment_data[$row_prefix . '_ftp_pass'] : null;
                    $base_url = isset($attachment_data[$row_prefix . '_ftp_path']) ? $attachment_data[$row_prefix . '_ftp_path'] : null;

                    // check if file hash is already stored
                    $source = $base_url . $location;
                    $source = apply_filters('iwp/attachment/filename', $source);
                    $attachment_id = $attachment->get_attachment_by_hash($source);
                    if ($attachment_id <= 0) {
                        $result = $ftp->download_file($source, $ftp_host, $ftp_user, $ftp_pass);
                    }
                    break;
                case 'local':
                    $base_url = isset($attachment_data[$row_prefix . '_local_url']) ? $attachment_data[$row_prefix . '_local_url'] : null;

                    // check if file hash is already stored
                    $source = $base_url . $location;
                    $source = apply_filters('iwp/attachment/filename', $source);
                    $attachment_id = $attachment->get_attachment_by_hash($source);
                    if ($attachment_id <= 0) {
                        $result = $filesystem->copy_file($source);
                    }
                    break;
            }

            $meta_enabled = isset($attachment_data[$row_prefix . '_meta._enabled']) && $attachment_data[$row_prefix . '_meta._enabled'] === 'yes' ? true : false;

            // insert attachment
            if ($attachment_id <= 0) {

                if (is_wp_error($result)) {
                    // TODO: What do we do with errors?
                    continue;
                }

                if (!$result) {
                    continue;
                }

                $attachment_args = [];
                if ($meta_enabled) {
                    $attachment_args['title'] = isset($attachment_data[$row_prefix . '_meta._title']) ? $attachment_data[$row_prefix . '_meta._title'] : null;
                    $attachment_args['alt'] = isset($attachment_data[$row_prefix . '_meta._alt']) ? $attachment_data[$row_prefix . '_meta._alt'] : null;
                    $attachment_args['caption'] = isset($attachment_data[$row_prefix . '_meta._caption']) ? $attachment_data[$row_prefix . '_meta._caption'] : null;
                    $attachment_args['description'] = isset($attachment_data[$row_prefix . '_meta._description']) ? $attachment_data[$row_prefix . '_meta._description'] : null;
                }

                $attachment_id = $attachment->insert_attachment($post_id, $result['dest'], $result['mime'], $attachment_args);
                if (is_wp_error($attachment_id)) {
                    continue;
                }

                $attachment->generate_image_sizes($attachment_id, $result['dest']);
                $attachment->store_attachment_hash($attachment_id, $source);
            } else {
                // Update existing attachment meta
                if ($meta_enabled) {
                    $post_data = [];

                    if (isset($attachment_data[$row_prefix . '_meta._title']) && !empty($attachment_data[$row_prefix . '_meta._title'])) {
                        $post_data['post_title'] = $attachment_data[$row_prefix . '_meta._title'];
                    }

                    if (isset($attachment_data[$row_prefix . '_meta._description']) && !empty($attachment_data[$row_prefix . '_meta._description'])) {
                        $post_data['post_content'] = $attachment_data[$row_prefix . '_meta._description'];
                    }

                    if (isset($attachment_data[$row_prefix . '_meta._caption']) && !empty($attachment_data[$row_prefix . '_meta._caption'])) {
                        $post_data['post_excerpt'] = $attachment_data[$row_prefix . '_meta._caption'];
                    }

                    if (!empty($post_data)) {
                        $post_data['ID'] = $attachment_id;
                        wp_update_post($post_data);
                    }

                    if (isset($attachment_data[$row_prefix . '_meta._alt']) && !empty($attachment_data[$row_prefix . '_meta._alt'])) {
                        update_post_meta($attachment_id, '_wp_attachment_image_alt', $attachment_data[$row_prefix . '_meta._alt']);
                    }
                }
            }

            $this->_attachments[] = wp_get_attachment_url($attachment_id);

            // set featured
            if ('yes' === $featured) {
                update_post_meta($post_id, '_thumbnail_id', $attachment_id);
            }
        }
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
