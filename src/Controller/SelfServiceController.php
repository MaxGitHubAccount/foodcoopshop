<?php

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;

/**
 * FoodCoopShop - The open source software for your foodcoop
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         FoodCoopShop 2.5.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 * @author        Mario Rothauer <office@foodcoopshop.com>
 * @copyright     Copyright (c) Mario Rothauer, https://www.rothauer-it.com
 * @link          https://www.foodcoopshop.com
 */
class SelfServiceController extends FrontendController
{

    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        if (!(Configure::read('appDb.FCS_SELF_SERVICE_MODE_FOR_STOCK_PRODUCTS_ENABLED') && $this->AppAuth->user())) {
            $this->AppAuth->deny($this->getRequest()->getParam('action'));
        }
    }
    
    public function index()
    {
        
        $keyword = '';
        if (!empty($this->getRequest()->getQuery('keyword'))) {
            $keyword = trim($this->getRequest()->getQuery('keyword'));
        }
        $this->set('keyword', $keyword);
        
        $this->Category = TableRegistry::getTableLocator()->get('Categories');
        $products = $this->Category->getProductsByCategoryId(Configure::read('app.categoryAllProducts'), false, $keyword);
        $products = $this->prepareProductsForFrontend($products);
        $this->set('products', $products);
        
        $this->viewBuilder()->setLayout('self_service');
        $this->set('title_for_layout', __('Self_service_for_stock_products'));
    }
    
}
