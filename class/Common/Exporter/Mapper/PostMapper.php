<?php

namespace ImportWP\Common\Exporter\Mapper;

use ImportWP\Common\Exporter\MapperInterface;

class PostMapper extends AbstractMapper implements MapperInterface
{

    private $post_type;
    private $user_data;

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

    public function get_fields()
    {

        global $wpdb;

        $core_fields = $this->get_core_fields();

        $custom_fields = array();

        // add post_thumbnail
        if (post_type_supports($this->post_type, 'thumbnail')) {
            $custom_fields[] = 'post_thumbnail';
        }

        // post author
        $custom_fields[] = 'ewp_author_nicename';
        $custom_fields[] = 'ewp_author_nickname';
        $custom_fields[] = 'ewp_author_first_name';
        $custom_fields[] = 'ewp_author_last_name';
        $custom_fields[] = 'ewp_author_login';
        $custom_fields[] = 'ewp_author_desc';

        // post_meta
        $meta_fields = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT meta_key FROM " . $wpdb->postmeta . " WHERE post_id IN (SELECT DISTINCT ID FROM " . $wpdb->posts . " WHERE post_type='%s')", [$this->post_type]));
        foreach ($meta_fields as $field) {
            $custom_fields[] = 'ewp_cf_' . $field;
        }

        $custom_fields = apply_filters('iwp/exporter/post_type/custom_field_list', $custom_fields, $this->post_type);

        // taxonomies
        $taxonomies = get_object_taxonomies($this->post_type, 'objects');
        foreach ($taxonomies as $tax) {
            $custom_fields[] = 'ewp_tax_' . $tax->name;
            $custom_fields[] = 'ewp_tax_' . $tax->name . '_slug';
            $custom_fields[] = 'ewp_tax_' . $tax->name . '_id';
        }


        return array_merge($core_fields, $custom_fields);
    }

    /**
     * @var WP_Query
     */
    private $query;

    public function have_records()
    {
        $this->query = new \WP_Query(array(
            'post_type' => $this->post_type,
            'posts_per_page' => -1,
            'post_status' => 'any, trash, future',
            'fields' => 'ids',
            'cache_results' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'no_found_rows' => true,
        ));

        return $this->found_records() > 0;
    }

    public function found_records()
    {
        return $this->query->post_count;
    }

    public function get_record($i, $columns)
    {

        $post = get_post($this->query->posts[$i]);
        if (!$post) {
            return false;
        }

        // Meta data
        $meta = get_post_meta($post->ID);

        // Author Details
        $author = $post->post_author;
        $this->user_data = get_userdata($author);

        $row = array();
        foreach ($columns as $column) {
            $row[$column] = $this->get_field($column, $post, $meta);
        }

        if ($this->filter($row, $post, $meta)) {
            return false;
        }

        return $row;
    }

    public function get_field($column, $record, $meta)
    {
        // Core fields
        $core = $this->get_core_fields();

        $output = '';

        $matches = null;
        if (preg_match('/^ewp_tax_(.*?)/', $column) == 1) {

            if (preg_match('/^ewp_tax_(.*?)_slug$/', $column, $matches) == 1) {

                $taxonomy    = $matches[1];
                $found_terms = array();
                $terms       = wp_get_object_terms($record->ID, $taxonomy);
                if (!empty($terms)) {
                    foreach ($terms as $term) {

                        /**
                         * @var WP_Term $term
                         */
                        $found_terms[] = $term->slug;
                    }
                }

                $output = $found_terms;
            } elseif (preg_match('/^ewp_tax_(.*?)_id$/', $column, $matches) == 1) {

                $taxonomy    = $matches[1];
                $found_terms = array();
                $terms       = wp_get_object_terms($record->ID, $taxonomy);
                if (!empty($terms)) {
                    foreach ($terms as $term) {

                        /**
                         * @var WP_Term $term
                         */
                        $found_terms[] = $term->term_id;
                    }
                }

                $output = $found_terms;
            } elseif (preg_match('/^ewp_tax_(.*?)$/', $column, $matches) == 1) {

                $taxonomy    = $matches[1];
                $found_terms = array();
                $terms       = wp_get_object_terms($record->ID, $taxonomy);
                if (!empty($terms)) {
                    foreach ($terms as $term) {

                        /**
                         * @var WP_Term $term
                         */
                        $found_terms[] = $term->name;
                    }
                }

                $output = $found_terms;
            }
        } elseif (preg_match('/^ewp_cf_(.*?)$/', $column, $matches) == 1) {

            $meta_key = $matches[1];
            if (isset($meta[$meta_key])) {
                $output = $meta[$meta_key];
            }
        } else {

            if (in_array($column, $core, true)) {
                $output = $record->{$column};
            } else {
                switch ($column) {
                    case 'post_thumbnail':
                        if (has_post_thumbnail($record)) {
                            $output = wp_get_attachment_url(get_post_thumbnail_id($record));
                        }
                        break;
                }

                if ($this->user_data) {
                    switch ($column) {
                        case 'ewp_author_nicename':
                            $output = $this->user_data->user_nicename;
                            break;
                        case 'ewp_author_nickname':
                            $output = $this->user_data->nickname;
                            break;
                        case 'ewp_author_first_name':
                            $output = $this->user_data->first_name;
                            break;
                        case 'ewp_author_last_name':
                            $output = $this->user_data->last_name;
                            break;
                        case 'ewp_author_login':
                            $output = $this->user_data->user_login;
                            break;
                        case 'ewp_author_desc':
                            $output = $this->user_data->description;
                            break;
                    }
                }
            }
        }

        $output = apply_filters('iwp/exporter/post_type/value', $output, $column, $record, $meta);
        return $output;
    }
}
