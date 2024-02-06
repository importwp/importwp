<?php

namespace ImportWP\Common\Exporter\Mapper;

use ImportWP\Common\Exporter\ExporterRecord;
use ImportWP\Common\Exporter\MapperInterface;

class CommentMapper extends AbstractMapper implements MapperInterface
{
    private $post_type;

    /**
     * @var \WP_Comment_Query
     */
    private $query;

    public function __construct($post_type)
    {
        $this->post_type = $post_type;

        add_filter('iwp/exporter_record/comment', [$this, 'get_record_data'], 10, 3);
    }

    public function get_core_fields()
    {
        return array(
            'comment_ID',
            'comment_post_ID',
            'comment_author',
            'comment_author_email',
            'comment_author_url',
            'comment_author_IP',
            'comment_date',
            'comment_date_gmt',
            'comment_content',
            'comment_karma',
            'comment_approved',
            'comment_agent',
            'comment_type',
            'comment_parent',
            'user_id',
        );
    }


    public function get_fields()
    {

        /**
         * @var \WPDB
         */
        global $wpdb;

        $fields = [
            'key' => 'main',
            'label' => __('Comment', 'jc-importer'),
            'loop' => true,
            'fields' => [],
            'children' => [
                'custom_fields' => [
                    'key' => 'custom_fields',
                    'label' => __('Custom Fields', 'jc-importer'),
                    'loop' => true,
                    'loop_fields' => ['meta_key', 'meta_value'],
                    'fields' => [],
                    'children' => []
                ]
            ]

        ];

        $fields['fields'] = $this->get_core_fields();

        // get comment custom fields
        $meta_fields = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT cm.meta_key FROM " . $wpdb->comments . " as c INNER JOIN " . $wpdb->commentmeta . " as cm ON c.comment_ID = cm.comment_id WHERE c.comment_post_ID IN (SELECT ID from " . $wpdb->posts . " WHERE post_type=%s)", [$this->post_type]));
        $custom_file_fields = apply_filters('iwp/exporter/comment/custom_file_id_fields', []);
        foreach ($meta_fields as $field) {
            $fields['children']['custom_fields']['fields'][] = $field;

            if (in_array($field, $custom_file_fields)) {
                $fields['children']['custom_fields']['fields'][] = $field . '::id';
                $fields['children']['custom_fields']['fields'][] = $field . '::url';
                $fields['children']['custom_fields']['fields'][] = $field . '::title';
                $fields['children']['custom_fields']['fields'][] = $field . '::alt';
                $fields['children']['custom_fields']['fields'][] = $field . '::caption';
                $fields['children']['custom_fields']['fields'][] = $field . '::description';
            }
        }

        $fields['children']['custom_fields']['fields'] = apply_filters('iwp/exporter/comment/custom_field_list', $fields['children']['custom_fields']['fields'], $this->post_type);

        return $this->parse_fields($fields);
    }

    public function have_records($exporter_id)
    {
        $query_args = [];
        $query_args = apply_filters('iwp/exporter/comment_query', $query_args);
        $query_args = apply_filters(sprintf('iwp/exporter/%d/comment_query', $exporter_id), $query_args);
        $query_args = apply_filters('iwp/exporter/comment_query/' . $this->post_type, $query_args);
        $query_args = apply_filters(sprintf('iwp/exporter/%d/comment_query/%s', $exporter_id, $this->post_type), $query_args);

        $query_args = wp_parse_args($query_args, [
            'post_type' => $this->post_type,
            'fields' => 'ids'
        ]);

        $this->query = new \WP_Comment_Query($query_args);
        $this->items = $this->query->comments;

        return $this->found_records() > 0;
    }

    public function found_records()
    {
        return count($this->items);
    }

    public function get_records()
    {
        return $this->items;
    }

    public function setup($i)
    {
        $comment = get_comment($this->items[$i], ARRAY_A);

        $this->record = new ExporterRecord($comment, 'comment');
        $this->record = apply_filters('iwp/exporter/comment/setup_data', $this->record, $this->post_type);

        return true;
    }

    public function get_record_data($value, $key, $record)
    {
        switch ($key) {
            case 'custom_fields':
                $value = get_comment_meta($record['comment_ID']);
                $value = $this->modify_custom_field_data($value, 'comment');
                break;
        }
        return $value;
    }
}
