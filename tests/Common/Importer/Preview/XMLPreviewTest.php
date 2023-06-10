<?php

namespace ImportWPTests\Common\Importer\Preview;

use ImportWP\Common\Importer\Config\Config;
use ImportWP\Common\Importer\File\XMLFile;
use ImportWP\Common\Importer\Preview\XMLNodePreview;
use ImportWP\Common\Importer\Preview\XMLPreview;

class XMLPreviewTest extends \WP_UnitTestCase
{
    public function testXMLPreview()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file        = new XMLFile(IWP_TEST_ROOT . '/data/xml/wp-export.xml', new Config($config_file));
        $preview = new XMLPreview($file, 'rss/channel/item');

        $this->assertEquals(
            '<ul><li><span>&lt;record</span>&gt;<ul><li><span  class="xml-node xml-draggable"data-xpath="/">&lt;item</span>&gt;<ul><li><span  class="xml-node xml-draggable"data-xpath="/title[1]">&lt;title</span>&gt;Post One&lt;/title&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/link[1]">&lt;link</span>&gt;http://importwp.dev/2017/08/22/post-one/&lt;/link&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/pubDate[1]">&lt;pubDate</span>&gt;Tue, 22 Aug 2017 21:01:24 +0000&lt;/pubDate&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/dc:creator[1]">&lt;dc:creator</span>&gt;admin&lt;/dc:creator&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/guid[1]">&lt;guid</span> <span class="xml-node xml-draggable" data-xpath="/guid[1]/@isPermaLink">isPermaLink="false"</span>&gt;http://importwp.dev/2017/08/22/post-one/&lt;/guid&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/description[1]">&lt;description</span>&gt;&lt;/description&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/content:encoded[1]">&lt;content:encoded</span>&gt;This is the post one\'s content&lt;/content:encoded&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/excerpt:encoded[1]">&lt;excerpt:encoded</span>&gt;&lt;/excerpt:encoded&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:post_id[1]">&lt;wp:post_id</span>&gt;263&lt;/wp:post_id&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:post_date[1]">&lt;wp:post_date</span>&gt;2017-08-22 22:01:24&lt;/wp:post_date&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:post_date_gmt[1]">&lt;wp:post_date_gmt</span>&gt;2017-08-22 21:01:24&lt;/wp:post_date_gmt&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:comment_status[1]">&lt;wp:comment_status</span>&gt;closed&lt;/wp:comment_status&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:ping_status[1]">&lt;wp:ping_status</span>&gt;closed&lt;/wp:ping_status&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:post_name[1]">&lt;wp:post_name</span>&gt;post-one&lt;/wp:post_name&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:status[1]">&lt;wp:status</span>&gt;publish&lt;/wp:status&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:post_parent[1]">&lt;wp:post_parent</span>&gt;0&lt;/wp:post_parent&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:menu_order[1]">&lt;wp:menu_order</span>&gt;0&lt;/wp:menu_order&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:post_type[1]">&lt;wp:post_type</span>&gt;post&lt;/wp:post_type&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:post_password[1]">&lt;wp:post_password</span>&gt;&lt;/wp:post_password&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:is_sticky[1]">&lt;wp:is_sticky</span>&gt;0&lt;/wp:is_sticky&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/category[1]">&lt;category</span> <span class="xml-node xml-draggable" data-xpath="/category[1]/@domain">domain="category"</span> <span class="xml-node xml-draggable" data-xpath="/category[1]/@nicename">nicename="cakephp"</span>&gt;CakePHP&lt;/category&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/category[2]">&lt;category</span> <span class="xml-node xml-draggable" data-xpath="/category[2]/@domain">domain="category"</span> <span class="xml-node xml-draggable" data-xpath="/category[2]/@nicename">nicename="javascript"</span>&gt;Javascript&lt;/category&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/category[3]">&lt;category</span> <span class="xml-node xml-draggable" data-xpath="/category[3]/@domain">domain="category"</span> <span class="xml-node xml-draggable" data-xpath="/category[3]/@nicename">nicename="php"</span>&gt;PHP&lt;/category&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/category[4]">&lt;category</span> <span class="xml-node xml-draggable" data-xpath="/category[4]/@domain">domain="post_tag"</span> <span class="xml-node xml-draggable" data-xpath="/category[4]/@nicename">nicename="scripts"</span>&gt;scripts&lt;/category&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/category[5]">&lt;category</span> <span class="xml-node xml-draggable" data-xpath="/category[5]/@domain">domain="post_tag"</span> <span class="xml-node xml-draggable" data-xpath="/category[5]/@nicename">nicename="submenu"</span>&gt;submenu&lt;/category&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/category[6]">&lt;category</span> <span class="xml-node xml-draggable" data-xpath="/category[6]/@domain">domain="post_tag"</span> <span class="xml-node xml-draggable" data-xpath="/category[6]/@nicename">nicename="toast"</span>&gt;toast&lt;/category&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/category[7]">&lt;category</span> <span class="xml-node xml-draggable" data-xpath="/category[7]/@domain">domain="category"</span> <span class="xml-node xml-draggable" data-xpath="/category[7]/@nicename">nicename="wordpress"</span>&gt;Wordpress&lt;/category&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[1]">&lt;wp:postmeta</span>&gt;<ul><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[1]/wp:meta_key[1]">&lt;wp:meta_key</span>&gt;_yoast_wpseo_focuskw&lt;/wp:meta_key&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[1]/wp:meta_value[1]">&lt;wp:meta_value</span>&gt;post-one-123&lt;/wp:meta_value&gt;</li></ul>&lt;/wp:postmeta&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[2]">&lt;wp:postmeta</span>&gt;<ul><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[2]/wp:meta_key[1]">&lt;wp:meta_key</span>&gt;_yoast_wpseo_title&lt;/wp:meta_key&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[2]/wp:meta_value[1]">&lt;wp:meta_value</span>&gt;This is the post one\'s excerpt&lt;/wp:meta_value&gt;</li></ul>&lt;/wp:postmeta&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[3]">&lt;wp:postmeta</span>&gt;<ul><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[3]/wp:meta_key[1]">&lt;wp:meta_key</span>&gt;_yoast_wpseo_metadesc&lt;/wp:meta_key&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[3]/wp:meta_value[1]">&lt;wp:meta_value</span>&gt;publish&lt;/wp:meta_value&gt;</li></ul>&lt;/wp:postmeta&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[4]">&lt;wp:postmeta</span>&gt;<ul><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[4]/wp:meta_key[1]">&lt;wp:meta_key</span>&gt;_jci_version_205&lt;/wp:meta_key&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[4]/wp:meta_value[1]">&lt;wp:meta_value</span>&gt;9&lt;/wp:meta_value&gt;</li></ul>&lt;/wp:postmeta&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[5]">&lt;wp:postmeta</span>&gt;<ul><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[5]/wp:meta_key[1]">&lt;wp:meta_key</span>&gt;_pingme&lt;/wp:meta_key&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[5]/wp:meta_value[1]">&lt;wp:meta_value</span>&gt;1&lt;/wp:meta_value&gt;</li></ul>&lt;/wp:postmeta&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[6]">&lt;wp:postmeta</span>&gt;<ul><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[6]/wp:meta_key[1]">&lt;wp:meta_key</span>&gt;_encloseme&lt;/wp:meta_key&gt;</li><li><span  class="xml-node xml-draggable"data-xpath="/wp:postmeta[6]/wp:meta_value[1]">&lt;wp:meta_value</span>&gt;1&lt;/wp:meta_value&gt;</li></ul>&lt;/wp:postmeta&gt;</li></ul>&lt;/item&gt;</li></ul>&lt;/record&gt;</li></ul>',
            $preview->output()
        );
    }

    public function testXMLDataPreview()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file        = new XMLFile(IWP_TEST_ROOT . '/data/xml/wp-export.xml', new Config($config_file));
        $preview = new XMLPreview($file, 'rss/channel/item');

        $preview_data = $preview->data();
        // $this->assertArrayHasKey('attr', $preview_data[0]);
        // $this->assertArrayHasKey('value', $preview_data[0]);
        $this->assertEquals('record', $preview_data[0]['node']);
        $this->assertEmpty($preview_data[0]['xpath']);
        $this->assertEquals([
            ['name' => 'xmlns:dc', 'value' => 'false', 'xpath' => '/@xmlns:dc'],
            ['name' => 'xmlns:content', 'value' => 'false', 'xpath' => '/@xmlns:content'],
            ['name' => 'xmlns:excerpt', 'value' => 'false', 'xpath' => '/@xmlns:excerpt'],
            ['name' => 'xmlns:wp', 'value' => 'false', 'xpath' => '/@xmlns:wp'],
        ], $preview_data[0]['attr']);
        $this->assertNotEmpty($preview_data[0]['value']);

        // $this->assertEqualsCanonicalizing([
        //     [
        //         'node' => 'record',
        //         'attr' => [
        //             'xmlns:excerpt' => "http://wordpress.org/export/1.2/excerpt/",
        //             'xmlns:content' => "http://purl.org/rss/1.0/modules/content/",
        //             'xmlns:dc' => "http://purl.org/dc/elements/1.1/",
        //             'xmlns:wp' => "http://wordpress.org/export/1.2/",
        //         ]
        //     ]
        // ], $preview->data());
    }

    public function testXMLNodePreview()
    {

        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file        = new XMLFile(IWP_TEST_ROOT . '/data/xml/wp-export.xml', new Config($config_file));
        $preview = new XMLNodePreview($file);

        $this->assertEquals(
            '<ul><li>/rss</li><li>/rss/channel</li><li>/rss/channel/title</li><li>/rss/channel/link</li><li>/rss/channel/description</li><li>/rss/channel/pubDate</li><li>/rss/channel/language</li><li>/rss/channel/wp:wxr_version</li><li>/rss/channel/wp:base_site_url</li><li>/rss/channel/wp:base_blog_url</li><li>/rss/channel/wp:author</li><li>/rss/channel/wp:author/wp:author_id</li><li>/rss/channel/wp:author/wp:author_login</li><li>/rss/channel/wp:author/wp:author_email</li><li>/rss/channel/wp:author/wp:author_display_name</li><li>/rss/channel/wp:author/wp:author_first_name</li><li>/rss/channel/wp:author/wp:author_last_name</li><li>/rss/channel/generator</li><li>/rss/channel/item</li><li>/rss/channel/item/title</li><li>/rss/channel/item/link</li><li>/rss/channel/item/pubDate</li><li>/rss/channel/item/dc:creator</li><li>/rss/channel/item/guid</li><li>/rss/channel/item/description</li><li>/rss/channel/item/content:encoded</li><li>/rss/channel/item/excerpt:encoded</li><li>/rss/channel/item/wp:post_id</li><li>/rss/channel/item/wp:post_date</li><li>/rss/channel/item/wp:post_date_gmt</li><li>/rss/channel/item/wp:comment_status</li><li>/rss/channel/item/wp:ping_status</li><li>/rss/channel/item/wp:post_name</li><li>/rss/channel/item/wp:status</li><li>/rss/channel/item/wp:post_parent</li><li>/rss/channel/item/wp:menu_order</li><li>/rss/channel/item/wp:post_type</li><li>/rss/channel/item/wp:post_password</li><li>/rss/channel/item/wp:is_sticky</li><li>/rss/channel/item/category</li><li>/rss/channel/item/wp:postmeta</li><li>/rss/channel/item/wp:postmeta/wp:meta_key</li><li>/rss/channel/item/wp:postmeta/wp:meta_value</li></ul>',
            $preview->output()
        );
    }

    public function testXMLCloseNodeTagWithAttribute()
    {
        $xml = '<Parents><Parent><ChildSelfClose i:nil="true"/><Child>One</Child></Parent></Parents>';

        $xml_file_path = tempnam(sys_get_temp_dir(), 'xml');
        file_put_contents($xml_file_path, $xml);

        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file        = new XMLFile($xml_file_path, new Config($config_file));
        $preview = new XMLPreview($file, 'Parents');
        $preview_data = $preview->data();

        $this->assertEquals('record', $preview_data[0]['node']);
        $this->assertEquals('Parents', $preview_data[0]['value'][0]['node']);
        $this->assertEquals('Parent', $preview_data[0]['value'][0]['value'][0]['node']);

        $this->assertEquals('ChildSelfClose', $preview_data[0]['value'][0]['value'][0]['value'][0]['node']);
        $this->assertEquals([['name' => 'i:nil', 'value' => 'true', 'xpath' => '/Parent/ChildSelfClose/@i:nil']], $preview_data[0]['value'][0]['value'][0]['value'][0]['attr']);

        $this->assertEquals('Child', $preview_data[0]['value'][0]['value'][0]['value'][1]['node']);
    }
}
