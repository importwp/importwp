<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 14/04/2018
 * Time: 17:47
 */

class AbstractMapper {

	/**
	 * Existing Object Id
	 * @var int $ID
	 */
	protected $ID;

	/**
	 * @var JC_Importer_Template $template
	 */
	protected $template;

	public function __construct(JC_Importer_Template $template) {
		$this->template = $template;
	}

}