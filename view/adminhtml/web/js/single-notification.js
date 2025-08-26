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

            // Custom data functionality
            $('#custom_data_type').on('change', function () {
                var type = $(this).val();
                $('#custom_data_raw_container, #custom_data_keyvalue_container').hide();

                if (type === 'raw') {
                    $('#custom_data_raw_container').show();
                } else if (type === 'keyvalue') {
                    $('#custom_data_keyvalue_container').show();
                }
            });

            // Add key-value pair
            $('#add_keyvalue_pair').on('click', function () {
                var newPair = $('<div class="keyvalue-pair">' +
                    '<input type="text" name="custom_data_keys[]" class="admin__control-text" placeholder="' + $t('Key') + '" style="width: 45%; margin-right: 10px;">' +
                    '<input type="text" name="custom_data_values[]" class="admin__control-text" placeholder="' + $t('Value') + '" style="width: 45%;">' +
                    '<button type="button" class="action-remove" style="margin-left: 10px;">' + $t('Remove') + '</button>' +
                    '</div>');
                $('#keyvalue_pairs').append(newPair);
            });

            // Remove key-value pair
            $(document).on('click', '.action-remove', function () {
                $(this).closest('.keyvalue-pair').remove();
            });

            // Send notification functionality
            $('#send-notification').on('click', function () {
                var form = $('#single-notification-form');

                // initialize Magento validation on the form
                form.validation();

                                if (form.validation('isValid')) {
                    var formData = new FormData(form[0]);

                    // Ensure form key is included
                    var formKey = $('input[name="form_key"]').val();
                    if (formKey) {
                        formData.set('form_key', formKey);
                    }

                    // Process custom data
                    var customDataType = $('#custom_data_type').val();
                    var customData = null;

                    if (customDataType === 'raw') {
                        var rawData = $('#custom_data_raw').val().trim();
                        if (rawData) {
                            try {
                                customData = JSON.parse(rawData);
                            } catch (e) {
                                alert($t('Invalid JSON format in custom data.'));
                                return;
                            }
                        }
                    } else if (customDataType === 'keyvalue') {
                        var keys = $('input[name="custom_data_keys[]"]').map(function() { return $(this).val(); }).get();
                        var values = $('input[name="custom_data_values[]"]').map(function() { return $(this).val(); }).get();
                        customData = {};

                        for (var i = 0; i < keys.length; i++) {
                            if (keys[i] && values[i]) {
                                customData[keys[i]] = values[i];
                            }
                        }
                    }

                    // Add custom data to form data
                    if (customData) {
                        formData.append('custom_data', JSON.stringify(customData));
                    }

                    $.ajax({
                        url: form.attr('action'),
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        processData: false,
                        contentType: false,
                        showLoader: true,
                        success: function (response) {
                            if (response.success) {
                                alert($t('Notification sent successfully!'));
                                form[0].reset();
                                $('#custom_data_raw_container, #custom_data_keyvalue_container').hide();
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




