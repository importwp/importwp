<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 14/04/2018
 * Time: 17:47
 */

class IWP_Mapper {

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

	/**
	 * @var string $method Current method (insert|update|delete)
	 */
	protected $method;

	private $log = array();

	/**
	 * @var IWP_Template $template
	 */
	protected $template;

	protected $permissions;

	public function __construct( IWP_Template $template, $permissions ) {
		$this->template    = $template;
		$this->permissions = $permissions;
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

	public function clearLog(){
		$this->log = array();
	}

	public function getId(){
		return $this->ID;
	}

	/**
	 * Check relevant permissions for action
	 *
	 * @param string $method
	 * @param array $data
	 *
	 * @return array
	 * @throws \ImportWP\Importer\Exception\MapperException
	 */
	protected function checkPermissions($method, $data){

		if ( $method === 'insert' && ( ! isset( $this->permissions['create'] ) || intval( $this->permissions['create'] ) !== 1 ) ) {
			throw new \ImportWP\Importer\Exception\MapperException( "No Enough Permissions to Insert Record");
		}

		if ( $method === 'update' && ( ! isset( $this->permissions['update'] ) || intval( $this->permissions['update'] ) !== 1 ) ) {
			throw new \ImportWP\Importer\Exception\MapperException( "No Enough Permissions to Update Record");
		}

		if ( $method === 'delete' && ( ! isset( $this->permissions['delete'] ) || intval( $this->permissions['delete'] ) !== 1 ) ) {
			throw new \ImportWP\Importer\Exception\MapperException( "No Enough Permissions to Delete Record");
		}

		$data = apply_filters('iwp/import_mapper_permissions', $data, $method);

		return $data;
	}

	protected function applyFieldFilters($fields, $field_type){

		if(!empty($fields)){
			foreach($fields as $key => $value){
				$raw_value = $value;
				$value = apply_filters( sprintf( 'iwp/%s_field', $field_type ) , $value, $raw_value, $key, $this );
				$value = apply_filters( sprintf( 'iwp/%s_field/%s', $field_type, $key ) , $value, $raw_value, $this );
				$fields[$key] = $value;
			}
		}

		return $fields;
	}

}