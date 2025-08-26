define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate',
    'mage/validation'
], function ($, modal, $t) {
    'use strict';

    return function (config) {
        $(document).ready(function () {
            $('#send-notification').on('click', function () {
                var form = $('#multiple-notification-form');

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
                                if (response.total_sent !== undefined) {
                                    alert($t('Notification sent successfully! Total sent: ') + response.total_sent);
                                } else {
                                    alert(response.message);
                                }
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




