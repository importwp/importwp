<?php

namespace ImportWP\Common\Importer;

/**
 * Class DataParser
 *
 * Run all template queries on xml record.
 *
 * @package ImportWP\Common\Importer
 */
class DataParser
{
    private $config_data;

    /**
     * @var ParserInterface $parser
     */
    private $parser;

    /**
     * @var MapperInterface $mapper
     */
    private $mapper;

    public function __construct(ParserInterface $parser, MapperInterface $mapper, $config_data)
    {
        $this->parser      = $parser;
        $this->config_data = $config_data;
        $this->mapper      = $mapper;
    }

    /**
     * Get record from parser matching the template
     *
     * @param int $index
     *
     * @return ParsedData
     */
    public function get($index)
    {

        $parsed_data = new ParsedData($this->mapper);

        if ($this->config_data) {
            foreach ($this->config_data as $group) {
                $data = $this->parser->getRecord($index)
                    ->queryGroup($group);

                $parsed_data->add($data, $group);
            }
        }

        return $parsed_data;
    }
}
