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
            ['label' => 'Any', 'value' => '']
        ];
        foreach ($post_types as $post_type => $label) {
            $post_type_options[] = ['label' => $label, 'value' => $post_type];
        }

        return [
            $this->register_group('Comment', 'comment', [

                // Comment
                $this->register_field('ID', 'comment_ID', [
                    'tooltip' => __('ID is only used to reference existing records', 'importwp')
                ]),
                $this->register_field('Content', 'comment_content', [
                    'tooltip' => __('The content of the comment.', 'importwp')
                ]),
                $this->register_group('Comment Parent', '_parent', [

                    $this->register_field('Comment Parent', 'id', [
                        'default' => '',
                        'options' => 'callback',
                        'tooltip' => __('ID of this comment\'s parent, if any.', 'importwp')
                    ]),
                    $this->register_field('Comment Post Field Type', '_id_type', [
                        'default' => 'id',
                        'options' => [
                            ['value' => 'id', 'label' => 'ID'],
                            ['value' => 'ref', 'label' => 'Comment Ref Field'],
                            ['value' => 'custom_field', 'label' => 'Custom Field'],
                        ],
                        'type' => 'select',
                        'tooltip' => __('Select how the post ID field should be handled', 'importwp')
                    ]),
                    $this->register_field('Custom Field Key', '_custom_field', [
                        'condition' => ['_id_type', '==', 'custom_field'],
                        'tooltip' => __('Enter the name of the post\'s custom field.', 'importwp')
                    ])

                ]),
                $this->register_group('Comment Post', '_post', [
                    $this->register_field('Comment Post', 'id', [
                        'default' => '',
                        'tooltip' => __('ID of the post that relates to the comment, if any.', 'importwp')
                    ]),
                    $this->register_field('Comment Post Field Type', '_id_type', [
                        'default' => 'id',
                        'options' => [
                            ['value' => 'id', 'label' => 'ID'],
                            ['value' => 'slug', 'label' => 'Slug'],
                            ['value' => 'name', 'label' => 'Name'],
                            ['value' => 'custom_field', 'label' => 'Custom Field'],
                        ],
                        'type' => 'select',
                        'tooltip' => __('Select how the post ID field should be handled', 'importwp')
                    ]),
                    $this->register_field('Custom Field Key', '_custom_field', [
                        'condition' => ['_id_type', '==', 'custom_field'],
                        'tooltip' => __('Enter the name of the post\'s custom field.', 'importwp')
                    ])
                ]),
                $this->register_field('Comment Type', 'comment_type', [
                    'default' => 'comment',
                    'tooltip' => __('Comment type.', 'importwp')
                ]),

                // Author
                $this->register_field('Author ID', 'user_id', [
                    'tooltip' => __('Author user id.', 'importwp')
                ]),
                $this->register_field('Author Name', 'comment_author', [
                    'tooltip' => __('The name of the author of the comment.', 'importwp')
                ]),
                $this->register_field('Author Email', 'comment_author_email', [
                    'tooltip' => __('The email address of the Comment Author', 'importwp')
                ]),
                $this->register_field('Author Url', 'comment_author_url', [
                    'tooltip' => __('The URL address of the Comment Author.', 'importwp')
                ]),
                $this->register_field('Comment Author IP', 'comment_author_IP', [
                    'tooltip' => __('The IP address of the Comment Author.', 'importwp')
                ]),

                // Meta
                $this->register_field('Ref', '_iwp_ref_id', [
                    'tooltip' => __('A custom field to uniquely identify the comment.', 'importwp')
                ]),
                $this->register_field('Post Type', 'post_type', [
                    'options' => $post_type_options,
                    'default' => '',
                    'tooltip' => __('The post type the comment belongs to.', 'importwp')
                ]),
                $this->register_field('Comment Approved', 'comment_approved', [
                    'options' => [
                        ['value' => '0', 'label' => 'Disapproved'],
                        ['value' => '1', 'label' => 'Approved']
                    ],
                    'default' => '1',
                    'tooltip' => __('Whether the comment has been approved. 1 = Approved, 0 = Disapproved', 'importwp')
                ]),
                $this->register_field('Comment Karma', 'comment_karma', [
                    'default' => '0',
                    'tooltip' => __('The karma of the comment.', 'importwp')
                ]),
                $this->register_field('Comment Agent', 'comment_agent', [
                    'tooltip' => __('The HTTP user agent of the Comment Author when the comment was submitted.', 'importwp')
                ]),
                $this->register_field('Comment Date', 'comment_date', [
                    'tooltip' => __('The date the comment was submitted.', 'importwp')
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
                'label' => 'Comment #' . $result
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
            'comment_ID' => 'ID',
            'comment_agent' => 'User Agent',
            'comment_approved' => 'Approved',
            'comment_author' => 'Author',
            'comment_author_email' => 'Author Email',
            'comment_author_IP' => 'Ip Address',
            'comment_author_url' => 'Author Url',
            'comment_content' => 'Content',
            'comment_date' => 'Date',
            'comment_date_gmt' => 'Date GMT',
            'comment_karma' => 'Karma',
            'comment_parent' => 'Parent',
            'comment_post_ID' => 'Post ID',
            'comment_type' => 'Comment Type',
            'user_id' => 'User ID'
        ];

        return $permission_fields;
    }
}
