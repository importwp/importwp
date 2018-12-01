<?php
/**
 * Repeater Field Template
 *
 * @var string $key
 * @var string $value
 * @var IWP_Base_Template $this
 * @var array $repeater
 */

$values = $this->get_repeater_values($key);

$repeater_index = 0;
if(count($values)) {
	foreach ( array_keys( $values ) as $row_i ) {
		$repeater_index = $row_i > $repeater_index ? $row_i : $repeater_index;
	}
}
?>
<div>

	<div class="taxonomies multi-rows multi-rows--indexed" data-iwp-index="<?php echo $repeater_index; ?>">

		<table class="iwp-table" cellspacing="0" cellpadding="0">
			<thead class="iwp-table__header">
			<tr>
				<th><?php echo $repeater['label']; ?></th>
				<th></th>
			</tr>
			</thead>
			<tbody class="iwp-table__body">

				<?php if(!empty($values)): ?>
                    <?php foreach($values as $i => $row_data): ?>
					<tr class="taxonomy multi-row">
						<td>

							<?php

							foreach($repeater['fields'] as $field){

								$options = isset($field['options']) && !empty($field['options']) ? $field['options'] : false;
								$field_name = sprintf('_iwpr_%s_%d_%s', $key, $i, $field['key']);
								echo JCI_FormHelper::text( 'field[' . $this->_group . ']['.$field_name.']', array(
									'label'   => $field['label'],
									'tooltip' => $field['tooltip'],
									'default' => $this->get_field_value($field_name),
									'class'   => 'xml-drop jci-group field__input field__input--' . $field_name,
									'after'   => ' <a href="#" class="jci-import-edit button button-small" title="Select Data To Map">Select</a><span class="preview-text"></span>',
									'data'    => array(
										'jci-field' => $field_name,
									),
									'wrapper_data' => array(
										'iwp-options' => esc_attr(json_encode($options)),
										'iwp-name' => 'field[' . $this->_group . ']['.$field_name.']'
									),
								) );
							}
							?>
						</td>
						<td>
							<a href="#" class="add-row button"
							   title="Add New <?php echo $repeater['label']; ?>">+</a>
							<a href="#" class="del-row button"
							   title="Delete <?php echo $repeater['label']; ?>">-</a>
						</td>
					</tr>
                    <?php endforeach; ?>
				<?php else: ?>

				<tr class="taxonomy multi-row">
					<td>
						<?php
						foreach($repeater['fields'] as $field){

							$options = isset($field['options']) && !empty($field['options']) ? $field['options'] : false;
							$field_name = sprintf('_iwpr_%s_%d_%s', $key, 0, $field['key']);
							echo JCI_FormHelper::text( 'field[' . $this->_group . ']['.$field_name.']', array(
								'label'   => $field['label'],
								'tooltip' => $field['tooltip'],
								'default' => esc_attr($value),
								'class'   => 'xml-drop jci-group field__input field__input--' . $field_name,
								'after'   => ' <a href="#" class="jci-import-edit button button-small" title="Select Data To Map">Select</a><span class="preview-text"></span>',
								'data'    => array(
									'jci-field' => $field_name,
								),
								'wrapper_data' => array(
									'iwp-options' => esc_attr(json_encode($options)),
									'iwp-name' => 'field[' . $this->_group . ']['.$field_name.']'
								),
							) );
						}
						?>
					</td>
					<td>
						<a href="#" class="add-row button" title="Add New <?php echo $repeater['label']; ?>">+</a>
						<a href="#" class="del-row button"
						   title="Delete <?php echo $repeater['label']; ?>">-</a>
					</td>
				</tr>
				<?php endif; ?>

			</tbody>
		</table>
	</div>


	<!-- /taxonomy section -->
</div>
