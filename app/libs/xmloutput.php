<?php

/**
 * Output XML in a html clickable format
 */
class JCI_XMLOutput {

	public $processed_nodes = array();
	public $processed_index_nodes = array();
	public $previous_node = '';
	public $node_index = 0;
	public $last_element = '';
	public $last_depth = 0;
	private $xml = false;
	private $file = false;
	private $prefix = '';
	private $xpath = false;

	public function __construct( $file = '', $xpath = false ) {
		$this->xml = new XMLReader();

		if ( ! empty( $file ) ) {
			$this->read( $file );
		}

		$this->xpath = $xpath;
	}

	/**
	 * Set file to read
	 *
	 * @param  string $file
	 *
	 * @return boolean
	 */
	public function read( $file ) {
		$this->file = $file;

		return $this->xml->open( $file );
	}

	public function output() {

		// load first element

		if ( $this->xpath != false && $this->xpath != '/' ) {

			$this->xml->read();

			// namespace string
			$namespace_attrs = '';

			// load xml from XMLReader and xpath with simplexml
			// $simplexml = simplexml_load_string($this->xml->readOuterXML());
			$simplexml  = simplexml_load_file( $this->file );
			$namespaces = $simplexml->getNamespaces( true );

			// register namespaces on simple xml
			foreach ( $namespaces as $prefix => $namespace ) {
				$simplexml->registerXPathNamespace( $prefix, $namespace );
				$namespace_attrs .= ' xmlns:' . $prefix .= '="' . $namespace . '"';
			}

			$result = $simplexml->xpath( $this->xpath );

			// nothing matches xpath
			if ( ! $result ) {
				return false;
			}

			$xml_string = '';

			foreach ( $result as $r ) {
				$xml_string .= $r->asXml();
			}

			$xml_string = str_replace( '<?xml version="1.0" encoding="UTF-8"?>', '', $xml_string );

			// wrap multiple results in <nodes></nodes> tags
			if ( ! empty( $this->xpath ) ) {
				$xml_string = '<nodes' . $namespace_attrs . '>' . $xml_string . '</nodes>';

				// wrapper
				// todo: fix this so it works without xpath
				$t = explode( '/', $this->xpath );

				$this->prefix = '';
				for ( $x = count( $t ) - 1; $x < count( $t ); $x ++ ) {
					$this->prefix .= "/{$t[$x]}";
				}
			}

			// load xml back into XMLReader
			$this->xml->xml( $xml_string );
		}

		// loop through document
		$this->iterate_element();
	}

	public function iterate_element() {

		while ( $this->xml->read() ) {

			switch ( $this->xml->nodeType ) {
				case XMLReader::ELEMENT:

					$element_name = $this->xml->name;

					$p_node = ! empty( $this->processed_nodes ) ? $this->processed_nodes[ count( $this->processed_nodes ) - 1 ] : false;

					// remove elements from if depth has decreased
					if ( $this->last_depth > $this->xml->depth ) {

						for ( $i = ( $this->xml->depth + 1 ); $i < count( $this->processed_index_nodes ); $i ++ ) {
							$this->processed_index_nodes[ $i ] = array();
						}
					}

					// increase index of element if found
					$found = false;
					if ( ! empty( $this->processed_index_nodes[ $this->xml->depth ] ) ) {

						foreach ( $this->processed_index_nodes[ $this->xml->depth ] as &$node ) {

							if ( isset( $node['n'] ) && $node['n'] == $element_name ) {
								$node['i'] = ( $node['i'] + 1 );
								$found     = true;
							}
						}
					}

					// otherwise reset back to 1 if not found
					if ( ! $found ) {
						$this->processed_index_nodes[ $this->xml->depth ][] = array( 'n' => $element_name, 'i' => 1 );
					}

					echo $this->open_element( $element_name );

					if ( $this->xml->isEmptyElement ) {
						echo $this->close_element( $element_name );
					}


					break;
				case XMLReader::END_ELEMENT:
					echo $this->close_element( $this->xml->name );
					break;
				case XMLReader::TEXT:
				case XMLReader::CDATA:

					echo( $this->xml->value );
					break;
			}

		}
	}

	public function open_element( $element_name ) {

		$this->processed_nodes[] = $this->xml->name;
		$this->last_element      = $this->xml->name;

		if ( $this->xml->depth > $this->last_depth ) {
			echo "<ul>\n";
		}

		$this->last_depth = $this->xml->depth;

		$attrs = '';

		// generate elements xpath
		$temp = $this->get_xpath();

		// output elements class
		$this->class = "class=\"xml-node xml-draggable\"";

		$attrs .= $this->get_attributes();


		return "<li><span  {$this->class}data-xpath=\"" . $temp . "\">&lt;{$element_name}</span>{$attrs}&gt;";
	}

	public function get_xpath( $attr = '' ) {

		if ( $this->xpath && count( $this->processed_index_nodes ) <= 1 ) {
			return $attr;
		}

		$temp = $this->gen_xpath( $this->processed_nodes );


		if ( $this->xpath !== false && ! empty( $this->prefix ) && strpos( $temp, $this->prefix ) === 0 ) {

			$temp = str_replace( $this->prefix, "", $temp );
		}

		$temp .= $attr;

		return $temp;
	}

	public function gen_xpath( $nodes ) {

		$temp = array();

		foreach ( $nodes as $depth => $node ) {

			if ( $this->xpath && $depth <= 0 ) {
				continue;
			}

			foreach ( $this->processed_index_nodes[ $depth ] as $p_node ) {
				if ( $p_node['n'] == $node ) {
					$temp[] = $node . '[' . $p_node['i'] . ']';
				}
			}
		}

		return '/' . implode( '/', $temp );
	}

	public function get_attributes() {

		$output = '';

		// get element attributes
		if ( $this->xml->hasAttributes ) {
			while ( $this->xml->moveToNextAttribute() ) {
				$output .= " <span {$this->class} data-xpath=\"" . $this->get_xpath( '/@' . $this->xml->name ) . "\">{$this->xml->name}=\"{$this->xml->value}\"</span>";
			}
		}

		return $output;
	}

	public function close_element( $element_name ) {

		$prefix = '';
		if ( $this->last_element != $this->xml->name ) {
			if ( $this->xml->depth < $this->last_depth ) {
				echo "</ul>\n";
			}
		}

		array_pop( $this->processed_nodes );

		if ( ! $this->xml->isEmptyElement ) {
			return "{$prefix}&lt;/{$element_name}&gt;</li>\n";
		}

		return "&lt;/{$element_name}&gt;</li>\n";
	}

	/**
	 * Generate array of elements xpath from file
	 *
	 * @param  boolean $show_attrs
	 *
	 * @return array
	 */
	public function generate_xpath( $show_attrs = false ) {

		// retrive parsed list of nodes/attributes
		$nodes = $this->get_nodes( $show_attrs );
		$list  = array();

		// list all possible xpath
		foreach ( $nodes as $depth_nodes ) {

			// display nodes
			foreach ( $depth_nodes as $node ) {
				$list[] = $node['xpath'];

				// skip generating attributes
				if ( ! $show_attrs ) {
					continue;
				}

				// display attributes
				foreach ( $node['attrs'] as $attr ) {
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
	public function get_nodes( $show_attrs = false ) {

		$output = array();

		$previous_depth   = - 1;
		$previous_element = false;
		$element_index    = 0;
		$nodes            = array();
		$xpath_nodes      = array();

		while ( $this->xml->read() ) {

			// if($this->xml->nodeType == XMLReader::TEXT || $this->xml->nodeType == XMLReader::CDATA){
			// 	var_dump($this->xml->value);
			// 	var_dump($previous_element);
			// 	echo "===<br />\n";
			// }

			if ( $this->xml->nodeType == XMLReader::ELEMENT ) {

				$d = $this->xml->depth;
				$e = $this->xml->name;
				$x = '/';
				$p = false;
				$a = array();
				$v = $this->xml->value;

				// var_dump($this->xml->hasValue);

				// use to set element index = /posts/post[0], /posts/post[1]
				if ( $previous_element == $e ) {
					$element_index ++;
				} else {
					$element_index = 0;
				}

				// manage depth
				if ( $d < $previous_depth ) {

					$c = ( $previous_depth - $d );
					for ( $x = 0; $x <= $c; $x ++ ) {

						// parent node
						array_pop( $nodes );
					}

				} elseif ( $d > $previous_depth ) {

					// child node
				} else {

					// partner node, same level
					array_pop( $nodes );
				}

				$nodes[] = $this->xml->name;

				// generate xpath
				$xpath = $x = '/' . implode( '/', $nodes );
				if ( ! in_array( $xpath, $xpath_nodes ) ) {
					$xpath_nodes[] = $xpath;
				}

				// get element attributes
				if ( $show_attrs && $this->xml->hasAttributes ) {
					while ( $this->xml->moveToNextAttribute() ) {
						$a[] = array( 'attr' => $this->xml->name, 'xpath' => $x . '/@' . $this->xml->name );
					}
				}

				// if nodes array is not empty get parent from it
				if ( count( $nodes ) > 1 ) {
					$p = $nodes[ count( $nodes ) - 2 ];
				}

				// create array if no array exists for depth
				if ( ! isset( $output[ $d ] ) ) {
					$output[ $d ] = array();
				}

				// check to see if element doesnt exist in depth array
				if ( ! $this->in_element_array( $e, $output[ $d ], $x, $element_index ) ) {

					$output[ $d ][] = array(
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
	public function in_element_array( $element, $array = array(), $xpath = '/', $index = 0 ) {

		foreach ( $array as $node ) {

			if ( $node['node'] == $element && $node['xpath'] == $xpath && $node['index'] == $index ) {
				return true;
			}
		}

		return false;
	}

	public function __destruct() {
	}
}