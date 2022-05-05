<?php
/**
 * FoodCoopShop - The open source software for your foodcoop
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         FoodCoopShop 3.5.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @author        Mario Rothauer <office@foodcoopshop.com>
 * @copyright     Copyright (c) Mario Rothauer, https://www.rothauer-it.com
 * @link          https://www.foodcoopshop.com
 */
namespace App\Lib\PdfWriter;

use App\Lib\Pdf\CustomerInvoiceWithTaxBasedOnInvoiceSumTcpdf;
use Cake\Datasource\FactoryLocator;

class InvoiceToCustomerWithTaxBasedOnInvoiceSumPdfWriter extends PdfWriter
{

    public $Invoice;

    public function __construct()
    {
        $this->plugin = 'Admin';
        $this->setPdfLibrary(new CustomerInvoiceWithTaxBasedOnInvoiceSumTcpdf());
        $this->Invoice = FactoryLocator::get('Table')->get('Invoices');
        $this->templateFile = DS . 'pdf' . DS . 'invoice_to_customer';
    }

    public function prepareAndSetData($data, $paidInCash, $newInvoiceNumber, $invoiceDate)
    {
        $this->setData([
            'result' => $data,
            'sumPriceIncl' => $data->sumPriceIncl,
            'sumPriceExcl' => $data->sumPriceExcl,
            'sumTax' => $data->sumTax,
            'newInvoiceNumber' => $newInvoiceNumber,
            'invoiceDate' => $invoiceDate,
            'paidInCash' => $paidInCash,
        ]);
    }

}