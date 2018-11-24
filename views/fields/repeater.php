<?php
/**
 * Repeater Field Template
 *
 * @var string $key
 * @var string $value
 * @var IWP_Base_Template $this
 * @var array $repeater
 */

$field_base = 'field[' . $this->_group . ']['.$key.']';
$field_key = sprintf('field_%s_%s', $this->get_group(), $key);
$values = $this->get_field_value($key);
?>
<div>

	<div id="test-taxonomies" class="taxonomies multi-rows">

		<table class="iwp-table" cellspacing="0" cellpadding="0">
			<thead class="iwp-table__header">
			<tr>
				<th><?php echo $repeater['label']; ?></th>
				<th></th>
			</tr>
			</thead>
			<tbody class="iwp-table__body">

				<?php if(!empty($values['iwp_repeater_index'])): ?>
                    <?php foreach($values['iwp_repeater_index'] as $i => $index): ?>
					<tr class="taxonomy multi-row">
						<td>

							<?php

							echo JCI_FormHelper::hidden( $field_base . "[iwp_repeater_index][]", array(
								'default' => 1
							) );

							foreach($repeater['fields'] as $field){

								echo JCI_FormHelper::text( $field_base . sprintf("[%s][]",  $field['key']), array(
									'label'   => $field['label'],
									'tooltip' => $field['tooltip'],
									'default' => $values[$field['key']][$i],
									'class'   => 'xml-drop jci-group',
									'after'   => ' <a href="#" class="jci-import-edit button button-small" title="Select Data To Map">Select</a><span class="preview-text"></span>',
									'data'    => array(
										'jci-field' => $field_key . sprintf('_%s_%d', $field['key'], $i),
									)
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

							echo JCI_FormHelper::hidden( $field_base . "[iwp_repeater_index][]", array(
								'default' => 1
							) );

							echo JCI_FormHelper::text( $field_base . sprintf("[%s][]",  $field['key']), array(
								'label'   => $field['label'],
								'tooltip' => $field['tooltip'],
								'default' => esc_attr($value),
								'class'   => 'xml-drop jci-group',
								'after'   => ' <a href="#" class="jci-import-edit button button-small" title="Select Data To Map">Select</a><span class="preview-text"></span>',
								'data'    => array(
									'jci-field' => $key,
								)
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
