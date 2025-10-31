<?php

namespace ImportWPTests\Common\Importer;

use ImportWP\Common\Importer\Mapper\AbstractMapper;
use ImportWP\Common\Importer\ParsedData;
use ImportWPTests\Utils\ProtectedPropertyTrait;

class ParsedDataTest extends \WP_UnitTestCase
{
    use ProtectedPropertyTrait;

    public function test_update_hash()
    {
        $mock_parsed_data = $this->createPartialMock(ParsedData::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | AbstractMapper $mock_mapper
         */
        $mock_mapper = $this->createPartialMock(AbstractMapper::class, ['update_custom_field']);
        $mock_mapper
            ->expects($this->once())
            ->method('update_custom_field')
            ->with(12, '_iwp_hash', 'hash1234');

        $this->setProtectedProperty($mock_parsed_data, 'mapper', $mock_mapper);
        $this->setProtectedProperty($mock_parsed_data, 'id', 12);

        $mock_parsed_data->update_hash('hash1234');
    }

    public function test_get_last_hash()
    {
        $mock_parsed_data = $this->createPartialMock(ParsedData::class, []);

        $mock_mapper = $this->createPartialMock(AbstractMapper::class, ['get_custom_field']);
        $mock_mapper
            ->expects($this->once())
            ->method('get_custom_field')
            ->with(12, '_iwp_hash', true)
            ->willReturn('hash1234');

        $this->setProtectedProperty($mock_parsed_data, 'mapper', $mock_mapper);
        $this->setProtectedProperty($mock_parsed_data, 'id', 12);

        $this->assertEquals('hash1234', $mock_parsed_data->get_last_hash());
    }

    public function test_hash_compare_default_false()
    {
        $mock_parsed_data = $this->createPartialMock(ParsedData::class, ['get_last_hash']);
        $mock_parsed_data->method('get_last_hash')->willReturn('asd1234');
        $this->assertFalse($mock_parsed_data->hash_compare('asd1234'));

        add_filter('iwp/importer/mapper/hash_check_enabled', '__return_true');
        $this->assertTrue($mock_parsed_data->hash_compare('asd1234'));
    }

    public function test_hash_compare()
    {

        $mock_parsed_data = $this->createPartialMock(ParsedData::class, ['get_last_hash']);
        $mock_parsed_data->method('get_last_hash')->willReturn('asd1234');

        add_filter('iwp/importer/mapper/hash_check_enabled', '__return_true');
        $this->assertTrue($mock_parsed_data->hash_compare('asd1234'));
        $this->assertFalse($mock_parsed_data->hash_compare('Asd1234'));
    }
}
