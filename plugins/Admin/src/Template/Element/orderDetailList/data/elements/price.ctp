<?php
/**
 * FoodCoopShop - The open source software for your foodcoop
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         FoodCoopShop 2.2.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 * @author        Mario Rothauer <office@foodcoopshop.com>
 * @copyright     Copyright (c) Mario Rothauer, http://www.rothauer-it.com
 * @link          https://www.foodcoopshop.com
 */

use Cake\Core\Configure;

echo '<td class="right' . ($groupBy == '' && $orderDetail->total_price_tax_incl == 0 ? ' not-available' : '') . '">';
    echo '<div class="table-cell-wrapper price">';
    if ($groupBy == '') {
        if ($editRecordAllowed) {
            echo $this->Html->getJqueryUiIcon($this->Html->image($this->Html->getFamFamFamPath('page_edit.png')), [
                'class' => 'order-detail-product-price-edit-button',
                'title' => __d('admin', 'Click_to_change_price')
            ], 'javascript:void(0);');
        }
        echo '<span class="product-price-for-dialog">' . $this->Number->formatAsDecimal($orderDetail->total_price_tax_incl) . '</span>';
        if (!empty($orderDetail->timebased_currency_order_detail)) {
            echo '<b class="timebased-currency-time-element" title="'.__d('admin', 'Additional_in_{0}', [Configure::read('appDb.FCS_TIMEBASED_CURRENCY_NAME'). ': ' . $this->TimebasedCurrency->formatSecondsToTimebasedCurrency($orderDetail->timebased_currency_order_detail->seconds)]).'">&nbsp;*</b>';
        }
    } else {
        echo $this->Number->formatAsDecimal($orderDetail['sum_price']);
    }
    echo '</div>';
echo '</td>';


?>