<?php
/**
 * FoodCoopShop - The open source software for your foodcoop
 *
 * Licensed under the GNU Affero General Public License version 3
 * For full copyright and license information, please see LICENSE
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         FoodCoopShop 1.0.0
 * @license       https://opensource.org/licenses/AGPL-3.0
 * @author        Mario Rothauer <office@foodcoopshop.com>
 * @copyright     Copyright (c) Mario Rothauer, https://www.rothauer-it.com
 * @link          https://www.foodcoopshop.com
 */

use Cake\Core\Configure;

?>
<div class="amount-wrapper">

    <?php if (!$hideAmountSelector) { ?>
        <span class="loi"><?php echo __('Amount'); ?></span>
        <input name="amount" value="1" type="text" />
        <a class="as as-plus" href="javascript:void(0);">
            <i class="fas fa-plus-circle"></i>
        </a>
        <a class="as as-minus" href="javascript:void(0);">
            <i class="fas fa-minus-circle"></i>
        </a>
    <?php } ?>
    <?php
        $availableQuantity = $stockAvailable->quantity - $stockAvailable->quantity_limit;
        if ((($product->is_stock_product && $product->manufacturer->stock_management_enabled) || !$stockAvailable->always_available) && $availableQuantity <= Configure::read('appDb.FCS_PRODUCT_AVAILABILITY_LOW')) { ?>
            <span <?php echo !$hideAmountSelector ? 'class="right-of-input availibility"' : ''; ?>>(<?php echo $availableQuantity . ' ' . __('available'); ?>)</span>
    <?php } ?>

</div>