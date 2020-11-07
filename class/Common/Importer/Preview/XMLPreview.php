<?php

namespace ImportWP\Common\Importer\Preview;

use ImportWP\Common\Importer\File\XMLFile;
use ImportWP\Common\Importer\PreviewInterface;
use XMLReader;

class XMLPreview implements PreviewInterface
{

    /**
     * @var XMLFile $file
     */
    private $file;

    /**
     * @var \XMLReader $xml
     */
    private $xml;

    /**
     * @var string
     */
    private $record_path;

    /**
     * @var array
     */
    private $record_path_parts = array();

    public $processed_nodes = array();
    public $processed_index_nodes = array();
    public $previous_node = '';
    public $node_index = 0;
    public $last_element = '';
    public $last_depth = 0;
    private $prefix = '';
    private $xpath = false;
    private $class;

    /**
     * XMLPreview constructor.
     *
     * @param \ImportWP\Common\Importer\File\XMLFile $file
     * @param string $record_path
     * @param array $args
     */
    public function __construct(XMLFile $file, $record_path, $args = array())
    {
        $this->file = $file;
        $this->file->processing(true);
        $this->record_path = $record_path;

        if (!empty($record_path) && strpos($record_path, '/') !== false) {
            $this->record_path_parts = explode('/', $record_path);

            // remove empty first tag
            if (empty($this->record_path_parts[0])) {
                array_shift($this->record_path_parts);
            }
        }
    }

    public function data()
    {
        $this->file->setRecordPath($this->record_path);
        $record = $this->file->getRecord();

        $xml = new \XMLReader();
        $xml->xml($record);

        $result = $this->parseXml2($xml);
        return $result;
    }

    private function generateXpath($nodes, $element = false)
    {
        if (count($nodes) < 1) {
            return '';
        }

        $result = array_slice($nodes, 2);
        if ($element) {
            $result[] = $element;
        }

        return !empty($result) ? '/' . implode('/', $result) : '';
    }

    protected function parseXml2(\XMLReader $xml, $path = [])
    {

        $data = [];
        $index = -1;

        while ($xml->read()) {
            switch ($xml->nodeType) {

                case XMLReader::END_ELEMENT:

                    return $data;

                case XMLReader::ELEMENT:

                    $element_path = $path;
                    $element_path[] = $xml->name;

                    $row = [
                        'node' => $xml->name,
                        'type' => 'element',
                        'xpath' => $this->generateXpath($element_path),
                    ];

                    $attributes = [];
                    if ($xml->hasAttributes) {

                        while ($xml->moveToNextAttribute()) {
                            $attributes[] = ['name' => $xml->name, 'value' => $xml->value, 'xpath' => $this->generateXpath($element_path, '@' . $xml->name)];
                        }
                        $xml->moveToElement();
                    }

                    $row['attr'] = $attributes;

                    if (!$xml->isEmptyElement) {
                        $row['value'] = $this->parseXml2($xml, $element_path);
                    }

                    $data[] = $row;
                    break;
                case XMLReader::TEXT:
                case XMLReader::CDATA:
                    $row = [
                        'node' => 'text',
                        'xpath' => $this->generateXpath($path),
                        'value' => "" . $xml->value,
                        'type' => 'text'
                    ];
                    $data[] = $row;
                    break;
            }
        }

        return $data;
    }

    /**
     * Generate a nested list visualising the xml document nodes
     * and attributes based on the XML data being previewed.
     *
     * @return string
     */
    public function output()
    {
        $this->file->setRecordPath($this->record_path);
        $record = $this->file->getRecord();

        return $this->generatePreview($record);
    }

    /**
     * Load xml string into XML Previewer
     *
     * @param string $record XML Record as string
     *
     * @return string
     */
    private function generatePreview($record)
    {
        $this->xml = new \XMLReader();
        $this->xml->xml($record);

        // loop through document
        $output = $this->iterate_element();
        return '<ul>' . $output . '</ul>';
    }

    /**
     * Loop through XML document element by element.
     *
     * @return string
     */
    public function iterate_element()
    {
        $output = '';

        while ($this->xml->read()) {

            switch ($this->xml->nodeType) {
                case XMLReader::ELEMENT:

                    $element_name = $this->xml->name;

                    // remove elements from if depth has decreased
                    if ($this->last_depth > $this->xml->depth) {

                        for ($i = ($this->xml->depth + 1); $i < count($this->processed_index_nodes); $i++) {
                            $this->processed_index_nodes[$i] = array();
                        }
                    }

                    // increase index of element if found
                    $found = false;
                    if (!empty($this->processed_index_nodes[$this->xml->depth])) {

                        foreach ($this->processed_index_nodes[$this->xml->depth] as &$node) {

                            if (isset($node['n']) && $node['n'] == $element_name) {
                                $node['i'] = ($node['i'] + 1);
                                $found     = true;
                            }
                        }
                    }

                    // otherwise reset back to 1 if not found
                    if (!$found) {
                        $this->processed_index_nodes[$this->xml->depth][] = array('n' => $element_name, 'i' => 1);
                    }

                    $output .= $this->open_element($element_name);

                    $isEmpty = $this->xml->isEmptyElement;
                    if ($isEmpty) {
                        $output .= $this->close_element($element_name);
                    }


                    break;
                case XMLReader::END_ELEMENT:
                    $output .= $this->close_element($this->xml->name);
                    break;
                case XMLReader::TEXT:
                case XMLReader::CDATA:

                    $output .= ($this->xml->value);
                    break;
            }
        }

        return $output;
    }

    /**
     * Create string representation of current element and its attributes.
     *
     * @param $element_name
     *
     * @return string
     */
    public function open_element($element_name)
    {
        $output = '';

        $this->processed_nodes[] = $this->xml->name;
        $this->last_element      = $this->xml->name;

        if ($this->xml->depth > $this->last_depth) {
            $output .= "<ul>";
        }

        $this->last_depth = $this->xml->depth;

        $attrs = '';

        // generate elements xpath
        $temp = $this->get_xpath();

        // output elements class
        $this->class = "class=\"xml-node xml-draggable\"";

        if (count($this->processed_nodes) > 1) {
            $attrs  .= $this->get_attributes();
            $output .= "<li><span  {$this->class}data-xpath=\"" . $temp . "\">&lt;{$element_name}</span>{$attrs}&gt;";
        } else {
            $output .= "<li><span>&lt;{$element_name}</span>{$attrs}&gt;";
        }


        return $output;
    }

    /**
     * Get xPath string for current attribute.
     *
     * @param string $attr
     *
     * @return string
     */
    public function get_xpath($attr = '')
    {

        if ($this->xpath && count($this->processed_index_nodes) <= 1) {
            return str_replace('//', '/', $attr);
        }

        $temp = $this->gen_xpath($this->processed_nodes);


        if ($this->xpath !== false && !empty($this->prefix) && strpos($temp, $this->prefix) === 0) {

            $temp = str_replace($this->prefix, "", $temp);
        }

        $temp .= $attr;

        return str_replace('//', '/', $temp);
    }

    /**
     * Generate xPath string for current nodes.
     *
     * @param array $nodes
     *
     * @return string
     */
    public function gen_xpath($nodes)
    {
        // TODO: if base node is set don't return the base node part
        $temp = array();

        foreach ($nodes as $depth => $node) {

            if ($this->xpath && $depth <= 0) {
                continue;
            }

            foreach ($this->processed_index_nodes[$depth] as $p_node) {
                if ($p_node['n'] == $node) {
                    $temp[] = $node . '[' . $p_node['i'] . ']';
                }
            }
        }

        $temp = $this->strip_record_path($temp);

        return '/' . implode('/', $temp);
    }

    /**
     * Create string representation of element attributes
     *
     * @return string
     */
    public function get_attributes()
    {

        $output = '';

        // get element attributes
        if ($this->xml->hasAttributes) {
            while ($this->xml->moveToNextAttribute()) {
                $output .= " <span {$this->class} data-xpath=\"" . $this->get_xpath('/@' . $this->xml->name) . "\">{$this->xml->name}=\"{$this->xml->value}\"</span>";
            }

            $this->xml->moveToElement();
        }

        return $output;
    }

    /**
     * Create string representation of current closing element,
     * and remove it from the currently opened list.
     *
     * @param $element_name
     *
     * @return string
     */
    public function close_element($element_name)
    {
        $output = '';
        $prefix = '';

        if ($this->last_element != $this->xml->name) {
            if ($this->xml->depth < $this->last_depth) {
                $output .= "</ul>";
            }
        }

        array_pop($this->processed_nodes);

        if (!$this->xml->isEmptyElement) {
            $output .= "{$prefix}&lt;/{$element_name}&gt;</li>";
        } else {
            $output .= "&lt;/{$element_name}&gt;</li>";
        }

        return $output;
    }

    /**
     * Generate array of elements xpath from file
     *
     * @param  boolean $show_attrs
     *
     * @return array
     */
    public function generate_xpath($show_attrs = false)
    {

        // retrive parsed list of nodes/attributes
        $nodes = $this->get_nodes($show_attrs);
        $list  = array();

        // list all possible xpath
        foreach ($nodes as $depth_nodes) {

            // display nodes
            foreach ($depth_nodes as $node) {
                $list[] = $node['xpath'];

                // skip generating attributes
                if (!$show_attrs) {
                    continue;
                }

                // display attributes
                foreach ($node['attrs'] as $attr) {
                    $list[] = $attr['xpath'];
                }
            }
        }

        return $list;
    }

    /**
     * Loop through file and retreive array of nodes / attributes
     *
     * @param  boolean $show_attrs
     *
     * @return array
     */
    public function get_nodes($show_attrs = false)
    {

        $output = array();

        $previous_depth   = -1;
        $previous_element = false;
        $element_index    = 0;
        $nodes            = array();
        $xpath_nodes      = array();

        while ($this->xml->read()) {

            // if($this->xml->nodeType == XMLReader::TEXT || $this->xml->nodeType == XMLReader::CDATA){
            // 	var_dump($this->xml->value);
            // 	var_dump($previous_element);
            // 	echo "===<br />";
            // }

            if ($this->xml->nodeType == XMLReader::ELEMENT) {

                $d = $this->xml->depth;
                $e = $this->xml->name;
                $x = '/';
                $p = false;
                $a = array();
                $v = $this->xml->value;

                // var_dump($this->xml->hasValue);

                // use to set element index = /posts/post[0], /posts/post[1]
                if ($previous_element == $e) {
                    $element_index++;
                } else {
                    $element_index = 0;
                }

                // manage depth
                if ($d < $previous_depth) {

                    $c = ($previous_depth - $d);
                    for ($x = 0; $x <= $c; $x++) {

                        // parent node
                        array_pop($nodes);
                    }
                } elseif ($d > $previous_depth) {

                    // child node
                } else {

                    // partner node, same level
                    array_pop($nodes);
                }

                $nodes[] = $this->xml->name;

                // generate xpath
                $xpath = $x = '/' . implode('/', $nodes);
                if (!in_array($xpath, $xpath_nodes)) {
                    $xpath_nodes[] = $xpath;
                }

                // get element attributes
                if ($show_attrs && $this->xml->hasAttributes) {
                    while ($this->xml->moveToNextAttribute()) {
                        $a[] = array('attr' => $this->xml->name, 'xpath' => $x . '/@' . $this->xml->name);
                    }
                }

                // if nodes array is not empty get parent from it
                if (count($nodes) > 1) {
                    $p = $nodes[count($nodes) - 2];
                }

                // create array if no array exists for depth
                if (!isset($output[$d])) {
                    $output[$d] = array();
                }

                // check to see if element doesnt exist in depth array
                if (!$this->in_element_array($e, $output[$d], $x, $element_index)) {

                    $output[$d][] = array(
                        'node'   => $e,
                        'parent' => $p,
                        'index'  => $element_index,
                        'xpath'  => $x,
                        'attrs'  => $a,
                        'depth'  => $d,
                        'value'  => $v

                    );
                }

                // set previous depth to current depth
                $previous_depth   = $d;
                $previous_element = $e;
            }
        }

        return $output;
    }

    /**
     * Check to see if node is in array
     *
     * @param  string $element node name
     * @param  array $array array to compare against
     * @param  string $xpath xpath string for node
     *
     * @return boolean
     */
    public function in_element_array($element, $array = array(), $xpath = '/', $index = 0)
    {

        foreach ($array as $node) {

            if ($node['node'] == $element && $node['xpath'] == $xpath && $node['index'] == $index) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove the xml wrapping element <record> and next node from xpath node list
     *
     * @param array $node_list
     *
     * @return array
     */
    private function strip_record_path($node_list)
    {

        $output = array();
        if (count($node_list) > 2) {
            for ($i = 2; $i < count($node_list); $i++) {
                $output[] = $node_list[$i];
            }
        }

        return $output;
    }
}
