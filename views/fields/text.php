<?php
/**
 * @var string $key
 * @var string $value
 * @var IWP_Template_Base $this
 */

$options = isset($this->_fields[$key]['options']) ? $this->_fields[$key]['options'] : false;

echo '<div class="iwp-field">';

echo IWP_FormBuilder::text( 'field[' . $this->_group . '][' . $key . ']', array(
	'label'   => $this->get_field_title($key),
	'tooltip' => $this->get_field_tooltip($key),
	'default' => esc_attr($value),
	'class'   => 'xml-drop jci-group field__input field__input--'.$key,
	'after'   => ' <a href="#" class="jci-import-edit button button-small" title="Select Data To Map">Select</a><span class="preview-text"></span>',
	'wrapper_data' => array(
		'iwp-options' => esc_attr(json_encode($options)),
		'iwp-name' => 'field[' . $this->_group . '][' . $key . ']'
	),
	'data'    => array(
		'jci-field' => $key,
	)
) );

if(isset($this->_fields[$key]['after'])){
	call_user_func_array($this->_fields[$key]['after'], array($key));
}

echo '</div>';