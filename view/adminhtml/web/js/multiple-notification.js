define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate',
    'mage/validation'
], function ($, modal, $t) {
    'use strict';

    return function (config) {
        $(document).ready(function () {
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

            $('#send-notification').on('click', function () {
                var form = $('#multiple-notification-form');

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
                                if (response.total_sent !== undefined) {
                                    alert($t('Notification sent successfully! Total sent: ') + response.total_sent);
                                } else {
                                    alert(response.message);
                                }
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




