<?php
/**
 * FoodCoopShop - The open source software for your foodcoop
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         FoodCoopShop 1.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @author        Mario Rothauer <office@foodcoopshop.com>
 * @copyright     Copyright (c) Mario Rothauer, https://www.rothauer-it.com
 * @link          https://www.foodcoopshop.com
 */

use Cake\Core\Configure;

$this->element('addScript', [
    'script' => Configure::read('app.jsNamespace') . ".Admin.init();
    "
]);
?>
<h4><?php echo __d('admin', 'Please_chose_member_in_above_dropdown_for_placing_order.')?></h4>
<h4><?php echo __d('admin', 'The_pickup_day_of_the_instant_order_will_be_today_{0}.', [$this->Time->getDateFormattedWithWeekday($this->Time->getCurrentDay())]); ?></h4>
