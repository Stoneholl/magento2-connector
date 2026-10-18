/**
 * Copyright (c) Stoneholl.
 */
define([
    'jquery',
    'mage/translate',
    'Magento_Ui/js/modal/confirm'
], function ($, $t, confirmation) {
    'use strict';

    return function (config, element) {
        var form = $(element),
            submitting = false;

        form.on('submit', function (event) {
            var content;

            if (submitting) {
                return;
            }

            event.preventDefault();
            content = $('<div></div>').text(form.attr('data-confirm-content')).html();

            confirmation({
                title: form.attr('data-confirm-title'),
                content: content,
                buttons: [{
                    text: $t('Cancel'),
                    class: 'action-secondary action-dismiss',
                    click: function (clickEvent) {
                        this.closeModal(clickEvent);
                    }
                }, {
                    text: form.attr('data-confirm-button'),
                    class: 'action-primary action-accept',
                    click: function (clickEvent) {
                        this.closeModal(clickEvent, true);
                    }
                }],
                actions: {
                    confirm: function () {
                        submitting = true;
                        element.submit();
                    }
                }
            });
        });
    };
});
