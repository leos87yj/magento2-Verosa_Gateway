/*browser:true*/
/*global define*/
define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Payment/js/model/credit-card-validation/validator'
    ],
    function (Component, $) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Verosa_Pay/payment/pay-form'
            },

            getCode: function() {
                return 'verosa_pay';
            },

            isActive: function() {
                return true;
            },

            isShowLegend: function () {
                return true;
            },


            validate: function() {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            }
        });
    }
);
