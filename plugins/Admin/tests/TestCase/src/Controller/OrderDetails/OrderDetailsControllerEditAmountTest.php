<?php
/**
 * FoodCoopShop - The open source software for your foodcoop
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         FoodCoopShop 2.5.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @author        Mario Rothauer <office@foodcoopshop.com>
 * @copyright     Copyright (c) Mario Rothauer, https://www.rothauer-it.com
 * @link          https://www.foodcoopshop.com
 */

use App\Test\TestCase\OrderDetailsControllerTestCase;
use Cake\Core\Configure;

class OrderDetailsControllerEditAmountTest extends OrderDetailsControllerTestCase
{

    public $newAmount = 1;
    public $editAmountReason = 'One product was not delivered.';


    public function testEditOrderDetailAmountNotValid()
    {
        $this->loginAsSuperadmin();
        $this->mockCart = $this->generateAndGetCart(1, 2);
        $this->editOrderDetailAmount($this->mockCart->cart_products[1]->order_detail->id_order_detail, -1, $this->editAmountReason);
        $this->assertEquals($this->getJsonDecodedContent()->msg, 'Die Anzahl ist nicht gültig.');
    }

    public function testEditOrderDetailAmountAsManufacturer()
    {
        $this->loginAsSuperadmin();
        $this->mockCart = $this->generateAndGetCart(5, 2);
        $this->logout();
        $this->loginAsVegetableManufacturer();
        $this->editOrderDetailAmount($this->mockCart->cart_products[0]->order_detail->id_order_detail, $this->newAmount, $this->editAmountReason);

        $changedOrder = $this->getChangedMockCartFromDatabase();
        $this->assertEquals($this->newAmount, $changedOrder->cart_products[0]->order_detail->product_amount);
        $this->assertEquals(0.17, $changedOrder->cart_products[0]->order_detail->tax_unit_amount);
        $this->assertEquals(0.17, $changedOrder->cart_products[0]->order_detail->tax_total_amount);
        $this->assertEquals(10, $changedOrder->cart_products[0]->order_detail->tax_rate);

        $expectedToEmail = Configure::read('test.loginEmailSuperadmin');
        $this->assertOrderDetailProductAmountChangedEmails(1, $expectedToEmail);

        $this->assertChangedStockAvailable($this->productIdA, 96);
    }

    public function testEditOrderDetailAmountWithTimebasedCurrency()
    {

        $cart = $this->prepareTimebasedCurrencyCart();
        $orderDetailId = $cart->cart_products[1]->order_detail->id_order_detail;
        $this->editOrderDetailAmount($orderDetailId, $this->newAmount, $this->editAmountReason);

        $changedOrderDetails = $this->getOrderDetailsFromDatabase([$orderDetailId]);

        $this->assertEquals($this->newAmount, $changedOrderDetails[0]->product_amount);
        $this->assertEquals('1,40', Configure::read('app.numberHelper')->formatAsDecimal($changedOrderDetails[0]->total_price_tax_incl));

        $this->assertTimebasedCurrencyOrderDetail($changedOrderDetails[0], 0.55, 0.6, 216);
    }


    public function testEditOrderDetailAmountAsSuperadminWithEnabledNotificationPurchasePrice()
    {
        $this->changeConfiguration('FCS_PURCHASE_PRICE_ENABLED', 1);
        $this->loginAsSuperadmin();

        $this->addProductToCart(346, 3);
        $this->addProductToCart('348-12', 5);
        $this->finishCart();
        $cartId = Configure::read('app.htmlHelper')->getCartIdFromCartFinishedUrl($this->_response->getHeaderLine('Location'));
        $cart = $this->getCartById($cartId);

        $this->editOrderDetailAmount($cart->cart_products[1]->order_detail->id_order_detail, 1, $this->editAmountReason);
        $this->editOrderDetailAmount($cart->cart_products[0]->order_detail->id_order_detail, 2, $this->editAmountReason);

        $changedOrderDetails = $this->OrderDetail->find('all', [
            'conditions' => [
                'OrderDetails.id_order_detail IN' => [
                    $cart->cart_products[0]->order_detail->id_order_detail,
                    $cart->cart_products[1]->order_detail->id_order_detail,
                ],
            ],
            'contain' => [
                'OrderDetailUnits',
                'OrderDetailPurchasePrices',
            ]
        ])->toArray();

        $this->assertEquals(8.4, $changedOrderDetails[0]->order_detail_purchase_price->total_price_tax_incl);
        $this->assertEquals(7.43, $changedOrderDetails[0]->order_detail_purchase_price->total_price_tax_excl);
        $this->assertEquals(0.97, $changedOrderDetails[0]->order_detail_purchase_price->tax_unit_amount);
        $this->assertEquals(0.97, $changedOrderDetails[0]->order_detail_purchase_price->tax_total_amount);

        $this->assertEquals(2.88, $changedOrderDetails[1]->order_detail_purchase_price->total_price_tax_incl);
        $this->assertEquals(2.4, $changedOrderDetails[1]->order_detail_purchase_price->total_price_tax_excl);
        $this->assertEquals(0.24, $changedOrderDetails[1]->order_detail_purchase_price->tax_unit_amount);
        $this->assertEquals(0.48, $changedOrderDetails[1]->order_detail_purchase_price->tax_total_amount);

    }

    public function testEditOrderDetailAmountAsSuperadminWithEnabledNotification()
    {
        $this->loginAsSuperadmin();
        $this->mockCart = $this->generateAndGetCart(1, 2);

        $this->editOrderDetailAmount($this->mockCart->cart_products[0]->order_detail->id_order_detail, $this->newAmount, $this->editAmountReason);

        $changedOrder = $this->getChangedMockCartFromDatabase();
        $this->assertEquals($this->newAmount, $changedOrder->cart_products[0]->order_detail->product_amount);
        $this->assertEquals(0.17, $changedOrder->cart_products[0]->order_detail->tax_unit_amount);
        $this->assertEquals(0.17, $changedOrder->cart_products[0]->order_detail->tax_total_amount);
        $this->assertEquals(10, $changedOrder->cart_products[0]->order_detail->tax_rate);

        $expectedToEmail = Configure::read('test.loginEmailSuperadmin');
        $this->assertOrderDetailProductAmountChangedEmails(1, $expectedToEmail);

        $this->assertChangedStockAvailable($this->productIdA, 96);
    }

    public function testEditOrderDetailAmountAsSuperadminWithEnabledNotificationAfterOrderListsWereSent()
    {
        $this->loginAsSuperadmin();
        $this->mockCart = $this->generateAndGetCart(1, 2);
        $orderDetailId = $this->mockCart->cart_products[0]->order_detail->id_order_detail;
        $this->simulateSendOrderListsCronjob($orderDetailId);

        $this->editOrderDetailAmount($orderDetailId, $this->newAmount, $this->editAmountReason);

        $changedOrder = $this->getChangedMockCartFromDatabase();
        $this->assertEquals($this->newAmount, $changedOrder->cart_products[0]->order_detail->product_amount);
        $this->assertEquals(0.17, $changedOrder->cart_products[0]->order_detail->tax_unit_amount);
        $this->assertEquals(0.17, $changedOrder->cart_products[0]->order_detail->tax_total_amount);
        $this->assertEquals(10, $changedOrder->cart_products[0]->order_detail->tax_rate);

        $expectedToEmail = Configure::read('test.loginEmailSuperadmin');
        $expectedCcEmail = Configure::read('test.loginEmailVegetableManufacturer');
        $this->assertOrderDetailProductAmountChangedEmails(1, $expectedToEmail, $expectedCcEmail);

        $this->assertChangedStockAvailable($this->productIdA, 96);
    }

    public function testEditOrderDetailAmountAsSuperadminWithDisabledNotification()
    {
        $this->loginAsSuperadmin();
        $this->mockCart = $this->generateAndGetCart(1, 2);
        $manufacturerId = $this->Customer->getManufacturerIdByCustomerId(Configure::read('test.vegetableManufacturerId'));
        $this->changeManufacturer($manufacturerId, 'send_ordered_product_amount_changed_notification', 0);

        $this->editOrderDetailAmount($this->mockCart->cart_products[0]->order_detail->id_order_detail, $this->newAmount, $this->editAmountReason);

        $changedOrder = $this->getChangedMockCartFromDatabase();
        $this->assertEquals($this->newAmount, $changedOrder->cart_products[0]->order_detail->product_amount);
        $this->assertEquals(0.17, $changedOrder->cart_products[0]->order_detail->tax_unit_amount);
        $this->assertEquals(0.17, $changedOrder->cart_products[0]->order_detail->tax_total_amount);
        $this->assertEquals(10, $changedOrder->cart_products[0]->order_detail->tax_rate);

        $expectedToEmail = Configure::read('test.loginEmailSuperadmin');
        $this->assertOrderDetailProductAmountChangedEmails(1, $expectedToEmail);

        $this->assertChangedStockAvailable($this->productIdA, 96);
    }

    private function assertOrderDetailProductAmountChangedEmails($emailIndex, $expectedToEmail, $expectedCcEmail = null)
    {
        $this->assertMailSubjectContainsAt($emailIndex, 'Bestellte Anzahl angepasst: Artischocke : Stück');
        $this->assertMailContainsHtmlAt($emailIndex, 'Die Anzahl des Produktes <b>Artischocke : Stück</b> wurde angepasst');
        $this->assertMailContainsHtmlAt($emailIndex, $this->editAmountReason);
        $this->assertMailContainsHtmlAt($emailIndex, 'Neue Anzahl: <b>' . $this->newAmount . '</b>');
        $this->assertMailContainsHtmlAt($emailIndex, 'Demo Gemüse-Hersteller');
        $this->assertMailSentToAt($emailIndex, $expectedToEmail);
        if ($expectedCcEmail !== null) {
            $this->assertMailSentWithAt($emailIndex, $expectedCcEmail, 'cc');
        }
    }

    private function editOrderDetailAmount($orderDetailId, $productAmount, $editAmountReason)
    {
        $this->ajaxPost(
            '/admin/order-details/editProductAmount/',
            [
                'orderDetailId' => $orderDetailId,
                'productAmount' => $productAmount,
                'editAmountReason' => $editAmountReason
            ]
        );
    }
}