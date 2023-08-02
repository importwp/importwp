<?php

namespace ImportWPTests\Common\Importer\Template;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ImportWP\Common\Importer\Mapper\TermMapper;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\Template\TermTemplate;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\EventHandler;
use ImportWPTests\Utils\MockDataTrait;

/**
 * @group Template
 * @group Core
 */
class TermTemplateTest extends \WP_UnitTestCase
{
    use ArraySubsetAsserts;
    use MockDataTrait;

    public function testRegisterOptions()
    {

        $template = new TermTemplate(new EventHandler());
        $options = $template->register_options();

        $expected = [
            [
                'id' => 'taxonomy',
                'type' => 'field',
                'options' => [
                    1 => ['value' => 'category'],
                    2 => ['value' => 'post_tag'],
                ]
            ]

        ];

        $this->assertArraySubset($expected, $options);
    }

    public function testRegister()
    {
        $template = new TermTemplate(new EventHandler());
        $fields = $template->register();

        $expected = [
            [
                'id' => 'term',
                'type' => 'group',
                'fields' => [
                    [
                        'id' => 'term_id',
                        'type' => 'field'
                    ],
                    [
                        'id' => 'name',
                        'type' => 'field'
                    ],
                    [
                        'id' => 'description',
                        'type' => 'field'
                    ],
                    [
                        'id' => 'slug',
                        'type' => 'field'
                    ],
                    [
                        'id' => '_parent',
                        'type' => 'group',
                        'fields' => [
                            [
                                'id' => 'parent',
                                'type' => 'field'
                            ],
                            [
                                'id' => '_parent_type',
                                'type' => 'select'
                            ],
                            [
                                'id' => '_parent_ref',
                                'type' => 'field'
                            ]
                        ]
                    ],
                    [
                        'id' => 'alias_of',
                        'type' => 'field'
                    ]
                ]
            ]
        ];

        $this->assertArraySubset($expected, $fields);
    }

    /**
     * @dataProvider perProcessProvider
     */
    public function testPreProcess($enabled, $expected)
    {
        $mapper = $this->createMock(TermMapper::class);
        $data = new ParsedData($mapper);

        // Data passed from front end
        $data->update([
            'term.name' => 'A',
            'term.term_id' => 'B',
            'term.alias_of' => 'C',
            'term.description' => 'D',
            'term._parent.parent' => 'E',
            'term.slug' => 'F'
        ]);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('isEnabledField')->will($this->returnCallback(function ($field) use ($enabled) {
            if (in_array($field, $enabled, true)) {
                return true;
            }
            return false;
        }));
        // $importer_model->method('isEnabledField')->willReturn(false);

        $template = new TermTemplate(new EventHandler());
        $template->register_hooks($importer_model);
        $response = $template->pre_process($data);

        $this->assertEqualSetsWithIndex($expected, $response->getData());
    }

    public function perProcessProvider()
    {
        return [
            [
                [],
                [
                    'name' => 'A',
                    // 'slug' => sanitize_title('A')
                ]
            ],
            [
                ['term.slug'],
                [
                    'name' => 'A',
                    'slug' => 'F'
                ]
            ],
            [
                ['term.description'],
                [
                    'name' => 'A',
                    // 'slug' => sanitize_title('A'),
                    'description' => 'D',
                ]
            ],
            [
                ['term.alias_of'],
                [
                    'name' => 'A',
                    // 'slug' => sanitize_title('A'),
                    'alias_of' => 'C'
                ]
            ],
            [
                ['term._parent'],
                [
                    'name' => 'A',
                    // 'slug' => sanitize_title('A'),
                    'parent' => 'E'
                ]
            ]
        ];
    }

    public function testGetTermByCF()
    {
        $taxonomy = 'category';
        $term1 = $this->factory()->term->create_and_get(['taxonomy' => $taxonomy]);
        $term2 = $this->factory()->term->create_and_get(['taxonomy' => $taxonomy]);

        $field = '_iwp_ref_test';
        $value = 'one';

        update_term_meta($term1->term_id, $field, $value);
        update_term_meta($term2->term_id, $field, 'two');

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->will($this->returnCallback(function ($key) use ($taxonomy) {
            return $key === 'taxonomy' ? $taxonomy : null;
        }));

        $template = new TermTemplate(new EventHandler());
        $template->register_hooks($importer_model);
        $result_id = $template->get_term_by_cf($field, $value);
        $this->assertEquals($term1->term_id, $result_id);
    }

    /**
     * @dataProvider providerGetTermParentOptionsData
     */
    public function testGetTermParentOptions($taxonomy)
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->will($this->returnCallback(function ($key) use ($taxonomy) {
            return $key === 'taxonomy' ? $taxonomy : null;
        }));

        $template = new TermTemplate(new EventHandler());
        $terms = $template->get_term_parent_options($importer_model);

        $expected_merged = [];
        if ($taxonomy === 'category') {
            $expected_merged[] = ['value' => 1, 'label' => 'Uncategorized'];
        }

        $this->assertEqualSets($expected_merged, $terms);

        $term1 = $this->factory()->term->create_and_get(['taxonomy' => $taxonomy]);
        $term2 = $this->factory()->term->create_and_get(['taxonomy' => $taxonomy]);

        $terms = $template->get_term_parent_options($importer_model);

        $expected_merged[] = ['value' => $term1->term_id, 'label' => $term1->name];
        $expected_merged[] = ['value' => $term2->term_id, 'label' => $term2->name];

        $this->assertEqualSets($expected_merged, $terms);
    }

    public function providerGetTermParentOptionsData()
    {
        return [
            'category' => ['category'],
            'post_tag' => ['post_tag'],
        ];
    }
}
