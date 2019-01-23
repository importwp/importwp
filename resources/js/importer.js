var currentNode = [];
var nodeOffset = '';
var jci_element = null;

jQuery(document).ready(function ($) {

    var data = {};
    var base;

    $('#jc-importer_parser_settings-import_base').on('change', function () {
        $.fn.nodeSelect(null);
    });

    $.fn.nodeSelect = function (base_arg) {

        data = {
            action: 'jc_import_file',
            id: ajax_object.id,
            base: $('#jc-importer_parser_settings-import_base').val()
        };

        if (base_arg !== undefined) {
            data.base = base_arg;
        }

        // get xml node offset via import_base
        if(data.base){
        
            base = data.base.split("/");
            nodeOffset = base[base.length - 1];
        }

            // traverse($('#treeView li'), xml.firstChild);

            // this – is an &mdash;
            $('<b>–<\/b>').prependTo('#treeView li:has(li)').click(function () {
                var sign = $(this).text();
                if (sign == "–")
                    $(this).text('+').parent('li').find(' > ul').children().hide();
                else
                    $(this).text('–').parent('li').find(' > ul').children().show();
            });

            var xpath = '';

            $('.xml-draggable').click(function () {
                xpath = $(this).data('xpath');
                jci_element.val('{' + xpath + '}');
                jci_element.trigger("change");
                tb_remove();
            });

    };

});

/**
 * Base Node Selector
 */
jQuery(function ($) {

    // handle on click event
    $('#jc-importer_parser_settings-import_base').click(function () {

        var data = {
            action: 'jc_base_node',
            id: ajax_object.id
        };

        $.post(ajax_object.ajax_url, data, function (xml) {
            $('#xml_preview_box').html(xml);
        });

    });
});

/**
 * XML Edit Node
 */
jQuery(document).ready(function ($) {

    // on edit click show node select modal
    $(document).on('click', '.xml-import .jci-import-edit', function (event) {

        // group set element
        var group = $(this).closest('.jci-node-group');
        var base = $('#jc-importer_parser_settings-import_base').val();

        if ($(this).parent().hasClass('jci-group')) {
            base = base + group.find('.jc-importer_general-group input').val();
        }

        jci_element = $(this).parent().find('input');
        var result = tb_show('Node Select', ajax_object.node_ajax_url + '&type=xml&current='+jci_element.val()+'&base=' + base);
        event.preventDefault();
    });

    $(document).on('click', '.base-node-select', function (event) {

        var _this = $(this);

        if (_this.hasClass('base')) {

            // setting main node
            jci_element = $(this).parent().find('input');
            var result = tb_show('Node Select', ajax_object.base_node_ajax_url+'&current='+jci_element.val());

        } else {

            // setting group node
            var base = $('#jc-importer_parser_settings-import_base').val();
            jci_element = $(this).parent().find('input');
            var result = tb_show('Node Select', ajax_object.base_node_ajax_url+'&current='+jci_element.val() + '&base=' + base);
        }

        event.preventDefault();
    });
});

/**
 * CSV Edit Node
 */
jQuery(function ($) {

    $(document).on('click', '.csv-import .jci-import-edit', function (event) {

        jci_element = $(this).parent().find('input');
        var result = tb_show('Node Select', ajax_object.node_ajax_url + '&type=csv&width=800');
        event.preventDefault();
    });

});


(function ($) {

    /**
     * Function to toggle a checkbox to enable a template field
     * @param  string trigger
     * @param  string target
     * @return void
     */
    $.fn.jci_enableField = function (trigger_str, target_str) {

        var trigger_elem = $('input[name$="[' + trigger_str + ']"]');
        var target_elem = $('#jc-importer_field-' + target_str);

        // check to see if a selector was specified not a form field
        if(!target_elem.length){
            target_elem = $(target_str);
        }

        trigger_elem.on('change', function () {

            var elem = target_elem.parent();
            if (!$(this).is(':checked')) {
                elem.hide();
            } else {
                elem.show();
            }
        });

        trigger_elem.trigger('change');
    };

    /**
     * Function to toggle a checkbox to enable a template field, defaulting to a populated dropdown
     * @param  string trigger_str
     * @param  string target_str  
     * @return void
     */
    $.fn.jci_enableSelectField = function (trigger_str, target_str) {

        var trigger_elem = $('input[name$="[' + trigger_str + ']"]');
        var target_elem_input = $('input#jc-importer_field-' + target_str);
        var target_elem_select = $('select#jc-importer_field-' + target_str);

        var select = target_elem_select[0].outerHTML;
        var input = target_elem_input[0].outerHTML;

        var val = target_elem_input.val();
        var init = 0;

        trigger_elem.on('change', function(){

            if ($(this).is(':checked')) {
                
                if(init == 1){
                    val = $('select#jc-importer_field-' + target_str).val();
                }

                $('select#jc-importer_field-' + target_str).replaceWith(input);

                if(val.length > 0){
                    $('input#jc-importer_field-' + target_str).val(val);
                }

                $('input#jc-importer_field-' + target_str).parent().removeClass("select");
            }else{

                if(init == 1){
                    val = $('input#jc-importer_field-' + target_str).val();    
                }
                
                $('input#jc-importer_field-' + target_str).replaceWith(select);

                if(val.length > 0){
                    $('select#jc-importer_field-' + target_str).val(val);
                }

                $('select#jc-importer_field-' + target_str).parent().addClass("select");
            }
        });

        trigger_elem.trigger('change');
        init = 1;

    }
})(jQuery);

/**
 * Help Tooltips
 */
(function($) {

    var init_tooltips = function(){
        $('.iwp-field__tooltip:not(.iwp-field__tooltip--initialized)').each(function(){

            if($(this).attr('title')) {
                $(this).addClass('iwp-field__tooltip--initialized');
                $(this).tipTip({
                    defaultPosition: "right"
                });
            }else{
                $(this).hide();
            }
        });
    };

    $(document).ready(function(){
        init_tooltips();
    });

    $(document).on('iwp_importer_updated', function(){
        init_tooltips();
    });

})(jQuery);