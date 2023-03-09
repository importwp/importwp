<?php
function iwp_fn_get_posts_by($input = '', $field = 'post_title', $post_type = 'post')
{
    $core_fields = array(
        'ID',
        'menu_order',
        'comment_status',
        'ping_status',
        'pinged',
        'post_author',
        'post_category',
        'post_content',
        'post_date',
        'post_date_gmt',
        'post_excerpt',
        'post_name',
        'post_parent',
        'post_password',
        'post_status',
        'post_title',
        'post_type',
        'tags_input',
        'to_ping',
        'tax_input'
    );

    $query_vars = array(
        'post_name' => 'name',
        'ID'        => 'p'
    );

    $parts = array_values(array_filter(array_map('trim', explode(',', $input))));
    $output = [];
    foreach ($parts as $part) {

        $query_args = array(
            'post_type' => $post_type,
            'post_status' => 'any, trash, future',
            'fields' => 'ids',
            'cache_results' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'no_found_rows' => true,
        );

        if (in_array($field, $core_fields, true)) {

            if (array_key_exists($field, $query_vars)) {
                $query_args[$query_vars[$field]] = $part;
            } else {
                switch ($field) {
                    case 'post_title':
                        $query_args['title'] = $part;
                        break;
                    default:
                        $query_args[$field] = $part;
                        break;
                }
            }
        } else {
            $meta_args[] = array(
                'key'   => $field,
                'value' => $part
            );
        }

        if (!empty($meta_args)) {
            $query_args['meta_query'] = $meta_args;
        }

        $query = new \WP_Query($query_args);
        if (count($query->posts) === 1) {
            $output[] = $query->posts[0];
        }
    }


    return implode(',', $output);
}

function iwp_fn_prefix_items($input = '', $prefix = '', $output_delimiter = ',', $input_delimter = ',')
{
    $items = explode($input_delimter, $input);

    $items = array_map(function ($item) use ($prefix) {
        return $prefix . trim($item);
    }, $items);

    return implode($output_delimiter, $items);
}