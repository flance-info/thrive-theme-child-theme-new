jQuery(document).ready(function($) {
    (function() {

        var originalFetch = window.fetch;

        window.fetch = function(input, init) {
            var url = typeof input === 'string' ? input : input.url;

            if (url === window.ajax_window.ajax_url && init && init.body instanceof FormData) {
                var formData = init.body;
                var action = formData.get('action');

                if (action === 'create_cc_order') {

                    return originalFetch.apply(this, arguments).then(function(response) {

                        var responseClone = response.clone();

                        return responseClone.json().then(function(data) {
                            if (data.success && data.data.status === 'success') {

                                 var formDetails = JSON.parse(data.data.processedOrderDetails);
                                var yourMessage = formDetails;

                               setTimeout(function() {
                                    $('.display-order').html(yourMessage);
                                }, 500);

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
