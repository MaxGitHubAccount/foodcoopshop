<?php
/**
 * FoodCoopShop - The open source software for your foodcoop
 *
 * Licensed under the GNU Affero General Public License version 3
 * For full copyright and license information, please see LICENSE
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         FoodCoopShop 2.2.0
 * @license       https://opensource.org/licenses/AGPL-3.0
 * @author        Mario Rothauer <office@foodcoopshop.com>
 * @copyright     Copyright (c) Mario Rothauer, https://www.rothauer-it.com
 * @link          https://www.foodcoopshop.com
 */

echo '<td style="text-align:center;width:54px;">';
    if (! empty($product->product_attributes) || isset($product->product_attributes)) {
        echo $this->Html->link(
            '<i class="fas fa-plus-circle ok"></i>',
            'javascript:void(0);',
            [
                'class' => 'btn btn-outline-light add-product-attribute-button',
                'title' => __d('admin', 'Add_new_attribute_for_product_{0}', [$product->unchanged_name]),
                'escape' => false
            ]
        );
    }
echo '</td>';

?>