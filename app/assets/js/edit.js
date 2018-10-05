(function($, window){

    // global iwp
    window.iwp = window.iwp || {

        isProcessed: false,

        init: function(){
            console.log('iwp.init');
            console.log('iwp.isProcessing', this.isProcessed);

            if(this.isProcessed === false){
                $('#processing').show();
            }

            var self = this;

            $.ajax({
                url: ajax_object.ajax_url,
                data: {
                    action: 'iwp_process',
                    id: ajax_object.id,
                },
                dataType: 'json',
                type: "POST",
                beforeSend: function () {

                },
                complete: function () {

                },
                success: function (response) {
                    self.isProcessed = true;
                    $('#processing').hide();
                    self.onProcessComplete.run(self);
                },
                error: function(e){
                    iwp.onError(e);
                }
            });
        },
        ready: function(){
            console.log('iwp.ready');
            console.log('iwp.isProcessing', this.isProcessed);
        },
        onError: function(e){
            console.log('iwp.error', e);

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

    $(document).ready(function(){
        window.iwp.init();
    });

})(jQuery, window);