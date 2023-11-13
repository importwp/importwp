<?php

namespace ImportWP\Common\Importer\Template;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\TemplateInterface;
use ImportWP\EventHandler;

class AttachmentTemplate extends Template implements TemplateInterface
{
    protected $name = 'Attachment';
    protected $mapper = 'attachment';
    private $virtual_fields = [];

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
        '_wp_attachment_image_alt' => 'post._wp_attachment_image_alt',
    ];

    protected $optional_fields = [];

    public function __construct(EventHandler $event_handler)
    {
        parent::__construct($event_handler);

        $this->groups[] = 'post';
        $this->default_template_options['unique_field'] = ['ID', 'post_name', 'src'];
        $this->default_template_options['post_type'] = 'attachment';

        $this->field_options = array_merge($this->field_options, [
            'post._parent.parent' => [$this, 'get_post_parent_options'],
        ]);

        $this->optional_fields = array_keys($this->field_map);
    }

    public function register()
    {
        return [
            $this->register_group('Attachment', 'post', [
                $this->register_field('ID', 'ID', [
                    'tooltip' => __('ID is only used to reference existing records', 'importwp')
                ]),
                $this->register_field('Title', 'post_title', [
                    'tooltip' => __('Title of the post.', 'importwp')
                ]),
                $this->register_field('Description', 'post_content', [
                    'tooltip' => __('Main WYSIWYG editor content of the post.', 'importwp')
                ]),
                $this->register_field('Caption', 'post_excerpt', [
                    'tooltip' => __('A custom short extract for the post.', 'importwp')
                ]),
                $this->register_field('Alt Text', '_wp_attachment_image_alt', [
                    'tooltip' => __('The slug is the user friendly and URL valid name of the post.', 'importwp')
                ]),
                $this->register_field('Slug', 'post_name', [
                    'tooltip' => __('The slug is the user friendly and URL valid name of the post.', 'importwp')
                ]),
                $this->register_attachment_fields('File', 'file', 'Media File', [], ['disabled_fields' => ['_meta', '_featured']]),
                $this->register_field('Date', 'post_date', [
                    'tooltip' => __('The date of the post , enter in the format "YYYY-MM-DD HH:ii:ss"', 'importwp')
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
            ]),
        ];
    }

    public function register_settings()
    {
        return [];
    }

    public function register_options()
    {
        return [];
    }

    public function pre_process(ParsedData $data)
    {
        $data = parent::pre_process($data);

        $field_map = [];
        $fields = [
            'ID',
            'post_title',
            'post_name',
            'post_content',
            'post_excerpt',
            '_wp_attachment_image_alt',
            'post_date',
            'menu_order',
            'post_author',
            'post_password',
            'comment_status',
            'ping_status',
        ];

        foreach ($fields as $field) {

            $field_id = sprintf('post.%s', $field);
            $value = $data->getValue($field_id, 'post');
            if ($value !== false && $this->importer->isEnabledField($field_id)) {
                $field_map[$field] = $value;
            }
        }

        $file_location = $data->getValue('post.file.location');
        if ($file_location !== false && $this->importer->isEnabledField('post.file')) {

            // Added so it can be used as a unique identifier
            // But not in default so it isnt automatically inserted.
            $data->add(['src' => $file_location], 'post');
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
            $post_author = $data->getValue('post._author.post_author', 'post');
            $post_author_type = $data->getValue('post._author._author_type', 'post');

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

            $field_map['post_author'] = $user_id > 0 ? $user_id : '';
        }

        // post_parent is different
        $post_parent = $data->getValue('post._parent.parent', 'post');
        if ($this->importer->isEnabledField('post._parent') && $post_parent !== false) {

            $field_map['post_parent'] = $post_parent;

            $parent_id = 0;
            $parent_field_type = $data->getValue('post._parent._parent_type');

            if ($parent_field_type === 'name' || $parent_field_type === 'slug') {

                // name or slug
                $page = get_posts(array('name' => sanitize_title($field_map['post_parent']), 'post_type' => 'any'));
                if ($page) {
                    $parent_id = intval($page[0]->ID);
                }
            } elseif ($parent_field_type === 'id') {

                // ID
                $parent_id = intval($field_map['post_parent']);
            } elseif ($parent_field_type === 'column') {

                // reference column
                $temp_id = $this->get_post_by_cf('post_parent', $field_map['post_parent']);
                if (intval($temp_id > 0)) {
                    $parent_id = intval($temp_id);
                }
            }

            if ($parent_id > 0) {
                $field_map['post_parent'] = $parent_id;
            }
        }

        $data->replace($field_map, 'default');

        return $data;
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
     * Get list of posts
     * 
     * @param ImporterModel $importer_model
     *
     * @return array
     */
    public function get_post_parent_options($importer_model)
    {
        $query = new \WP_Query(array(
            'post_type' => 'any',
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
            'post_type' => 'any',
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

            if (preg_match('/^author\.(.*?)$/', $field, $matches) === 1) {

                // Capture author.
                // author.ID, author.user_login,author.user_nicename,author.user_email,author.user_url,author.display_name
                if (!isset($author['map'])) {
                    $author['map'] = [];
                }

                $author['map'][$matches[1]] = sprintf('{%d}', $index);
            } elseif (preg_match('/^parent\.(.*?)$/', $field, $matches) === 1) {

                // Capture parent
                // parent.id,parent.name,parent.slug
                if (!isset($parent['map'])) {
                    $parent['map'] = [];
                }

                $parent['map'][$matches[1]] = sprintf('{%d}', $index);
            } elseif (isset($this->field_map[$field])) {

                // Handle core fields
                $field_key = $this->field_map[$field];
                $map[$field_key] = sprintf('{%d}', $index);

                if (in_array($field, $this->optional_fields)) {
                    $enabled[] = $field_key;
                }

                if (in_array($field, ['post_status', 'comment_status', 'ping_status'])) {
                    $map[$field_key . '._enable_text'] = 'yes';
                }
            } elseif ($field === 'custom_fields._wp_attachment_image_alt') {


                $field_key = $this->field_map[$field];
                $map[$field_key] = sprintf('{%d}', $index);
                $enabled[] = $field_key;

                add_filter('iwp/importer/generate_field_map/custom_fields', function ($custom_fields) {

                    if (isset($custom_fields['_wp_attachment_image_alt'])) {
                        unset($custom_fields['_wp_attachment_image_alt']);
                    }

                    return $custom_fields;
                });
            } elseif ($field === 'url') {


                $map['post.file.location'] = sprintf('{%d}', $index);
                $enabled[] = 'post.file';
            }
        }

        $author = apply_filters('iwp/importer/generate_field_map/author', $author, $fields, $importer);
        $parent = apply_filters('iwp/importer/generate_field_map/parent', $parent, $fields, $importer);

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
            'ID' => 'ID',
            'post_title' => 'Title',
            'file' => 'Attachment File',
            'post_content' => 'Description',
            'post_excerpt' => 'Caption',
            'post_name' => 'Slug',
            'post_status' => 'Post Status',
            'menu_order' => 'Order',
            'post_password' => 'Password',
            'post_date' => 'Date',
            'comment_status' => 'Allow Comments',
            'ping_status' => 'Allow Pingbacks',
            'post_parent' => 'Parent',
            'post_author' => 'Author',
            '_wp_attachment_image_alt' => 'Alt Text',
        ];

        return $permission_fields;
    }
}
