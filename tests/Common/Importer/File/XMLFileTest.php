<?php

namespace ImportWPTests\Common\Importer\File;

use ImportWP\Common\Importer\Config\Config;
use ImportWP\Common\Importer\File\XMLFile;
use ImportWPTests\Utils\ProtectedPropertyTrait;
use SebastianBergmann\Timer\Timer;

class XMLFileTest extends \WP_UnitTestCase
{
    use ProtectedPropertyTrait;

    /**
     * @dataProvider provide_is_record_start_data
     */
    public function test_is_record_start($expected, $current_node, $base_path, $base_path_segments = [], $open_nodes = [])
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | XMLFile
         */
        $mock_file = $this->createPartialMock(XMLFile::class, []);

        $this->setProtectedProperty($mock_file, 'base_path_segments', $base_path_segments);
        $this->setProtectedProperty($mock_file, 'open_nodes', $open_nodes);
        $this->setProtectedProperty($mock_file, 'base_path', $base_path);

        $this->assertEquals($expected, $mock_file->isRecordStart($current_node));
    }

    public function provide_is_record_start_data()
    {
        return [
            [true, 'reviews', 'reviews'],
            [false, 'review', 'reviews'],
            [false, 'review', 'review', ['reviews'], []],
            [true, 'review', 'review', ['reviews'], ['reviews']],
            [false, 'review', 'review', ['reviews'], ['base', 'reviews']],
            [false, 'review', 'review', ['base'], ['base', 'reviews']],
        ];
    }

    /**
     * @dataProvider provide_is_record_end_data
     */
    public function test_is_record_end($expected, $current_node, $base_path, $open_nodes, $base_path_segments, $record_opened)
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | XMLFile
         */
        $mock_file = $this->createPartialMock(XMLFile::class, []);

        $this->setProtectedProperty($mock_file, 'record_opened', $record_opened);
        $this->setProtectedProperty($mock_file, 'base_path_segments', $base_path_segments);
        $this->setProtectedProperty($mock_file, 'open_nodes', $open_nodes);
        $this->setProtectedProperty($mock_file, 'base_path', $base_path);

        $this->assertEquals($expected, $mock_file->isRecordEnd($current_node));
    }

    public function provide_is_record_end_data()
    {
        return [
            [true, 'review', 'review', ['reviews'], ['reviews'], true],
            [false, 'review', 'review', ['reviews'], ['reviews'], false],
            [false, 'review', 'review', ['reviews', 'review', 'reviews'], ['reviews'], true],
            [true, 'review', 'review', ['reviews', 'review', 'reviews'], ['reviews', 'review', 'reviews'], true],
        ];
    }

    public function test_set_record_path()
    {
        $file = new XMLFile(IWP_TEST_ROOT . '/data/xml/data-error-cdata-attr-colon.xml');
        $file->setRecordPath('rss/item');
        $this->assertEquals('item', $this->getProtectedProperty($file, 'base_path'));
    }

    public function test_get_record_count()
    {
        $config = new Config(tempnam(sys_get_temp_dir(), 'xml-config'));
        $file = new XMLFile(IWP_TEST_ROOT . '/data/xml/data-error-cdata-attr-colon.xml', $config);
        $file->setRecordPath('rss/item');
        $count = $file->getRecordCount();
        $this->assertEquals(1, $count);
    }

    public function test_get_record()
    {

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | XMLFile
         */
        $mock_file = $this->createPartialMock(XMLFile::class, ['getIndex', 'getFileHandle', 'getRecordCount']);

        $mock_file->method('getFileHandle')
            ->willReturn(fopen(IWP_TEST_ROOT . '/data/xml/data-error-cdata-attr-colon.xml', 'a+'));

        $mock_file->method('getIndex')
            ->willReturn([50, 39]);

        $mock_file->method('getRecordCount')
            ->willReturn(1);

        $this->assertEquals("<record><item><![CDATA[<a b=':'>c</a>]]></item></record>", $mock_file->getRecord());
    }

    public function test_wrap_with_record_tag()
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | XMLFile
         */
        $mock_file = $this->createPartialMock(XMLFile::class, []);

        $this->assertEquals('<record><item><one>A</one><two>B</two><three>C</three></item></record>', $mock_file->wrapWithRecordTag("<item><one>A</one><two>B</two><three>C</three></item>"));
        $this->assertEquals('<record xmlns:ms="false" xmlns:a="false"><item><ms:one>A</ms:one><a:two>B</a:two><three>C</three></item></record>', $mock_file->wrapWithRecordTag("<item><ms:one>A</ms:one><a:two>B</a:two><three>C</three></item>"));

        $this->assertEquals("<record><item><![CDATA[<a b=':'>c</a>]]></item></record>", $mock_file->wrapWithRecordTag("<item><![CDATA[<a b=':'>c</a>]]></item>"));
    }

    public function test_get_record_count_with_broken_repeating_nodes()
    {
        $config = new Config(tempnam(sys_get_temp_dir(), 'xml-config'));
        $file = new XMLFile(IWP_TEST_ROOT . '/data/xml/broken.xml', $config);
        $file->setRecordPath('reviews/reviews');
        $count = $file->getRecordCount();
        $this->assertEquals(2, $count);
    }

    /**
     * Test xml record counts
     *
     * @dataProvider provide_row_counter_file_list
     *
     * @param string $filename
     * @param int $rows
     */
    public function test_row_counter($filename, $base, $rows)
    {
        // Load a temp config
        $config_file = tempnam(sys_get_temp_dir(), 'config');

        $file = new XMLFile(IWP_TEST_ROOT . $filename, new Config($config_file));
        $file->setRecordPath($base);
        $this->assertEquals($rows, $file->getRecordCount());

        $result = $file->getNextRecord();
        $xml    = simplexml_load_string($result);

        $this->assertEquals('record', $xml->getName());
    }

    public function provide_row_counter_file_list()
    {
        return [
            // '100 rows'         => ['/tmp/temp-100-rows.xml', 'special-root/specialNode', 100],
            // '1000 rows'        => ['/tmp/temp-1000-rows.xml', 'special-root/specialNode', 1000],
            'short'            => ['/data/xml/short.xml', 'root/capture', 2],
            'short_last_chunk' => ['/data/xml/short_last_chunk.xml', 'root/capture', 2],
            'pubmed-example'   => ['/data/xml/pubmed-example.xml', 'PubmedArticleSet/PubmedArticle', 3],
            'incomplete'       => ['/data/xml/incomplete.xml', 'application/block/table/row', 3],
            'orphanet-xml'     => ['/data/xml/orphanet-xml-example.xml', 'JDBOR/DisorderList/Disorder', 3],
            // 'large'            => ['/tmp/temp-large.xml', 'XMI/XMI.content/Model:Package', 28],
            'wpxml'            => ['/data/xml/wp-export.xml', 'rss/channel/item', 3],
            'inmoweb'          => ['/data/xml/feed.inmoweb.es.xml', 'properties/propiedad', 25]
        ];
    }

    public function test_spektrix_short()
    {

        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file = new XMLFile(IWP_TEST_ROOT . '/data/xml/spektrix-short.xml', new Config($config_file));
        $file->setRecordPath('Events/Event');

        $record = $file->getRecord(0);

        $this->assertStringStartsWith('<record xmlns:i="false">', $record);
    }

    public function test_nested_xml_tags()
    {

        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file = new XMLFile(IWP_TEST_ROOT . '/data/xml/nested-xml-tags.xml', new Config($config_file));
        $file->setRecordPath('Events/Event');

        $nodes = $file->get_node_list();

        $this->assertEquals([
            '/Events',
            '/Events/Event',
            '/Events/Event/Id',
            '/Events/Event/Instances',
            '/Events/Event/Instances/Instance',
            '/Events/Event/Instances/Instance/Event',
            '/Events/Event/Instances/Instance/Event/Id',
            '/Events/Event/Instances/Instance/Time',
        ], $nodes);
    }

    public function test_tag_parsing()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file = new XMLFile(IWP_TEST_ROOT . '/data/xml/tags.xml', new Config($config_file));
        $file->setRecordPath('rss/channel/item');

        $nodes = $file->get_node_list();

        $this->assertEquals([
            '/rss',
            '/rss/channel',
            '/rss/channel/item',
            '/rss/channel/item/name',
            '/rss/channel/item/description',
        ], $nodes);

        $record_count =  $file->getRecordCount();
        $this->assertEquals(2, $record_count);
    }

    public function test_broken_xml()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $files       = $this->provide_row_counter_file_list();
        $file        = new XMLFile(IWP_TEST_ROOT . $files['incomplete'][0], new Config($config_file));
        $file->setRecordPath($files['incomplete'][1]);

        $c = 0;
        while ($file->hasNextRecord()) {
            $c++;
            $result = $file->getNextRecord();
            $xml    = simplexml_load_string($result);
            $this->assertEquals('record', $xml->getName());
        }

        $this->assertEquals(3, $file->getRecordCount());
        $this->assertEquals(3, $c);
    }

    public function test_multiple_record_paths()
    {

        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $files       = $this->provide_row_counter_file_list();
        $file        = new XMLFile(IWP_TEST_ROOT . $files['wpxml'][0], new Config($config_file));

        $file->setRecordPath('rss/channel/item/title');
        $record_title = $file->getRecord();
        $this->assertEquals('<record><title>Post One</title></record>', $record_title);

        $file = new XMLFile(IWP_TEST_ROOT . $files['wpxml'][0], new Config($config_file));

        $file->setRecordPath('rss/channel/item');
        $record_item = $file->getRecord();
        $this->assertEquals('<record xmlns:dc="false" xmlns:content="false" xmlns:excerpt="false" xmlns:wp="false"><item>
            <title>Post One</title>
            <link>http://importwp.dev/2017/08/22/post-one/</link>
            <pubDate>Tue, 22 Aug 2017 21:01:24 +0000</pubDate>
            <dc:creator><![CDATA[admin]]></dc:creator>
            <guid isPermaLink="false">http://importwp.dev/2017/08/22/post-one/</guid>
            <description></description>
            <content:encoded><![CDATA[This is the post one\'s content]]></content:encoded>
            <excerpt:encoded><![CDATA[]]></excerpt:encoded>
            <wp:post_id>263</wp:post_id>
            <wp:post_date><![CDATA[2017-08-22 22:01:24]]></wp:post_date>
            <wp:post_date_gmt><![CDATA[2017-08-22 21:01:24]]></wp:post_date_gmt>
            <wp:comment_status><![CDATA[closed]]></wp:comment_status>
            <wp:ping_status><![CDATA[closed]]></wp:ping_status>
            <wp:post_name><![CDATA[post-one]]></wp:post_name>
            <wp:status><![CDATA[publish]]></wp:status>
            <wp:post_parent>0</wp:post_parent>
            <wp:menu_order>0</wp:menu_order>
            <wp:post_type><![CDATA[post]]></wp:post_type>
            <wp:post_password><![CDATA[]]></wp:post_password>
            <wp:is_sticky>0</wp:is_sticky>
            <category domain="category" nicename="cakephp"><![CDATA[CakePHP]]></category>
            <category domain="category" nicename="javascript"><![CDATA[Javascript]]></category>
            <category domain="category" nicename="php"><![CDATA[PHP]]></category>
            <category domain="post_tag" nicename="scripts"><![CDATA[scripts]]></category>
            <category domain="post_tag" nicename="submenu"><![CDATA[submenu]]></category>
            <category domain="post_tag" nicename="toast"><![CDATA[toast]]></category>
            <category domain="category" nicename="wordpress"><![CDATA[Wordpress]]></category>
            <wp:postmeta>
                <wp:meta_key><![CDATA[_yoast_wpseo_focuskw]]></wp:meta_key>
                <wp:meta_value><![CDATA[post-one-123]]></wp:meta_value>
            </wp:postmeta>
            <wp:postmeta>
                <wp:meta_key><![CDATA[_yoast_wpseo_title]]></wp:meta_key>
                <wp:meta_value><![CDATA[This is the post one\'s excerpt]]></wp:meta_value>
            </wp:postmeta>
            <wp:postmeta>
                <wp:meta_key><![CDATA[_yoast_wpseo_metadesc]]></wp:meta_key>
                <wp:meta_value><![CDATA[publish]]></wp:meta_value>
            </wp:postmeta>
            <wp:postmeta>
                <wp:meta_key><![CDATA[_jci_version_205]]></wp:meta_key>
                <wp:meta_value><![CDATA[9]]></wp:meta_value>
            </wp:postmeta>
            <wp:postmeta>
                <wp:meta_key><![CDATA[_pingme]]></wp:meta_key>
                <wp:meta_value><![CDATA[1]]></wp:meta_value>
            </wp:postmeta>
            <wp:postmeta>
                <wp:meta_key><![CDATA[_encloseme]]></wp:meta_key>
                <wp:meta_value><![CDATA[1]]></wp:meta_value>
            </wp:postmeta>
        </item></record>', $record_item);
    }

    public function test_readChunkXmlNodes()
    {
        $chunk0 = file_get_contents(IWP_TEST_ROOT . '/data/xml/chunk-0.txt');
        // $chunk1 = file_get_contents(IWP_TEST_ROOT . '/data/xml/chunk-1.txt');

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | XMLFile
         */
        $mock_file = $this->createPartialMock(XMLFile::class, ['setIndex']);

        $mock_file->expects($this->exactly(82))->method('setIndex');

        $this->setProtectedProperty($mock_file, 'base_path', 'ettevotja');
        $this->setProtectedProperty($mock_file, 'base_path_segments', ['ettevotjad']);


        $timer = new Timer();
        $timer->start();
        $this->setProtectedProperty($mock_file, 'chunk', $chunk0);
        $mock_file->readChunkXmlNodes();
        // $this->setProtectedProperty($mock_file, 'chunk', $chunk1);
        // $mock_file->readChunkXmlNodes();
        $duration = $timer->stop();
    }

    public function test_read_chunk_xml_nodes()
    {
        $chunk0 = file_get_contents(IWP_TEST_ROOT . '/data/xml/chunk-0.txt');
        // $chunk1 = file_get_contents(IWP_TEST_ROOT . '/data/xml/chunk-1.txt');

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | XMLFile
         */
        $mock_file = $this->createPartialMock(XMLFile::class, ['setIndex']);
        $mock_file->expects($this->exactly(82))->method('setIndex');

        $this->setProtectedProperty($mock_file, 'base_path', 'ettevotja');
        $this->setProtectedProperty($mock_file, 'base_path_segments', ['ettevotjad']);

        $timer = new Timer();
        $timer->start();
        $testA = $mock_file->read_chunk_xml_nodes($chunk0);
        // $testB = $mock_file->read_chunk_xml_nodes($testA . $chunk1);
        $duration = $timer->stop();

        $this->assertEquals('</tegevusala_emtak_versioon_tekst', $testA);
    }
}
