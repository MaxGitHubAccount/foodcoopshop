/**
 * FoodCoopShop - The open source software for your foodcoop
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         FoodCoopShop 3.3.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @author        Mario Rothauer <office@foodcoopshop.com>
 * @copyright     Copyright (c) Mario Rothauer, https://www.rothauer-it.com
 * @link          https://www.foodcoopshop.com
 */
foodcoopshop.ModalProductPurchasePriceEdit = {

    init : function() {

        var modalSelector = '#modal-product-purchase-price-edit';

        $('a.product-purchase-price-edit-button').on('click', function () {

            foodcoopshop.Modal.appendModalToDom(
                modalSelector,
                foodcoopshop.LocalizedJs.dialogProduct.EnterPurchasePrice,
                foodcoopshop.ModalProductPurchasePriceEdit.getHtml()
            );

            foodcoopshop.Modal.bindSuccessButton(modalSelector, function() {
                foodcoopshop.ModalProductPurchasePriceEdit.getSuccessHandler(modalSelector);
            });

            $(modalSelector).on('hidden.bs.modal', function (e) {
                foodcoopshop.ModalProductPurchasePriceEdit.getCloseHandler(modalSelector);
            });
            foodcoopshop.ModalProductPurchasePriceEdit.getOpenHandler($(this), modalSelector);
        });

    },

    getHtml : function() {
        var html = '<label for="dialogPurchasePricePrice"><b></b></label><br />';
        html += '<input type="number" step="0.01" name="dialogPurchasePricePrice" id="dialogPurchasePricePrice" value="" />';
        html += '<b class="currency-symbol">' + foodcoopshop.LocalizedJs.helper.CurrencySymbol + '</b> (' + foodcoopshop.LocalizedJs.dialogProduct.gross + ')';
        html += '<input type="hidden" name="dialogPurchasePriceProductId" id="dialogPurchasePriceProductId" value="" />';
        return html;
    },

    getCloseHandler : function(modalSelector) {
        $(modalSelector).remove();
    },

    getSuccessHandler : function(modalSelector) {

        foodcoopshop.Helper.ajaxCall(
            '/admin/products/editPurchasePrice/',
            {
                productId: $('#dialogPurchasePriceProductId').val(),
                purchasePrice: $('#dialogPurchasePricePrice').val()
            },
            {
                onOk: function (data) {
                    document.location.reload();
                },
                onError: function (data) {
                    foodcoopshop.Modal.appendFlashMessage(modalSelector, data.msg);
                    foodcoopshop.Modal.resetButtons(modalSelector);
                }
            }
        );

    },

    getOpenHandler : function(button, modalSelector) {

        $(modalSelector).modal();

        var row = button.closest('tr');
        var purchasePriceContainer = row.find('span.purchase-price-for-dialog');
        var purchasePrice;
        var purchasePriceUnit = '';
        if (purchasePriceContainer.length > 0) {
            purchasePrice = purchasePriceContainer.html();
        }


        var unitPurchasePriceContainer = row.find('span.unit-purchase-price-for-dialog');
        if (unitPurchasePriceContainer.length > 0) {
            purchasePrice = unitPurchasePriceContainer.html();
            purchasePriceUnit = row.find('span.unit-price-for-dialog').html().split('&nbsp;')[1];
            $(modalSelector).find('.currency-symbol').html(purchasePriceUnit);
        }

        purchasePrice = foodcoopshop.Helper.getCurrencyAsFloat(purchasePrice).toFixed(2);

        $(modalSelector + ' #dialogPurchasePricePrice').val(purchasePrice);
        $(modalSelector + ' #dialogPurchasePriceProductId').val(row.find('td.cell-id').html());
        var label = foodcoopshop.Admin.getProductNameForDialog(row);
        $(modalSelector + ' label[for="dialogPurchasePricePrice"] b').html(label);

        $('#dialogPurchasePricePrice').focus();

    }

};