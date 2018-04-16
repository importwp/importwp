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
	 * List of errors
	 * @var array
	 */
	private $errors = array();

	private $log = array();

	/**
	 * @var JC_Importer_Template $template
	 */
	protected $template;

	public function __construct(JC_Importer_Template $template) {
		$this->template = $template;
	}

	public function logError($msg){
		array_push($this->errors, $msg);
	}

	/**
	 * Save log of imported data
	 *
	 * @param array  $data
	 * @param string $type
	 * @param string $group Name of data group
	 */
	public function logImport($data, $type, $group){
		$this->appendLog($data, $group);
		switch($type){
			case 'update':
				$this->log[$group]['_jci_type'] = 'U';
				break;
			default:
				$this->log[$group]['_jci_type'] = 'I';
				break;
		}
	}

	public function appendLog($data, $group){
		if(!isset($this->log[$group])){
			$this->log[$group] = array();
		}

		$this->log[$group] = array_merge($this->log[$group], $data);
	}

	public function getLog(){
		return $this->log;
	}

}