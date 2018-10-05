<?php
/**
 * Display preview record box and display preview text under template fields.
 *
 * @todo ammend to work with multiple groups, currently only works with one
 */
$jci_template_type = $jcimporter->importer->template_type;
?>
<div id="postimagediv" class="postbox">
    <h3><span>Preview Settings</span><span id="preview-loading" class="spinner"></span></h3>

    <div class="inside">
        <p>Choose the record you wish to preview</p>
        <input type="text" id="preview-record" value="1"/>
        <a href="#" id="prev-record" class="button button-iwp" title="View Previous Record">&laquo;</a> <a href="#"
                                                                                                           id="next-record"
                                                                                                           class="button button-iwp"
                                                                                                           title="View Next Record">&raquo;</a>
        <script type="text/javascript">
            jQuery(function ($) {
                var preview_record = $('#preview-record');
                var min_record = 1;
                $('#prev-record').click(function () {
                    var val = parseInt(preview_record.val());
                    if (val - 1 >= min_record) {
                        preview_record.val(val - 1);
                        preview_record.trigger('change');
                    }
                    return false;
                });
                $('#next-record').click(function () {
                    var val = parseInt(preview_record.val());
                    preview_record.val(val + 1);
                    preview_record.trigger('change');
                    return false;
                });
                // new record preview
                $(document).on('change', '.xml-drop input', function () {
                    var val = $(this).val();
                    var obj = $(this).parent();
                    var field = $(this).data('jci-field');
                    if (field === undefined) {
                        field = '';
                    }
                    if (val != '') {
                        $.ajax({
                            url: ajax_object.ajax_url,
                            data: {
                                action: 'jc_preview_record',
                                id: ajax_object.id,
                                field: field,
								<?php if($jci_template_type == 'xml'): ?>map: val,
                                row: $('#preview-record').val(),
                                general_base: $('#jc-importer_parser_settings-import_base').val(),
                                group_base: $('input[id^="jc-importer_parser_settings-group-"]').val()
								<?php else: ?>map: val,
                                row: $('#preview-record').val()
								<?php endif; ?>
                            },
                            dataType: 'json',
                            type: "POST",
                            beforeSend: function () {
                                obj.find('.preview-text').text('Loading...');
                                $('#preview-loading').show();
                            },
                            complete: function () {
                                clearLoading();
                            },
                            success: function (response) {
                                $.each(response, function (index, value) {
                                    obj.find('.preview-text').text('Preview: ' + value[1]);
                                });
                            },
                            error: function(e){
                                obj.find('.preview-text').text('Preview:');
                                iwp.onError(e);
                            }
                        });
                    } else {
                        obj.find('.preview-text').text('Preview:');
                    }
                });
                // change all inputs
                function refreshPreview() {
                    var nodes = [];
                    var mappings = [];
                    $('.xml-drop input').each(function () {
                        var input_val = $(this).val();
                        mappings.push({
                            map: input_val,
                            field: $(this).data('jci-field')
                        });
                        if (input_val != '' && $.inArray(input_val, nodes) == -1) {
                            // add to array if unique and not empty
                            nodes.push(input_val);
                        }
                    });
                    if(nodes.length > 0) {
                        $.ajax({
                            url: ajax_object.ajax_url,
                            data: {
                                action: 'jc_preview_record',
                                id: ajax_object.id,
								<?php if($jci_template_type == 'xml'): ?>map: mappings,
                                row: $('#preview-record').val(),
                                general_base: $('#jc-importer_parser_settings-import_base').val(),
                                group_base: $('input[id^="jc-importer_parser_settings-group-"]').val()
								<?php else: ?>map: mappings,
                                row: $('#preview-record').val()
								<?php endif; ?>
                            },
                            dataType: 'json',
                            type: "POST",
                            beforeSend: function () {
                                $('#preview-loading').show();
                                $('.preview-text').text('Loading...');
                            },
                            complete: function () {
                                clearLoading();
                            },
                            success: function (response) {
                                $.each(response, function (index, value) {
                                    $('.xml-drop input').each(function () {
                                        if ($(this).val() == value[0]) {
                                            $(this).parent().find('.preview-text').text('Preview: ' + value[1]);
                                        }
                                    })
                                });
                            },
                            error: function(e){
                                $('.preview-text').text('Preview:');
                                iwp.onError(e);
                            }
                        });
                    }else{
                        $('.preview-text').text('Preview:');
                    }
                }
                function clearLoading(){
                    $('#preview-loading').hide();
                    $('.xml-drop input').each(function () {
                        if ($(this).val() ===  '') {
                            $(this).parent().find('.preview-text').text('Preview: ');
                        }
                    });
                }
                $('#preview-record').change('change', function () {
                    refreshPreview();
                });
				<?php if($jci_template_type == 'xml'): ?>$('#jc-importer_parser_settings-import_base, input[id^="jc-importer_parser_settings-group-"]').on('change', function () {
                    refreshPreview();
                });
				<?php endif; ?>
                $('#preview-record').val($('#jc-importer_start-line').val());

                window.iwp.onProcessComplete.add(function(){
                    console.log('load preview');
                    $('#preview-record').trigger('change');
                });
            });
        </script>
    </div>
</div>