<?php

namespace ImportWP\Common\Importer\Preview;

use ImportWP\Common\Importer\File\XMLFile;
use ImportWP\Common\Importer\PreviewInterface;

class XMLNodePreview implements PreviewInterface
{

    /**
     * @var XMLFile $file
     */
    private $file;

    /**
     * XMLNodePreview constructor.
     *
     * @param \ImportWP\Common\Importer\File\XMLFile $file
     * @param array $args
     */
    public function __construct(XMLFile $file, $args = array())
    {
        $this->file = $file;
    }

    /**
     * Generate a list of possible XML Base nodes based on the XML data being previewed.
     *
     * @return string
     */
    public function output()
    {
        $output = '';
        $nodes  = $this->file->get_node_list();
        if (!empty($nodes)) {
            $output = implode('</li><li>', $nodes);
            $output = '<li>' . $output . '</li>';
        }

        return '<ul>' . $output . '</ul>';
    }
}
