define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate',
    'mage/validation'
], function ($, modal, $t) {
    'use strict';

    return function (config) {
        $(document).ready(function () {
            // Customer search functionality
            var searchTimeout;
            var $searchInput = $('#customer_search');
            var $searchResults = $('#customer_search_results');
            var $customerIdInput = $('#customer_id');

            $searchInput.on('input', function () {
                var query = $(this).val();

                clearTimeout(searchTimeout);

                if (query.length < 2) {
                    $searchResults.hide();
                    return;
                }

                searchTimeout = setTimeout(function () {
                    $.ajax({
                        url: config.customerSearchUrl,
                        type: 'GET',
                        data: { q: query, limit: 10 },
                        dataType: 'json',
                        success: function (data) {
                            if (data && data.length > 0) {
                                var html = '<ul class="customer-search-list">';
                                data.forEach(function (customer) {
                                    html += '<li class="customer-search-item" data-id="' + customer.id + '" data-text="' + customer.text + '">' + customer.text + '</li>';
                                });
                                html += '</ul>';
                                $searchResults.html(html).show();
                            } else {
                                $searchResults.html('<div class="no-results">' + $t('No customers found') + '</div>').show();
                            }
                        },
                        error: function () {
                            $searchResults.html('<div class="error">' + $t('Error searching customers') + '</div>').show();
                        }
                    });
                }, 300);
            });

            // Handle customer selection
            $(document).on('click', '.customer-search-item', function () {
                var id = $(this).data('id');
                var text = $(this).data('text');

                $customerIdInput.val(id);
                $searchInput.val(text);
                $searchResults.hide();
            });

            // Hide results when clicking outside
            $(document).on('click', function (e) {
                if (!$(e.target).closest('.admin__field-control').length) {
                    $searchResults.hide();
                }
            });

            // Send notification functionality
            $('#send-notification').on('click', function () {
                var form = $('#single-notification-form');

                // initialize Magento validation on the form
                form.validation();

                if (form.validation('isValid')) {
                    var formData = form.serialize();

                    $.ajax({
                        url: form.attr('action'),
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        showLoader: true,
                        success: function (response) {
                            if (response.success) {
                                alert($t('Notification sent successfully!'));
                                form[0].reset();
                            } else {
                                alert($t('Error: ') + response.message);
                            }
                        },
                        error: function () {
                            alert($t('An error occurred while sending the notification.'));
                        }
                    });
                }
            });
        });
    };
});




