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

namespace App\Shell;

use App\Mailer\AppMailer;
use Cake\Core\Configure;
use Cake\Database\Expression\QueryExpression;

class PickupReminderShell extends AppShell
{

    public $cronjobRunDay;

    public function main()
    {

        parent::main();

        // $this->cronjobRunDay can is set in unit test
        if (!isset($this->args[0])) {
            $this->cronjobRunDay = Configure::read('app.timeHelper')->getCurrentDateTimeForDatabase();
        } else {
            $this->cronjobRunDay = $this->args[0];
        }

        $this->startTimeLogging();

        $conditions = [
            'Customers.pickup_day_reminder_enabled' => 1,
            'Customers.active' => 1,
        ];
        $conditions[] = $this->Customer->getConditionToExcludeHostingUser();
        $this->Customer->dropManufacturersInNextFind();

        $customers = $this->Customer->find('all', [
            'conditions' => $conditions,
            'contain' => [
                'AddressCustomers' // to make exclude happen using dropManufacturersInNextFind
            ]
        ]);
        $customers = $this->Customer->sortByVirtualField($customers, 'name');
        $this->OrderDetail = $this->getTableLocator()->get('OrderDetails');

        $nextPickupDay = Configure::read('app.timeHelper')->getDeliveryDay(strtotime($this->cronjobRunDay));
        $formattedPickupDay = Configure::read('app.timeHelper')->getDateFormattedWithWeekday($nextPickupDay);
        $diffOrderAndPickupInDays = 6;

        $i = 0;
        $outString = '';
        $exp = new QueryExpression();
        foreach ($customers as $customer) {

            $futureOrderDetails = $this->OrderDetail->find('all', [
                'conditions' => [
                    'OrderDetails.id_customer' => $customer->id_customer,
                    $exp->eq('DATE_FORMAT(OrderDetails.pickup_day, \'%Y-%m-%d\')', date('Y-m-d', $nextPickupDay)),
                    $exp->gt('DATEDIFF(OrderDetails.pickup_day, DATE_FORMAT(OrderDetails.created, \'%Y-%m-%d\'))', $diffOrderAndPickupInDays),
                ],
                'contain' => [
                    'Products.Manufacturers'
                ],
                'order' => [
                    'OrderDetails.product_name' => 'ASC'
                ]
            ])->toArray();

            if (empty($futureOrderDetails)) {
                continue;
            }

            $email = new AppMailer();
            $email->setTo($customer->email)
            ->viewBuilder()->setTemplate('Admin.pickup_reminder');
            $email->setSubject(__('Pickup_reminder_for') . ' ' . $formattedPickupDay)
            ->setViewVars([
                'customer' => $customer,
                'newsletterCustomer' => $customer,
                'diffOrderAndPickupInDays' => $diffOrderAndPickupInDays,
                'formattedPickupDay' => $formattedPickupDay,
                'futureOrderDetails' => $futureOrderDetails
            ])
            ->addToQueue();

            $outString .= $customer->name . '<br />';

            $i ++;
        }

        $outString .= __('Sent_emails') . ': ' . $i;

        $this->stopTimeLogging();

        $this->ActionLog->customSave('cronjob_pickup_reminder', 0, 0, '', $outString . '<br />' . $this->getRuntime());

        $this->out($outString);
        $this->out($this->getRuntime());

        return true;

    }
}
