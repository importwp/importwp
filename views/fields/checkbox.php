<?php
/**
 * @var string $key
 * @var string $value
 * @var IWP_Template_Base $this
 */

echo '<div class="iwp-field">';

echo IWP_FormBuilder::checkbox( 'field[' . $this->_group . '][' . $key . ']', array(
	'label'   => $this->get_field_title($key),
	'tooltip' => $this->get_field_tooltip($key),
	'checked' => $value === '1',
	'wrapper_data' => array(
		'iwp-name' => 'field[' . $this->_group . '][' . $key . ']'
	)
) );

echo '</div>';