(function($, window, settings){

    // global iwp
    window.iwp = window.iwp || {

        isProcessed: (settings.processed === 'yes'),

        init: function(){
            console.log('iwp.init');
            console.log('iwp.isProcessing', this.isProcessed);

            if(this.isProcessed === false) {

                var self = this;
                $('#processing').show();

                $.ajax({
                    url: ajax_object.ajax_url,
                    data: {
                        action: 'iwp_process',
                        id: ajax_object.id,
                    },
                    dataType: 'json',
                    type: "POST",
                    beforeSend: function () {
                        $('#poststuff').fadeOut();
                    },
                    complete: function () {

                    },
                    success: function (response) {

                        if( false === response.success ){
                            iwp.onError('An Error has occurred when processing your file, ' + response.data);
                            $('#processing .preview-loading').hide();
                            $('#poststuff').fadeIn();
                            $('#processing').hide();
                            return;
                        }

                        self.isProcessed = true;
                        $('#processing p').text('File Processed Successfully.');
                        $('#processing .preview-loading').hide();
                        $('#poststuff').fadeIn();
                        console.log('SUCCESS', response);
                        self.onProcessComplete.run(self);

                        // hide message after a period of time
                        setTimeout(function(){
                            $('#processing').slideUp();
                        }, 2000);
                    },
                    error: function (e) {
                        $('#processing').hide();
                        iwp.onError('An Error has occurred when processing your file, ' + JSON.parse(e.responseText).data.error.message);
                    }
                });
            }else{
                this.onProcessComplete.run(this);
            }
        },
        ready: function(){
            console.log('iwp.ready');
            console.log('iwp.isProcessing', this.isProcessed);
        },
        onError: function(e){
            $('#ajaxResponse').html('<div class="error_msg warn error below-h2"><p>'+e+'</p></div>');
        },
        onProcessComplete: {
            callbacks: [],
            add: function(callback){
                this.callbacks.push(callback);
            },
            run: function () {
                for(var i = 0; i < this.callbacks.length; i++){
                    this.callbacks[i].call(this);
                }
            }
        },
    };

    $(window).load(function(){
        window.iwp.init();
    });

})(jQuery, window, iwp_settings);