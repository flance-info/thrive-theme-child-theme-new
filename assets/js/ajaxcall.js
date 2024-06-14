jQuery(document).ready(function($) {
    (function() {

        var originalFetch = window.fetch;

        window.fetch = function(input, init) {
            var url = typeof input === 'string' ? input : input.url;

            if (url === window.ajax_window.ajax_url && init && init.body instanceof FormData) {
                var formData = init.body;
                var action = formData.get('action');

                if (action === 'create_cc_order') {

                    var preload_data = formData.get('data');

                    return originalFetch.apply(this, arguments).then(function(response) {

                        var responseClone = response.clone();

                        return responseClone.json().then(function(data) {
                            if (data.success && data.data.status === 'success') {

                                if (preload_data) {
                                    try {
                                        var jsonData = JSON.parse(preload_data);
                                        var fields = jsonData.formDetails.fields;
                                        var yourMessage = fields.find(field => field.name === 'your-message').value;
                                          var formattedMessage = yourMessage.replace(/\n/g, '<br>');
                                        // Display your-message
                                        setTimeout(function () {
                                            $('.display-order').html(formattedMessage);
                                        }, 500);
                                    } catch (error) {
                                        console.error('Error parsing JSON data:', error);
                                    }
                                }
                            /*
                                var formDetailse = JSON.parse(data.data.processedOrderDetails);
                                var yourMessagee = formDetailse ;

                               setTimeout(function() {
                                    $('.display-order').html(yourMessagee);
                                }, 1500);
                                
                             */
                            }

                            return response;
                        });
                    });
                }
            }

            return originalFetch.apply(this, arguments);
        };
    })();
});
