<?php

namespace App\Controller;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\I18n\FrozenDate;

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
class FrontendController extends AppController
{

    public function isAuthorized($user)
    {
        return true;
    }

    protected function prepareProductsForFrontend($products)
    {
        $this->Product = $this->getTableLocator()->get('Products');
        $this->Manufacturer = $this->getTableLocator()->get('Manufacturers');
        $this->ProductAttribute = $this->getTableLocator()->get('ProductAttributes');
        $this->Customer = $this->getTableLocator()->get('Customers');

        $i = 0;
        foreach ($products as $product) {

            $productCacheKey = join('_', [
                'FrontendController_prepareProductsForFrontend',
                'productId' => $products[$i]['id_product'],
                $this->AppAuth->isOrderForDifferentCustomerMode() || $this->AppAuth->isSelfServiceModeByUrl(),
                $this->AppAuth->user('shopping_price'),
                $this->AppAuth->isTimebasedCurrencyEnabledForCustomer(),
                'date-' . date('Y-m-d'),
            ]);
            $cachedProduct = Cache::read($productCacheKey);

            if ($cachedProduct === null) {

                $taxRate = is_null($products[$i]['taxRate']) ? 0 : $products[$i]['taxRate'];

                // START: override shopping with purchase prices / zero prices
                $modifiedProductPricesByShoppingPrice = $this->Customer->getModifiedProductPricesByShoppingPrice($this->AppAuth, $products[$i]['id_product'], $products[$i]['price'], $products[$i]['price_incl_per_unit'], $products[$i]['deposit'], $taxRate);
                $products[$i]['price'] = $modifiedProductPricesByShoppingPrice['price'];
                $products[$i]['price_incl_per_unit'] = $modifiedProductPricesByShoppingPrice['price_incl_per_unit'];
                $products[$i]['deposit'] = $modifiedProductPricesByShoppingPrice['deposit'];
                // END: override shopping with purchase prices / zero prices

                $grossPrice = $this->Product->getGrossPrice($products[$i]['price'], $taxRate);

                $products[$i]['gross_price'] = $grossPrice;
                $products[$i]['tax'] = $grossPrice - $products[$i]['price'];
                $products[$i]['is_new'] = $this->Product->isNew($products[$i]['created']);

                if (!Configure::read('app.isDepositEnabled')) {
                    $products[$i]['deposit'] = 0;
                }

                if (Configure::read('appDb.FCS_CUSTOMER_CAN_SELECT_PICKUP_DAY')) {
                    $products[$i]['next_delivery_day'] = new FrozenDate('1970-01-01');
                } elseif ($this->AppAuth->isOrderForDifferentCustomerMode() || $this->AppAuth->isSelfServiceModeByUrl()) {
                    $products[$i]['next_delivery_day'] = Configure::read('app.timeHelper')->getCurrentDateForDatabase();
                } else {
                    $products[$i]['next_delivery_day'] = $this->Product->calculatePickupDayRespectingDeliveryRhythm(
                        $this->Product->newEntity([
                            'delivery_rhythm_order_possible_until' => $products[$i]['delivery_rhythm_order_possible_until'] == '' ? null : new FrozenDate($products[$i]['delivery_rhythm_order_possible_until']),
                            'delivery_rhythm_first_delivery_day' => $products[$i]['delivery_rhythm_first_delivery_day'] == '' ? null : new FrozenDate($products[$i]['delivery_rhythm_first_delivery_day']),
                            'delivery_rhythm_type' => $products[$i]['delivery_rhythm_type'],
                            'delivery_rhythm_count' => $products[$i]['delivery_rhythm_count'],
                            'delivery_rhythm_send_order_list_weekday' => $products[$i]['delivery_rhythm_send_order_list_weekday'],
                            'delivery_rhythm_send_order_list_day' => $products[$i]['delivery_rhythm_send_order_list_day'],
                            // convert database strings to boolean to && them and then re-convert to string
                            'is_stock_product' => (string) (
                                (boolean) $products[$i]['is_stock_product'] && (boolean) $products[$i]['stock_management_enabled']
                            )
                        ]
                    ));
                }

                $products[$i]['attributes'] = [];

                if ($this->AppAuth->isTimebasedCurrencyEnabledForCustomer()) {
                    if ($this->Manufacturer->getOptionTimebasedCurrencyEnabled($products[$i]['timebased_currency_enabled'])) {
                        $products[$i]['timebased_currency_money_incl'] = $this->Manufacturer->getTimebasedCurrencyMoney($products[$i]['gross_price'], $products[$i]['timebased_currency_max_percentage']);
                        $products[$i]['timebased_currency_money_excl'] = $this->Manufacturer->getTimebasedCurrencyMoney($products[$i]['price'], $products[$i]['timebased_currency_max_percentage']);
                        $products[$i]['timebased_currency_seconds'] = $this->Manufacturer->getCartTimebasedCurrencySeconds($products[$i]['gross_price'], $products[$i]['timebased_currency_max_percentage']);
                        $products[$i]['timebased_currency_manufacturer_limit_reached'] = $this->Manufacturer->hasManufacturerReachedTimebasedCurrencyLimit($products[$i]['id_manufacturer']);
                    }

                }

                $conditions = [
                    'ProductAttributes.id_product' => $product['id_product'],
                ];
                $contain = [
                    'StockAvailables',
                    'ProductAttributeCombinations.Attributes',
                    'DepositProductAttributes',
                    'UnitProductAttributes'
                ];

                if (Configure::read('appDb.FCS_PURCHASE_PRICE_ENABLED')) {
                    $contain[] = 'PurchasePriceProductAttributes';
                }

                $attributes = $this->ProductAttribute->find('all', [
                    'conditions' => $conditions,
                    'contain' => $contain,
                ]);

                $preparedAttributes = [];
                foreach ($attributes as $attribute) {

                    if (Configure::read('appDb.FCS_PURCHASE_PRICE_ENABLED')) {
                        if (!$this->Product->ProductAttributes->PurchasePriceProductAttributes->isPurchasePriceSet($attribute)) {
                            continue;
                        }
                    }

                    $preparedAttributes['ProductAttributes'] = [
                        'id_product_attribute' => $attribute->id_product_attribute
                    ];

                    $attributePricePerUnit = !empty($attribute->unit_product_attribute) ? $attribute->unit_product_attribute->price_incl_per_unit : 0;
                    $attributeDeposit = !empty($attribute->deposit_product_attribute) ? $attribute->deposit_product_attribute->deposit : 0;

                    // START: override shopping with purchase prices / zero prices
                    $modifiedAttributePricesByShoppingPrice = $this->Customer->getModifiedAttributePricesByShoppingPrice($this->AppAuth, $attribute->id_product, $attribute->id_product_attribute, $attribute->price, $attributePricePerUnit, $attributeDeposit, $taxRate);
                    $attribute->price = $modifiedAttributePricesByShoppingPrice['price'];
                    if (!empty($attribute->unit_product_attribute)) {
                        $attribute->unit_product_attribute->price_incl_per_unit = $modifiedAttributePricesByShoppingPrice['price_incl_per_unit'];
                    }
                    if (!empty($attribute->deposit_product_attribute)) {
                        $attribute->deposit_product_attribute->deposit = $modifiedAttributePricesByShoppingPrice['deposit'];
                    }
                    // END: override shopping with purchase prices / zero prices

                    $grossPrice = $this->Product->getGrossPrice($attribute->price, $taxRate);

                    $preparedAttributes['ProductAttributes'] = [
                        'gross_price' => $grossPrice,
                        'tax' => $grossPrice - $attribute->price,
                        'default_on' => $attribute->default_on,
                        'id_product_attribute' => $attribute->id_product_attribute
                    ];
                    $preparedAttributes['StockAvailables'] = [
                        'quantity' => $attribute->stock_available->quantity,
                        'quantity_limit' => $attribute->stock_available->quantity_limit,
                        'always_available' => $attribute->stock_available->always_available,
                    ];
                    $preparedAttributes['DepositProductAttributes'] = [
                        'deposit' => Configure::read('app.isDepositEnabled') && !empty($attribute->deposit_product_attribute) ? $attribute->deposit_product_attribute->deposit : 0,
                    ];
                    $preparedAttributes['ProductAttributeCombinations'] = [
                        'Attributes' => [
                            'name' => $attribute->product_attribute_combination->attribute->name,
                            'can_be_used_as_unit' => $attribute->product_attribute_combination->attribute->can_be_used_as_unit
                        ]
                    ];
                    $preparedAttributes['Units'] = [
                        'price_per_unit_enabled' => !empty($attribute->unit_product_attribute) ? $attribute->unit_product_attribute->price_per_unit_enabled : 0,
                        'price_incl_per_unit' => !empty($attribute->unit_product_attribute) ? $attribute->unit_product_attribute->price_incl_per_unit : 0,
                        'unit_name' => !empty($attribute->unit_product_attribute) ? $attribute->unit_product_attribute->name : '',
                        'unit_amount' => !empty($attribute->unit_product_attribute) ? $attribute->unit_product_attribute->amount : 0,
                        'quantity_in_units' => !empty($attribute->unit_product_attribute) ? $attribute->unit_product_attribute->quantity_in_units : 0
                    ];

                    if ($this->AppAuth->isTimebasedCurrencyEnabledForCustomer()) {
                        if ($this->Manufacturer->getOptionTimebasedCurrencyEnabled($products[$i]['timebased_currency_enabled'])) {
                            $preparedAttributes['timebased_currency_money_incl'] = $this->Manufacturer->getTimebasedCurrencyMoney($grossPrice, $products[$i]['timebased_currency_max_percentage']);
                            $preparedAttributes['timebased_currency_money_excl'] = $this->Manufacturer->getTimebasedCurrencyMoney($attribute->price, $products[$i]['timebased_currency_max_percentage']);
                            $preparedAttributes['timebased_currency_seconds'] = $this->Manufacturer->getCartTimebasedCurrencySeconds($grossPrice, $products[$i]['timebased_currency_max_percentage']);
                            $preparedAttributes['timebased_currency_manufacturer_limit_reached'] = $this->Manufacturer->hasManufacturerReachedTimebasedCurrencyLimit($products[$i]['id_manufacturer']);
                        }
                    }

                    $products[$i]['attributes'][] = $preparedAttributes;
                }
            } else {
                $products[$i] = $cachedProduct;
            }

            Cache::write($productCacheKey, $products[$i]);
            $i++;
        }

        // never cache user dependent future order details
        if ($this->AppAuth->user()) {
            $futureOrderDetails = $this->AppAuth->getFutureOrderDetails();
        }

        $i = 0;
        foreach($products as $product) {
            $products[$i]['future_order_details'] = [];
            if (!empty($futureOrderDetails)) {
                foreach($futureOrderDetails as $futureOrderDetail) {
                    if ($futureOrderDetail['product_id'] == $products[$i]['id_product']) {
                        $products[$i]['future_order_details'][] = $futureOrderDetail;
                        continue;
                    }
                }
            }
            $i++;
        }

        return $products;
    }

    protected function resetOriginalLoggedCustomer()
    {
        if ($this->getRequest()->getSession()->read('Auth.originalLoggedCustomer')) {
            $this->AppAuth->setUser($this->getRequest()->getSession()->read('Auth.originalLoggedCustomer'));
        }
    }

    protected function destroyOrderCustomer()
    {
        $this->getRequest()->getSession()->delete('Auth.orderCustomer');
        $this->getRequest()->getSession()->delete('Auth.originalLoggedCustomer');
    }

    // is not called on ajax actions!
    public function beforeRender(EventInterface $event)
    {

        parent::beforeRender($event);

        // when an instant order was placed, the pdfs that are rendered for the order confirmation email
        // called this method and therefore called resetOriginalLoggedCustomer() => email was sent t
        // the user who placed the order for a member and not to the member
        if ($this->getResponse()->getType() != 'text/html') {
            return;
        }

        $this->resetOriginalLoggedCustomer();

        $categoriesForMenu = [];
        if (Configure::read('appDb.FCS_SHOW_PRODUCTS_FOR_GUESTS') || $this->AppAuth->user()) {
            $this->Category = $this->getTableLocator()->get('Categories');
            $allProductsCount = $this->Category->getProductsByCategoryId($this->AppAuth, Configure::read('app.categoryAllProducts'), false, '', 0, true);
            $newProductsCount = $this->Category->getProductsByCategoryId($this->AppAuth, Configure::read('app.categoryAllProducts'), true, '', 0, true);
            $categoriesForMenu = $this->Category->getForMenu($this->AppAuth);
            array_unshift($categoriesForMenu, [
                'slug' => Configure::read('app.slugHelper')->getNewProducts(),
                'name' => __('New_products') . ' <span class="additional-info"> (' . $newProductsCount . ')</span>',
                'options' => [
                    'fa-icon' => 'fa-star' . ($newProductsCount > 0 ? ' gold' : '')
                ]
            ]);
            array_unshift($categoriesForMenu, [
                'slug' => Configure::read('app.slugHelper')->getAllProducts(),
                'name' => __('All_products') . ' <span class="additional-info"> (' . $allProductsCount . ')</span>',
                'options' => [
                    'fa-icon' => 'fa-tags'
                ]
            ]);
        }
        $this->set('categoriesForMenu', $categoriesForMenu);

        $manufacturersForMenu = [];
        if (Configure::read('app.showManufacturerListAndDetailPage')) {
            $this->Manufacturer = $this->getTableLocator()->get('Manufacturers');
            $manufacturersForMenu = $this->Manufacturer->getForMenu($this->AppAuth);
            $this->set('manufacturersForMenu', $manufacturersForMenu);
        }

        $this->Page = $this->getTableLocator()->get('Pages');
        $conditions = [];
        $conditions['Pages.active'] = APP_ON;
        $conditions[] = 'Pages.position > 0';
        if (! $this->AppAuth->user()) {
            $conditions['Pages.is_private'] = APP_OFF;
        }

        $pages = $this->Page->getThreaded($conditions);
        $pagesForHeader = [];
        $pagesForFooter = [];
        foreach ($pages as $page) {
            if ($page->menu_type == 'header') {
                $pagesForHeader[] = $page;
            }
            if ($page->menu_type == 'footer') {
                $pagesForFooter[] = $page;
            }
        }
        $this->set('pagesForHeader', $pagesForHeader);
        $this->set('pagesForFooter', $pagesForFooter);
    }

    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);

        if (($this->name == 'Categories' && $this->getRequest()->getParam('action') == 'detail') || $this->name == 'Carts') {
            // do not allow but call isAuthorized
        } else {
            $this->AppAuth->allow();
        }

        /*
         * changed the acutally logged in customer to the desired orderCustomer
         * but only in controller beforeFilter(), beforeRender() sets the customer back to the original one
         * this means, in views $appAuth ALWAYS returns the original customer, in controllers ALWAYS the desired orderCustomer
         */
        if ($this->AppAuth->isOrderForDifferentCustomerMode()) {
            $this->getRequest()->getSession()->write('Auth.originalLoggedCustomer', $this->AppAuth->user());
            $this->AppAuth->setUser($this->getRequest()->getSession()->read('Auth.orderCustomer'));
        }
        if (!empty($this->AppAuth->user())) {

            if (Configure::read('app.htmlHelper')->paymentIsCashless()) {
                $creditBalance = $this->AppAuth->getCreditBalance();
                $this->set('creditBalance', $creditBalance);
            }

            $this->set('shoppingPrice', $this->AppAuth->user('shopping_price'));

            $cartsTable = $this->getTableLocator()->get('Carts');
            $this->set('paymentType', $this->AppAuth->isSelfServiceCustomer() ? $cartsTable::CART_SELF_SERVICE_PAYMENT_TYPE_CASH : $cartsTable::CART_SELF_SERVICE_PAYMENT_TYPE_CREDIT);

            $this->OrderDetail = $this->getTableLocator()->get('OrderDetails');
            $futureOrderDetails = $this->OrderDetail->getGroupedFutureOrdersByCustomerId($this->AppAuth->getUserId());
            $this->set('futureOrderDetails', $futureOrderDetails);
        }
        $this->AppAuth->setCart($this->AppAuth->getCart());
    }
}
