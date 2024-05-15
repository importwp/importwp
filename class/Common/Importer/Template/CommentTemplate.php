<?php

namespace ImportWP\Common\Importer\Template;

use ImportWP\Common\Importer\TemplateInterface;
use ImportWP\EventHandler;

class CommentTemplate extends Template implements TemplateInterface
{
    protected $name = 'Comment';
    protected $mapper = 'comment';

    public function __construct(EventHandler $event_handler)
    {
        parent::__construct($event_handler);

        $this->groups[] = 'comment';

        $this->field_options = array_merge($this->field_options, [
            'comment._parent.id' => [$this, 'get_parent_options'],
        ]);
    }

    public function register()
    {

        $post_types = get_post_types();
        $post_type_options = [
            ['label' => __('Any', 'jc-importer'), 'value' => '']
        ];
        foreach ($post_types as $post_type => $label) {
            $post_type_options[] = ['label' => $label, 'value' => $post_type];
        }

        return [
            $this->register_group(__('Comment', 'jc-importer'), 'comment', [

                // Comment
                $this->register_field(__('ID', 'jc-importer'), 'comment_ID', [
                    'tooltip' => __('ID is only used to reference existing records', 'jc-importer')
                ]),
                $this->register_field(__('Content', 'jc-importer'), 'comment_content', [
                    'tooltip' => __('The content of the comment.', 'jc-importer')
                ]),
                $this->register_group(__('Comment Parent', 'jc-importer'), '_parent', [

                    $this->register_field(__('Comment Parent', 'jc-importer'), 'id', [
                        'default' => '',
                        'options' => 'callback',
                        'tooltip' => __('ID of this comment\'s parent, if any.', 'jc-importer')
                    ]),
                    $this->register_field(__('Comment Parent Field Type', 'jc-importer'), '_id_type', [
                        'default' => 'id',
                        'options' => [
                            ['value' => 'id', 'label' => __('ID', 'jc-importer')],
                            ['value' => 'ref', 'label' => __('Comment Ref Field', 'jc-importer')],
                            ['value' => 'custom_field', 'label' => __('Custom Field', 'jc-importer')],
                        ],
                        'type' => 'select',
                        'tooltip' => __('Select how the post ID field should be handled', 'jc-importer')
                    ]),
                    $this->register_field(__('Custom Field Key', 'jc-importer'), '_custom_field', [
                        'condition' => ['_id_type', '==', 'custom_field'],
                        'tooltip' => __('Enter the name of the post\'s custom field.', 'jc-importer')
                    ])

                ]),
                $this->register_group(__('Comment Post', 'jc-importer'), '_post', [
                    $this->register_field(__('Comment Post', 'jc-importer'), 'id', [
                        'default' => '',
                        'tooltip' => __('ID of the post that relates to the comment, if any.', 'jc-importer')
                    ]),
                    $this->register_field(__('Comment Post Field Type', 'jc-importer'), '_id_type', [
                        'default' => 'id',
                        'options' => [
                            ['value' => 'id', 'label' => __('ID', 'jc-importer')],
                            ['value' => 'slug', 'label' => __('Slug', 'jc-importer')],
                            ['value' => 'name', 'label' => __('Name', 'jc-importer')],
                            ['value' => 'custom_field', 'label' => __('Custom Field', 'jc-importer')],
                        ],
                        'type' => 'select',
                        'tooltip' => __('Select how the post ID field should be handled', 'jc-importer')
                    ]),
                    $this->register_field(__('Custom Field Key', 'jc-importer'), '_custom_field', [
                        'condition' => ['_id_type', '==', 'custom_field'],
                        'tooltip' => __('Enter the name of the post\'s custom field.', 'jc-importer')
                    ])
                ]),
                $this->register_field(__('Comment Type', 'jc-importer'), 'comment_type', [
                    'default' => 'comment',
                    'tooltip' => __('Comment type.', 'jc-importer')
                ]),

                // Author
                $this->register_field(__('Author ID', 'jc-importer'), 'user_id', [
                    'tooltip' => __('Author user id.', 'jc-importer')
                ]),
                $this->register_field(__('Author Name', 'jc-importer'), 'comment_author', [
                    'tooltip' => __('The name of the author of the comment.', 'jc-importer')
                ]),
                $this->register_field(__('Author Email', 'jc-importer'), 'comment_author_email', [
                    'tooltip' => __('The email address of the Comment Author', 'jc-importer')
                ]),
                $this->register_field(__('Author Url', 'jc-importer'), 'comment_author_url', [
                    'tooltip' => __('The URL address of the Comment Author.', 'jc-importer')
                ]),
                $this->register_field(__('Comment Author IP', 'jc-importer'), 'comment_author_IP', [
                    'tooltip' => __('The IP address of the Comment Author.', 'jc-importer')
                ]),

                // Meta
                $this->register_field(__('Ref', 'jc-importer'), '_iwp_ref_id', [
                    'tooltip' => __('A custom field to uniquely identify the comment.', 'jc-importer')
                ]),
                $this->register_field(__('Post Type', 'jc-importer'), 'post_type', [
                    'options' => $post_type_options,
                    'default' => '',
                    'tooltip' => __('The post type the comment belongs to.', 'jc-importer')
                ]),
                $this->register_field(__('Comment Approved', 'jc-importer'), 'comment_approved', [
                    'options' => [
                        ['value' => '0', 'label' => __('Disapproved', 'jc-importer')],
                        ['value' => '1', 'label' => __('Approved', 'jc-importer')]
                    ],
                    'default' => '1',
                    'tooltip' => __('Whether the comment has been approved. 1 = Approved, 0 = Disapproved', 'jc-importer')
                ]),
                $this->register_field(__('Comment Karma', 'jc-importer'), 'comment_karma', [
                    'default' => '0',
                    'tooltip' => __('The karma of the comment.', 'jc-importer')
                ]),
                $this->register_field(__('Comment Agent', 'jc-importer'), 'comment_agent', [
                    'tooltip' => __('The HTTP user agent of the Comment Author when the comment was submitted.', 'jc-importer')
                ]),
                $this->register_field(__('Comment Date', 'jc-importer'), 'comment_date', [
                    'tooltip' => __('The date the comment was submitted.', 'jc-importer')
                ]),
            ])
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

    /**
     * Get list of comments
     * 
     * @param \ImportWP\Common\Model\ImporterModel $importer_model
     *
     * @return array
     */
    public function get_parent_options($importer_model)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $vars = [];
        $results = $wpdb->get_col("SELECT comment_post_ID FROM {$wpdb->comments}");

        foreach ($results as $result) {
            $vars[] = [
                'value' => $result,
                'label' => sprintf(__('Comment #%s', 'jc-importer'), $result)
            ];
        }

        return $vars;
    }

    /**
     * Process data before record is importer.
     * 
     * Alter data that is passed to the mapper.
     *
     * @param \ImportWP\Common\Importer\ParsedData $data
     * @return \ImportWP\Common\Importer\ParsedData
     */
    public function pre_process(\ImportWP\Common\Importer\ParsedData $data)
    {
        $data = parent::pre_process($data);

        $field_map = [
            'comment_ID',
            'comment_agent',
            'comment_approved',
            'comment_author',
            'comment_author_email',
            'comment_author_IP',
            'comment_author_url',
            'comment_content',
            'comment_date',
            'comment_date_gmt',
            'comment_karma',
            'comment_parent',
            'comment_post_ID',
            'comment_type',
            'user_id',
            '_iwp_ref_id'
        ];

        $comment_field_map = [];

        foreach ($field_map as $field_key) {

            $field_id = sprintf('comment.%s', $field_key);
            $value = $data->getValue($field_id, 'comment');

            if ($value !== false && $this->importer->isEnabledField('comment.' . $field_key)) {
                $comment_field_map[$field_key] = $value;
            }
        }

        // Get comment post type, used for reading data
        $post_type = 'any';
        if ($this->importer->isEnabledField('comment.post_type')) {

            $value = $data->getValue('comment.post_type', 'comment');
            if (!empty($value)) {
                $post_type = $value;
            }
        }

        // post field
        if ($this->importer->isEnabledField('comment._post')) {

            $comment_post_ID = $data->getValue('comment._post.id');
            if ($comment_post_ID !== false) {

                $parent_field_type = $data->getValue('comment._post._id_type');
                switch ($parent_field_type) {
                    case 'id':

                        // ID
                        $comment_post_ID = intval($comment_post_ID);
                        break;
                    case 'slug':
                    case 'name':

                        // name or slug
                        $page = get_posts(array('name' => sanitize_title($comment_post_ID), 'post_type' => $post_type));
                        if ($page) {
                            $comment_post_ID = intval($page[0]->ID);
                        }

                        break;
                    case 'custom_field':

                        // reference column
                        $parent_custom_field = $data->getValue('comment._post._custom_field');
                        $temp_id = $this->get_post_by_cf($parent_custom_field, $comment_post_ID, $post_type);
                        if (intval($temp_id > 0)) {
                            $comment_post_ID = intval($temp_id);
                        }
                        break;
                }
            }

            if ($comment_post_ID > 0) {
                $comment_field_map['comment_post_ID'] = $comment_post_ID;
            }
        }

        // parent field
        if ($this->importer->isEnabledField('comment._parent')) {

            $comment_parent = $data->getValue('comment._parent.id');
            if ($comment_parent !== false) {

                $parent_field_type = $data->getValue('comment._parent._id_type');
                switch ($parent_field_type) {
                    case 'id':

                        // ID
                        $comment_parent = intval($comment_parent);
                        break;
                    case 'ref':

                        // reference column
                        $temp_id = $this->get_comment_by_cf('_iwp_ref_id', $comment_parent, $post_type);
                        if (intval($temp_id > 0)) {
                            $comment_parent = intval($temp_id);
                        }
                        break;
                    case 'custom_field':

                        // custom field
                        $parent_custom_field = $data->getValue('comment._parent._custom_field');
                        $temp_id = $this->get_comment_by_cf($parent_custom_field, $comment_parent, $post_type);
                        if (intval($temp_id > 0)) {
                            $comment_parent = intval($temp_id);
                        }
                        break;
                }
            }

            if ($comment_parent > 0) {
                $comment_field_map['comment_parent'] = $comment_parent;
            }
        }

        $data->replace($comment_field_map, 'default');
        return $data;
    }

    /**
     * Find existing post/page/custom by Custom field
     *
     * @param string $field Custom Field Name
     * @param string $value Value to check against reference
     *
     * @return bool
     */
    public function get_post_by_cf($field, $value, $post_type = 'any')
    {

        $query = new \WP_Query(array(
            'post_type' => $post_type,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'cache_results' => false,
            'update_post_meta_cache' => false,
            'meta_query' => array(
                array(
                    'key' => $field,
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
     * Find existing comment by Custom field
     *
     * @param string $field Custom Field Name
     * @param string $value Value to check against reference
     *
     * @return bool
     */
    public function get_comment_by_cf($field, $value, $post_type = 'any')
    {
        $query = new \WP_Comment_Query([
            'fields' => 'ids',
            'update_comment_meta_cache' => false,
            'update_comment_post_cache' => false,
            'no_found_rows' => true,
            'post_type' => $post_type,
            'meta_query' => array(
                array(
                    'key' => $field,
                    'value' => $value
                )
            ),
        ]);

        if (count($query->comments) === 1) {
            return $query->comments[0];
        }

        return false;
    }

    public function get_permission_fields($importer_model)
    {
        $permission_fields = parent::get_permission_fields($importer_model);

        $permission_fields['core'] = [
            'comment_ID' => __('ID', 'jc-importer'),
            'comment_agent' => __('User Agent', 'jc-importer'),
            'comment_approved' => __('Approved', 'jc-importer'),
            'comment_author' => __('Author', 'jc-importer'),
            'comment_author_email' => __('Author Email', 'jc-importer'),
            'comment_author_IP' => __('Ip Address', 'jc-importer'),
            'comment_author_url' => __('Author Url', 'jc-importer'),
            'comment_content' => __('Content', 'jc-importer'),
            'comment_date' => __('Date', 'jc-importer'),
            'comment_date_gmt' => __('Date GMT', 'jc-importer'),
            'comment_karma' => __('Karma', 'jc-importer'),
            'comment_parent' => __('Parent', 'jc-importer'),
            'comment_post_ID' => __('Post ID', 'jc-importer'),
            'comment_type' => __('Comment Type', 'jc-importer'),
            'user_id' => __('User ID', 'jc-importer')
        ];

        return $permission_fields;
    }

    public function get_unique_identifier_options($importer_model, $unique_fields = [])
    {
        $output = parent::get_unique_identifier_options($importer_model, $unique_fields);

        $field_map = [
            'comment_ID' => 'comment.comment_ID',
            'comment_agent' => 'comment.comment_agent',
            'comment_approved' => 'comment.comment_approved',
            'comment_author' => 'comment.comment_author',
            'comment_author_email' => 'comment.comment_author_email',
            'comment_author_IP' => 'comment.comment_author_IP',
            'comment_author_url' => 'comment.comment_author_url',
            'comment_content' => 'comment.comment_content',
            'comment_date' => 'comment.comment_date',
            'comment_date_gmt' => 'comment.comment_date_gmt',
            'comment_karma' => 'comment.comment_karma',
            'comment_parent' => 'comment.comment_parent',
            'comment_post_ID' => 'comment.comment_post_ID',
            'comment_type' => 'comment.comment_type',
            'user_id' => 'comment.user_id',
            '_iwp_ref_id' => 'comment._iwp_ref_id',
        ];
        $optional_fields = array_keys($field_map);

        return array_merge(
            $output,
            $this->get_unique_identifier_options_from_map($importer_model, $unique_fields, $field_map, $optional_fields)
        );
    }
}
