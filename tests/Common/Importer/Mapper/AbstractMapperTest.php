<?php

namespace ImportWPTests\Common\Importer\Mapper;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ImportWP\Common\Importer\Mapper\AbstractMapper;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\Template\Template;
use ImportWP\Common\Model\ImporterModel;
use ImportWPTests\Utils\ProtectedPropertyTrait;

/**
 * @group Mapper
 * @group Core
 */
class AbstractMapperTest extends \WP_UnitTestCase
{
    use ArraySubsetAsserts;
    use ProtectedPropertyTrait;

    public function create_abstract_mapper_mock($has_custom_unique_identifier, $has_field_unique_identifier, $get_setting_callback = null, $mapper_methods = [])
    {
        /**
         * @var ImporterModel | \PHPUnit\Framework\MockObject\MockObject $importer_model
         */
        $importer_model = $this->createPartialMock(ImporterModel::class, [
            'has_custom_unique_identifier',
            'has_field_unique_identifier',
            'getSetting',
        ]);
        $importer_model->method('has_custom_unique_identifier')
            ->willReturn($has_custom_unique_identifier);
        $importer_model->method('has_field_unique_identifier')
            ->willReturn($has_field_unique_identifier);

        if (!is_null($get_setting_callback)) {
            $importer_model->method('getSetting')
                ->willReturnCallback($get_setting_callback);
        }

        /**
         * @var AbstractMapper | \PHPUnit\Framework\MockObject\MockObject $mapper
         */
        $mapper = $this->createPartialMock(AbstractMapper::class, $mapper_methods);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        return $mapper;
    }

    public function test_exists_get_identifier_with_custom()
    {
        // Missing Ref ID in data

        /**
         * @var ParsedData | \PHPUnit\Framework\MockObject\MockObject $data
         */
        $data = $this->createPartialMock(ParsedData::class, []);

        $mapper = $this->create_abstract_mapper_mock(true, true);
        list($unique_fields, $meta_args, $has_unique_field) = $mapper->exists_get_identifier($data);
        $this->assertEmpty($unique_fields);
        $this->assertTrue($has_unique_field);
        $this->assertCount(1, $meta_args);
        $this->assertEquals('_iwp_ref_uid', $meta_args[0]['key']);
        $this->assertTrue($meta_args[0]['value'] === '', 'string is not empty');


        // Empty Ref ID in data

        /**
         * @var ParsedData | \PHPUnit\Framework\MockObject\MockObject $data
         */
        $data = $this->createPartialMock(ParsedData::class, []);
        $data->add([
            '_iwp_ref_uid' => ''
        ], 'iwp');

        $mapper = $this->create_abstract_mapper_mock(true, true);
        list($unique_fields, $meta_args, $has_unique_field) = $mapper->exists_get_identifier($data);
        $this->assertEmpty($unique_fields);
        $this->assertTrue($has_unique_field);
        $this->assertCount(1, $meta_args);
        $this->assertEquals('_iwp_ref_uid', $meta_args[0]['key']);
        $this->assertTrue($meta_args[0]['value'] === '', 'string is not empty');


        // With Ref ID in data

        /**
         * @var ParsedData | \PHPUnit\Framework\MockObject\MockObject $data
         */
        $data = $this->createPartialMock(ParsedData::class, []);
        $data->add([
            '_iwp_ref_uid' => '123'
        ], 'iwp');

        $mapper = $this->create_abstract_mapper_mock(true, true);
        list($unique_fields, $meta_args, $has_unique_field) = $mapper->exists_get_identifier($data);
        $this->assertEmpty($unique_fields);
        $this->assertEquals([[
            'key' => '_iwp_ref_uid',
            'value' => '123'
        ]], $meta_args);
        $this->assertTrue($has_unique_field);

        // With Ref ID in data in wrong group

        /**
         * @var ParsedData | \PHPUnit\Framework\MockObject\MockObject $data
         */
        $data = $this->createPartialMock(ParsedData::class, []);
        $data->add([
            '_iwp_ref_uid' => '123'
        ], 'default');

        $mapper = $this->create_abstract_mapper_mock(true, true);
        list($unique_fields, $meta_args, $has_unique_field) = $mapper->exists_get_identifier($data);
        $this->assertEmpty($unique_fields);
        $this->assertTrue($has_unique_field);
        $this->assertCount(1, $meta_args);
        $this->assertEquals('_iwp_ref_uid', $meta_args[0]['key']);
        $this->assertTrue($meta_args[0]['value'] === '', 'string is not empty');
    }

    public function test_exists_get_identifier_with_field()
    {
        // 

        /**
         * @var ParsedData | \PHPUnit\Framework\MockObject\MockObject $data
         */
        $data = $this->createPartialMock(ParsedData::class, []);
        $mapper = $this->create_abstract_mapper_mock(false, true, function ($key) {
            return null;
        });
        list($unique_fields, $meta_args, $has_unique_field) = $mapper->exists_get_identifier($data);
        $this->assertFalse($has_unique_field);
        $this->assertEmpty($unique_fields);
        $this->assertEmpty($meta_args);

        // 

        /**
         * @var ParsedData | \PHPUnit\Framework\MockObject\MockObject $data
         */
        $data = $this->createPartialMock(ParsedData::class, []);
        $mapper = $this->create_abstract_mapper_mock(false, true, function ($key) {
            return $key == 'unique_identifier' ? 'ID' : null;
        });
        list($unique_fields, $meta_args, $has_unique_field) = $mapper->exists_get_identifier($data);
        $this->assertFalse($has_unique_field);
        $this->assertEquals(['ID'], $unique_fields);
        $this->assertEmpty($meta_args);

        // check if unique identifier = ''

        /**
         * @var ParsedData | \PHPUnit\Framework\MockObject\MockObject $data
         */
        $data = $this->createPartialMock(ParsedData::class, []);
        $mapper = $this->create_abstract_mapper_mock(false, true, function ($key) {
            return $key == 'unique_identifier' ? '' : null;
        });
        list($unique_fields, $meta_args, $has_unique_field) = $mapper->exists_get_identifier($data);
        $this->assertFalse($has_unique_field);
        $this->assertEmpty($unique_fields);
        $this->assertEmpty($meta_args);

        // check if unique identifier = false

        /**
         * @var ParsedData | \PHPUnit\Framework\MockObject\MockObject $data
         */
        $data = $this->createPartialMock(ParsedData::class, []);
        $mapper = $this->create_abstract_mapper_mock(false, true, function ($key) {
            return $key == 'unique_identifier' ? false : null;
        });
        list($unique_fields, $meta_args, $has_unique_field) = $mapper->exists_get_identifier($data);
        $this->assertFalse($has_unique_field);
        $this->assertEmpty($unique_fields);
        $this->assertEmpty($meta_args);
    }

    /**
     * @dataProvider provide_exists_get_identifier_with_template
     */
    public function test_exists_get_identifier_with_template($mapper_type, $expected_unique_fields)
    {
        /**
         * @var ParsedData | \PHPUnit\Framework\MockObject\MockObject $data
         */
        $data = $this->createPartialMock(ParsedData::class, []);
        $mapper = $this->create_abstract_mapper_mock(false, false, null, ['getUniqueIdentifiers']);
        $mapper->method('getUniqueIdentifiers')->willReturnArgument(0);
        $template = $this->createPartialMock(Template::class, ['get_mapper']);
        $template->method('get_mapper')->willReturn($mapper_type);
        $this->setProtectedProperty($mapper, 'template', $template);
        list($unique_fields, $meta_args, $has_unique_field) = $mapper->exists_get_identifier($data);
        $this->assertEquals($expected_unique_fields, $unique_fields);
        $this->assertEmpty($meta_args);
        $this->assertFalse($has_unique_field);
    }

    public function provide_exists_get_identifier_with_template()
    {
        return [
            ['user', ['user_email', 'user_login']],
            ['post', ['ID', 'post_name']],
            ['page', ['ID', 'post_name']],
            ['custom-post-type', ['ID', 'post_name']],
            ['attachment', ['ID', 'post_name', 'src']],
            ['term', ['term_id', 'slug', 'name']],
            ['comment', ['comment_ID', '_iwp_ref_id']],
        ];
    }
}
