<?php
/**
 * XML Base node selector view
 *
 * Display list of avaliable nodes from current xml file
 * @todo: add xml result count
 */
?>
<div class="jci-node-select">

    <div class="jci-heading">
        <div class="jci-left">
            <h1>Choose Base Node</h1>
            <p>Select the XML base node for this importer</p>
        </div>
        <div class="jci-right">
            <select id="jci-node-selector">
                <option value="choose-one">Please Choose One</option>
                <option value="">Leave Empty</option>
				<?php if ( ! empty( $nodes ) ): ?>
					<?php foreach ( $nodes as $node ): ?>
                        <option value="<?php echo $node; ?>" <?php selected( $node, $current_base_node, true ); ?>><?php echo $node; ?></option>
					<?php endforeach; ?>
				<?php endif; ?>
            </select>
            <span class="spinner preview-loading" style="display: none;"></span>
        </div>
    </div>

    <div id="jci-node-select-preview" class="jci-preview-block"></div>

    <div class="jci-footer">
        <div class="jci-right">
            <a class="button-primary jci-select-node">Submit</a>
        </div>
        <div class="jci-right">
            <p>Total Records: <span id="jci-record-count"></span></p>
        </div>
    </div>

    <script type="text/javascript">

        // set base node once clicked
        jQuery(function ($) {

            var base_node = '';
            var base_node_parent = '<?php echo $base_node; ?>';

            $('#jci-node-selector').on('change', function () {

                if ($(this).val() == 'choose-one') {
                    return false;
                }

                base_node = $(this).val();
                var output_base_node = base_node_parent + base_node;

                data = {
                    action: 'jc_preview_xml_base_bode',
                    id: ajax_object.id,
                    base: base_node_parent + base_node
                };

                $('.jci-node-select .preview-loading').css("visibility","visible").show();
                $('#jci-node-select-preview').html('');

                $.post(ajax_object.ajax_url, data, function (xml) {

                    $('#jci-node-select-preview').html(xml.data);
                }).always(function () {
                    $('.jci-node-select .preview-loading').hide();
                }).fail(function(e){
                    $('#jci-node-select-preview').html('<p>An error has occurred when choosing a base node: '+e.responseJSON.data.error.message + '</p>' );
                });

                // get max amount of records which chosen base node
                if (output_base_node == '') {
                    output_base_node = '/';
                }

                // Remove Record count
                $.ajax({
                    url: ajax_object.ajax_url,
                    data: {
                        action: 'jc_record_total',
                        id: ajax_object.id,
                        general_base: output_base_node
                    },
                    dataType: 'json',
                    type: "POST",
                    success: function (response) {

                        $('#jci-record-count').text(response);
                    }
                })
            });

            $('a.jci-select-node').click(function (event) {
                event.preventDefault();

                jci_element.val(base_node);
                jci_element.trigger("change");
                tb_remove();
                return false;
            });

            $('#jci-node-selector').trigger('change');
        });
    </script>
</div>