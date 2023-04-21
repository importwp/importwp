<?php

namespace ImportWP\Common\Exporter\Mapper;

use ImportWP\Common\Exporter\MapperInterface;

class PostMapper extends AbstractMapper implements MapperInterface
{

    /**
     * @var WP_Query
     */
    protected $query;
    protected $post_type;

    public function __construct($post_type = 'post')
    {
        $this->post_type = $post_type;
    }

    public function get_core_fields()
    {
        return array(
            'ID',
            'post_author',
            'post_date',
            'post_date_gmt',
            'post_content',
            'post_title',
            'post_excerpt',
            'post_status',
            'comment_status',
            'ping_status',
            'post_password',
            'post_name',
            'to_ping',
            'pinged',
            'post_modified',
            'post_modified_gmt',
            'post_content_filtered',
            'post_parent',
            'guid',
            'menu_order',
            'post_type',
            'post_mime_type',
            'comment_count',
        );
    }

    public function get_post_types_array()
    {
        $post_types = is_string($this->post_type) ? explode(',', $this->post_type) : (array)$this->post_type;
        $post_types = array_values(array_filter(array_map('trim', $post_types)));
        return $post_types;
    }

    public function get_fields()
    {
        /**
         * @var \WPDB
         */
        global $wpdb;

        $fields = [
            'key' => 'main',
            'label' => 'Post',
            'loop' => true,
            'fields' => [],
            'children' => [
                'author' => [
                    'key' => 'author',
                    'label' => 'Author',
                    'loop' => false,
                    'fields' => [],
                    'children' => []
                ],
                'image' => [
                    'key' => 'image',
                    'label' => 'Featured Image',
                    'loop' => false,
                    'fields' => ['id', 'url', 'title', 'alt', 'caption', 'description'],
                    'children' => []
                ],
                'parent' => [
                    'key' => 'parent',
                    'label' => 'Parent',
                    'loop' => false,
                    'fields' => ['id', 'name', 'slug'],
                    'children' => []
                ],
                'custom_fields' => [
                    'key' => 'custom_fields',
                    'label' => 'Custom Fields',
                    'loop' => true,
                    'loop_fields' => ['meta_key', 'meta_value'],
                    'fields' => [],
                    'children' => []
                ]
            ]

        ];

        $fields['fields'] = $this->get_core_fields();

        $post_types = $this->get_post_types_array();

        // add post_thumbnail
        if (post_type_supports($post_types[0], 'thumbnail')) {
            $fields['fields'][] = 'post_thumbnail';
        }

        // post author
        $fields['children']['author']['fields'][] = 'ID';
        $fields['children']['author']['fields'][] = 'user_login';
        $fields['children']['author']['fields'][] = 'user_nicename';
        $fields['children']['author']['fields'][] = 'user_email';
        $fields['children']['author']['fields'][] = 'user_url';
        $fields['children']['author']['fields'][] = 'display_name';

        // post_meta
        $meta_fields = $wpdb->get_col(sprintf("SELECT DISTINCT meta_key FROM " . $wpdb->postmeta . " WHERE post_id IN (SELECT DISTINCT ID FROM " . $wpdb->posts . " WHERE post_type IN ('%s'))", implode("','", $post_types)));
        foreach ($meta_fields as $field) {
            $fields['children']['custom_fields']['fields'][] = $field;
        }

        // taxonomies
        $taxonomies = get_object_taxonomies($post_types[0], 'objects');
        foreach ($taxonomies as $tax) {
            $fields['children']['tax_' . $tax->name] = [
                'key' => 'tax_' . $tax->name,
                'label' => $tax->label,
                'loop' => true,
                'loop_fields' => ['id', 'name', 'slug'],
                'fields' => [
                    'name',
                    'slug',
                    'id',
                    'hierarchy::id',
                    'hierarchy::name',
                    'hierarchy::slug',
                ]
            ];
        }

        $fields['children']['custom_fields']['fields'] = apply_filters('iwp/exporter/post_type/custom_field_list',  $fields['children']['custom_fields']['fields'], $this->post_type);
        $fields = apply_filters('iwp/exporter/post_type/fields', $fields, $this->post_type);

        return $fields;
    }


    public function have_records($exporter_id)
    {
        $post_types = $this->get_post_types_array();

        $query_args = [];
        $query_args = apply_filters('iwp/exporter/post_query', $query_args);
        $query_args = apply_filters(sprintf('iwp/exporter/%d/post_query', $exporter_id), $query_args);

        foreach ($post_types as $post_type) {
            $query_args = apply_filters(sprintf('iwp/exporter/post_query/%s', $post_type), $query_args);
            $query_args = apply_filters(sprintf('iwp/exporter/%d/post_query/%s', $exporter_id, $post_type), $query_args);
        }

        $query_args = wp_parse_args($query_args, [
            'post_type' => $post_types,
            'posts_per_page' => -1,
            'post_status' => 'any, trash, future',
            'fields' => 'ids',
            'cache_results' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'no_found_rows' => true,
            'order' => 'ASC'
        ]);

        $this->query = new \WP_Query($query_args);
        $this->items = $this->query->posts;

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
        $post = get_post($this->items[$i], ARRAY_A);
        if (!$post) {
            return false;
        }

        $this->record = $post;
        $this->record['post_thumbnail'] = wp_get_attachment_url(get_post_thumbnail_id($this->record['ID']));
        $this->record['custom_fields'] = get_post_meta($post['ID']);

        $user = get_userdata($post['post_author']);
        if ($user) {
            $this->record['author'] = (array)$user->data;
        }

        $thumbnail_id = intval(get_post_thumbnail_id($this->record['ID']));
        if ($thumbnail_id > 0) {

            $attachment = get_post($thumbnail_id, ARRAY_A);
            $alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);

            $this->record['image'] = [
                'id' => $thumbnail_id,
                'url' => wp_get_attachment_url($thumbnail_id),
                'title' => $attachment['post_title'],
                'alt' => $alt,
                'caption' => $attachment['post_excerpt'],
                'description' => $attachment['post_content']
            ];
        } else {
            $this->record['image'] = [
                'id' => '',
                'url' => '',
                'title' => '',
                'alt' => '',
                'caption' => '',
                'description' => ''
            ];
        }

        $parent = get_post_parent($this->record['ID']);
        if ($parent) {
            $this->record['parent'] = [
                'id' => $parent->ID,
                'name' => $parent->post_title,
                'slug' => $parent->post_name
            ];
        } else {
            $this->record['parent'] = [
                'id' => '',
                'name' => '',
                'slug' => ''
            ];
        }

        // taxonomies
        $taxonomies = get_object_taxonomies($this->record['post_type'], 'objects');
        foreach (array_keys($taxonomies) as $taxonomy) {
            $tmp = [];

            $terms       = wp_get_object_terms($post['ID'], $taxonomy);
            $tmp = [
                'id' => [],
                'slug' => [],
                'name' => [],
                'hierarchy::id' => [],
                'hierarchy::name' => [],
                'hierarchy::slug' => [],
            ];

            if (!empty($terms)) {
                foreach ($terms as $term) {

                    /**
                     * @var \WP_Term $term
                     */
                    $tmp['id'][] = $term->term_id;
                    $tmp['slug'][] = $term->slug;
                    $tmp['name'][] = $term->name;

                    $ancestor_ids = get_ancestors($term->term_id, $taxonomy, 'taxonomy');
                    // $ancestor_ids = array_reverse($ancestor_ids);

                    $ancestor_tmp = [
                        'id' => [$term->term_id],
                        'name' => [$term->name],
                        'slug' => [$term->slug]
                    ];

                    foreach ($ancestor_ids as $ancestor_id) {

                        $ancestor = get_term($ancestor_id, $taxonomy);
                        if (is_wp_error($ancestor)) {
                            continue;
                        }

                        $ancestor_tmp['id'][] = $ancestor->term_id;
                        $ancestor_tmp['name'][] = $ancestor->name;
                        $ancestor_tmp['slug'][] = $ancestor->slug;
                    }

                    $ancestor_tmp['id'] = array_reverse($ancestor_tmp['id']);
                    $tmp['hierarchy::id'][] = implode(' > ', $ancestor_tmp['id']);

                    $ancestor_tmp['name'] = array_reverse($ancestor_tmp['name']);
                    $tmp['hierarchy::name'][] = implode(' > ', $ancestor_tmp['name']);

                    $ancestor_tmp['slug'] = array_reverse($ancestor_tmp['slug']);
                    $tmp['hierarchy::slug'][] = implode(' > ', $ancestor_tmp['slug']);
                }
            }

            $this->record['tax_' . $taxonomy] = $tmp;
        }

        $this->record = apply_filters('iwp/exporter/post_type/setup_data', $this->record, $this->post_type);

        return true;
    }
}
