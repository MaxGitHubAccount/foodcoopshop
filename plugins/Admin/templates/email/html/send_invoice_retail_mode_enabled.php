<?php
/**
 * FoodCoopShop - The open source software for your foodcoop
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         FoodCoopShop 3.2.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @author        Mario Rothauer <office@foodcoopshop.com>
 * @copyright     Copyright (c) Mario Rothauer, https://www.rothauer-it.com
 * @link          https://www.foodcoopshop.com
 */
?>
<?php echo $this->element('email/tableHead'); ?>
<tbody>

    <tr>
        <td style="font-weight: bold; font-size: 18px; padding-bottom: 20px;">
                <?php echo __d('admin', 'Hello'); ?> <?php echo $customer->firstname; ?>,
            </td>
    </tr>

</tbody>
<?php echo $this->element('email/tableFoot'); ?>
