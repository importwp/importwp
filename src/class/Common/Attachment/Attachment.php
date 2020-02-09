<?php

namespace ImportWP\Common\Attachment;

class Attachment
{
    public function insert_attachment($parent_id, $dest, $mime, $args = array())
    {

        $title = isset($args['title']) ? $args['title'] : false;
        $alt = isset($args['alt']) ? $args['alt'] : false;
        $caption = isset($args['caption']) ? $args['caption'] : false;
        $description = isset($args['description']) ? $args['description'] : false;

        $attachment = array(
            'post_mime_type' => $mime,
            'post_parent' => $parent_id,
            'post_title' => !empty($title) ? $title : preg_replace('/\.[^.]+$/', '', basename($dest)),
            'post_content' => !empty($description) ? $description : '',
            'post_excerpt'   => !empty($caption) ? $caption : '',
            'post_status' => 'inherit'
        );

        if (!empty($alt)) {
            $attachment['meta_input'] = array('_wp_attachment_image_alt' => $alt);
        }

        $attachment_id = wp_insert_attachment($attachment, $dest, $parent_id, true);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        return $attachment_id;
    }

    public function store_attachment_hash($attachment_id, $dest)
    {
        update_post_meta($attachment_id, '_iwp_attachment_src', md5($dest));
    }

    public function get_attachment_by_hash($dest)
    {
        global $wpdb;
        $hash = md5($dest);
        $query = $wpdb->prepare("SELECT p.ID FROM {$wpdb->postmeta} as pm INNER JOIN {$wpdb->posts} as p ON pm.post_id = p.ID WHERE p.post_type='attachment' AND pm.meta_key='_iwp_attachment_src' AND pm.meta_value=%s ORDER BY p.ID DESC LIMIT 1", [$hash]);
        return intval($wpdb->get_var($query));
    }

    public function generate_image_sizes($attachment_id, $source)
    {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attachment_id, $source);
        wp_update_attachment_metadata($attachment_id, $attach_data);
    }
}
