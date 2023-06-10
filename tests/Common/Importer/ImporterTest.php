<?php

namespace ImportWPTests\Common\Importer;

use ImportWP\Common\Importer\Config\Config;
use ImportWP\Common\Importer\Importer;
use ImportWP\Common\Importer\Parser\XMLParser;

class ImporterTest extends \WP_UnitTestCase
{
    /**
     * @dataProvider privideTestFilterRecords
     */
    public function testFilterRecords($value, $filters, $result)
    {
        $config = $this->createMock(Config::class);
        $importer = new Importer($config);

        $parser = $this->createMock(XMLParser::class);
        $parser->expects($this->any())->method('query_string')->willReturn($value);

        $importer->parser($parser);

        $importer->filter($filters);

        $this->assertEquals($result, $importer->filterRecords());
    }

    private function generateFilterCondition($data, $condition, $right, $result = false)
    {
        return [
            $data,
            [
                [
                    [
                        'left' => '{1}',
                        'right' => $right,
                        'condition' => $condition
                    ]
                ]
            ],
            $result
        ];
    }

    public function privideTestFilterRecords()
    {

        $tests = [

            // equals
            $this->generateFilterCondition('saw', 'equal', 'circular saw'),
            $this->generateFilterCondition('circular saw', 'equal', 'circular saw', true),
            $this->generateFilterCondition('circular saw blade', 'equal', 'circular saw'),
            $this->generateFilterCondition('red circular saw', 'equal', 'circular saw'),
            $this->generateFilterCondition('blue saw', 'equal', 'circular saw'),
            $this->generateFilterCondition('drill', 'equal', 'circular saw'),
            $this->generateFilterCondition('cordless drill', 'equal', 'circular saw'),
            $this->generateFilterCondition('cordless drill bit', 'equal', 'circular saw'),
            $this->generateFilterCondition('red cordless drill', 'equal', 'circular saw'),
            $this->generateFilterCondition('blue drill', 'equal', 'circular saw'),

            // not equals
            $this->generateFilterCondition('saw', 'not-equal', 'circular saw', true),
            $this->generateFilterCondition('circular saw', 'not-equal', 'circular saw'),
            $this->generateFilterCondition('circular saw blade', 'not-equal', 'circular saw', true),
            $this->generateFilterCondition('red circular saw', 'not-equal', 'circular saw', true),
            $this->generateFilterCondition('blue saw', 'not-equal', 'circular saw', true),
            $this->generateFilterCondition('drill', 'not-equal', 'circular saw', true),
            $this->generateFilterCondition('cordless drill', 'not-equal', 'circular saw', true),
            $this->generateFilterCondition('cordless drill bit', 'not-equal', 'circular saw', true),
            $this->generateFilterCondition('red cordless drill', 'not-equal', 'circular saw', true),
            $this->generateFilterCondition('blue drill', 'not-equal', 'circular saw', true),

            // contains: circular saw
            $this->generateFilterCondition('saw', 'contains', 'circular saw'),
            $this->generateFilterCondition('circular saw', 'contains', 'circular saw', true),
            $this->generateFilterCondition('circular saw blade', 'contains', 'circular saw', true),
            $this->generateFilterCondition('red circular saw', 'contains', 'circular saw', true),
            $this->generateFilterCondition('blue saw', 'contains', 'circular saw'),
            $this->generateFilterCondition('drill', 'contains', 'circular saw'),
            $this->generateFilterCondition('cordless drill', 'contains', 'circular saw'),
            $this->generateFilterCondition('cordless drill bit', 'contains', 'circular saw'),
            $this->generateFilterCondition('red cordless drill', 'contains', 'circular saw'),
            $this->generateFilterCondition('blue drill', 'contains', 'circular saw'),

            // contains: circular saw, cordless drill
            $this->generateFilterCondition('saw', 'contains', 'circular saw, cordless drill'),
            $this->generateFilterCondition('circular saw', 'contains', 'circular saw, cordless drill'),
            $this->generateFilterCondition('circular saw blade', 'contains', 'circular saw, cordless drill'),
            $this->generateFilterCondition('red circular saw', 'contains', 'circular saw, cordless drill'),
            $this->generateFilterCondition('blue saw', 'contains', 'circular saw, cordless drill'),
            $this->generateFilterCondition('drill', 'contains', 'circular saw, cordless drill'),
            $this->generateFilterCondition('cordless drill', 'contains', 'circular saw, cordless drill'),
            $this->generateFilterCondition('cordless drill bit', 'contains', 'circular saw, cordless drill'),
            $this->generateFilterCondition('red cordless drill', 'contains', 'circular saw, cordless drill'),
            $this->generateFilterCondition('blue drill', 'contains', 'circular saw, cordless drill'),

            // not-contains: circular saw
            $this->generateFilterCondition('saw', 'not-contains', 'circular saw', true),
            $this->generateFilterCondition('circular saw', 'not-contains', 'circular saw'),
            $this->generateFilterCondition('circular saw blade', 'not-contains', 'circular saw'),
            $this->generateFilterCondition('red circular saw', 'not-contains', 'circular saw'),
            $this->generateFilterCondition('blue saw', 'not-contains', 'circular saw', true),
            $this->generateFilterCondition('drill', 'not-contains', 'circular saw', true),
            $this->generateFilterCondition('cordless drill', 'not-contains', 'circular saw', true),
            $this->generateFilterCondition('cordless drill bit', 'not-contains', 'circular saw', true),
            $this->generateFilterCondition('red cordless drill', 'not-contains', 'circular saw', true),
            $this->generateFilterCondition('blue drill', 'not-contains', 'circular saw', true),

            // in: circular saw, cordless drill
            $this->generateFilterCondition('saw', 'in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('circular saw', 'in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('circular saw blade', 'in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('red circular saw', 'in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('blue saw', 'in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('drill', 'in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('cordless drill', 'in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('cordless drill bit', 'in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('red cordless drill', 'in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('blue drill', 'in', 'circular saw, cordless drill'),

            // not in: circular saw, cordless drill
            $this->generateFilterCondition('saw', 'not-in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('circular saw', 'not-in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('circular saw blade', 'not-in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('red circular saw', 'not-in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('blue saw', 'not-in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('drill', 'not-in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('cordless drill', 'not-in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('cordless drill bit', 'not-in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('red cordless drill', 'not-in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('blue drill', 'not-in', 'circular saw, cordless drill', true),

            // contains-in: circular saw, cordless drill
            $this->generateFilterCondition('saw', 'contains-in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('circular saw', 'contains-in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('circular saw blade', 'contains-in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('red circular saw', 'contains-in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('blue saw', 'contains-in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('drill', 'contains-in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('cordless drill', 'contains-in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('cordless drill bit', 'contains-in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('red cordless drill', 'contains-in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('blue drill', 'contains-in', 'circular saw, cordless drill'),

            // not-contains-in: circular saw, cordless drill
            $this->generateFilterCondition('saw', 'not-contains-in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('circular saw', 'not-contains-in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('circular saw blade', 'not-contains-in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('red circular saw', 'not-contains-in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('blue saw', 'not-contains-in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('drill', 'not-contains-in', 'circular saw, cordless drill', true),
            $this->generateFilterCondition('cordless drill', 'not-contains-in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('cordless drill bit', 'not-contains-in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('red cordless drill', 'not-contains-in', 'circular saw, cordless drill'),
            $this->generateFilterCondition('blue drill', 'not-contains-in', 'circular saw, cordless drill', true),
        ];

        return $tests;
    }
}
