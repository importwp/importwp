<?php

namespace ImportWPTests\Common\Attachment;

use ImportWP\Common\Attachment\Attachment;

class AttachmentTest extends \WP_UnitTestCase
{
    public function test_insert_attachment()
    {
        $attachment = new Attachment();

        $parent_id = $this->factory()->post->create();
        $file = IWP_TEST_ROOT . '/data/xml/wp-export.xml';

        $result = $attachment->insert_attachment($parent_id, $file, mime_content_type($file));
        $this->assertNotWPError($result);
        $this->assertGreaterThan(0, $result);
    }

    public function test_store_attachment_hash()
    {
        $wp_attachment = $this->factory()->attachment->create_and_get();
        $attachment = new Attachment();
        $attachment->store_attachment_hash($wp_attachment->ID, get_attached_file($wp_attachment->ID));

        $hash = get_post_meta($wp_attachment->ID, '_iwp_attachment_src', true);
        $this->assertNotEmpty($hash);
    }

    public function test_get_attachment_by_hash()
    {
        $file = IWP_TEST_ROOT . '/data/xml/wp-export.xml';
        $wp_attachment_id = $this->factory()->attachment->create_object(['file' => $file]);
        $attachment_file = get_attached_file($wp_attachment_id);

        $attachment = new Attachment();
        $attachment->store_attachment_hash($wp_attachment_id, $attachment_file);
        $hash = get_post_meta($wp_attachment_id, '_iwp_attachment_src', true);
        $this->assertNotEmpty($hash);

        $result = $attachment->get_attachment_by_hash($attachment_file);
        $this->assertSame($wp_attachment_id, $result);
    }
}
