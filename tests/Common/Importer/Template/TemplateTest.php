<?php

namespace ImportWPTests\Common\Importer\Template;

use ImportWP\Common\Importer\Template\Template;

class TemplateTest extends \WP_UnitTestCase
{
    public function find_item($array, $id)
    {
        $found = array_filter($array,  function ($item) use ($id) {
            return $item['id'] === $id;
        });

        return !empty($found) ? current($found) : false;
    }

    public function test_register_attachment_fields()
    {
        $template = $this->createPartialMock(Template::class, []);

        $fields = $template->register_attachment_fields();
        $this->assertEquals('attachments', $fields['id']);
        $this->assertEquals('Images & Attachments', $fields['heading']);

        $location_item = $this->find_item($fields['fields'], 'location');
        $this->assertNotFalse($location_item);
        $this->assertEquals('Location', $location_item['label']);

        $setting_item = $this->find_item($fields['fields'], 'settings');
        $this->assertNotFalse($setting_item);

        foreach (['_featured', '_download', '_ftp_host', '_ftp_user', '_ftp_pass', '_ftp_path', '_remote_url', '_local_url', '_enable_image_hash', '_delimiter'] as $sub_field_key) {

            $setting_item_tmp = $this->find_item($setting_item['fields'], $sub_field_key);
            $this->assertNotFalse($setting_item_tmp);
        }

        $meta_item = $this->find_item($setting_item['fields'], '_meta');
        $this->assertNotFalse($meta_item);

        foreach (['_enabled', '_alt', '_title', '_caption', '_description',] as $sub_field_key) {

            $setting_item_tmp = $this->find_item($meta_item['fields'], $sub_field_key);
            $this->assertNotFalse($setting_item_tmp);
        }
    }

    public function test_register_attachment_fields_with_custom_label()
    {
        $template = $this->createPartialMock(Template::class, []);

        $fields = $template->register_attachment_fields('Test Attachment');

        $this->assertEquals('attachments', $fields['id']);
        $this->assertEquals('Test Attachment', $fields['heading']);
    }

    public function test_register_attachment_fields_with_custom_name()
    {
        $template = $this->createPartialMock(Template::class, []);

        $fields = $template->register_attachment_fields('Attachments', 'test-attachments');

        $this->assertEquals('test-attachments', $fields['id']);
        $this->assertEquals('Attachments', $fields['heading']);
    }

    public function test_register_attachment_fields_with_field_label()
    {
        $template = $this->createPartialMock(Template::class, []);

        $fields = $template->register_attachment_fields('Attachments', 'attachments', 'Test Location');

        $this->assertEquals('attachments', $fields['id']);
        $this->assertEquals('Attachments', $fields['heading']);

        $location_item = $this->find_item($fields['fields'], 'location');
        $this->assertNotFalse($location_item);
        $this->assertEquals('Test Location', $location_item['label']);
    }

    public function test_register_attachment_fields_with_attachment_args_disable_meta()
    {
        $template = $this->createPartialMock(Template::class, []);
        $fields = $template->register_attachment_fields('Attachments', 'attachments', 'Location', null, ['disabled_fields' => ['_meta']]);

        $setting_item = $this->find_item($fields['fields'], 'settings');
        $this->assertNotFalse($setting_item);

        $meta_item = $this->find_item($setting_item['fields'], '_meta');
        $this->assertFalse($meta_item);

        $meta_item = $this->find_item($setting_item['fields'], '_featured');
        $this->assertNotFalse($meta_item);
    }

    public function test_register_attachment_fields_with_attachment_args_disable_featured()
    {
        $template = $this->createPartialMock(Template::class, []);
        $fields = $template->register_attachment_fields('Attachments', 'attachments', 'Location', null, ['disabled_fields' => ['_featured']]);

        $setting_item = $this->find_item($fields['fields'], 'settings');
        $this->assertNotFalse($setting_item);

        $meta_item = $this->find_item($setting_item['fields'], '_meta');
        $this->assertNotFalse($meta_item);

        $meta_item = $this->find_item($setting_item['fields'], '_featured');
        $this->assertFalse($meta_item);
    }

    public function test_register_attachment_fields_with_attachment_args_disable_meta_and_featured()
    {
        $template = $this->createPartialMock(Template::class, []);
        $fields = $template->register_attachment_fields('Attachments', 'attachments', 'Location', null, ['disabled_fields' => ['_featured', '_meta']]);

        $setting_item = $this->find_item($fields['fields'], 'settings');
        $this->assertNotFalse($setting_item);

        $meta_item = $this->find_item($setting_item['fields'], '_meta');
        $this->assertFalse($meta_item);

        $meta_item = $this->find_item($setting_item['fields'], '_featured');
        $this->assertFalse($meta_item);
    }
}
