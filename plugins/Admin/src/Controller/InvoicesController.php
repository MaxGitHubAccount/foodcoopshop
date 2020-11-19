<?php

namespace Admin\Controller;

use App\Lib\Invoice\GenerateInvoiceToCustomer;
use App\Lib\PdfWriter\InvoiceToCustomerPdfWriter;
use Cake\Core\Configure;
use Cake\Core\Exception\Exception;
use Cake\Http\Exception\NotFoundException;

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
class InvoicesController extends AdminAppController
{

    public function isAuthorized($user)
    {
        return Configure::read('appDb.FCS_SEND_INVOICES_TO_CUSTOMERS') && $this->AppAuth->isSuperadmin();
    }

    public function generate()
    {

        $customerId = h($this->getRequest()->getQuery('customerId'));
        $paidInCash = h($this->getRequest()->getQuery('paidInCash'));

        $customer = $this->Customer->find('all', [
            'conditions' => [
                'Customers.id_customer' => $customerId,
            ],
        ])->first();

        if (empty($customer)) {
            throw new Exception('customer not found');
        }

        $data = $this->Customer->Invoices->getDataForCustomerInvoice($customer->id_customer, Configure::read('app.timeHelper')->getCurrentDateForDatabase());
        if (!$data->new_invoice_necessary) {
            $this->Flash->success(__d('admin', 'No_data_available_to_generate_an_invoice.'));
            $this->redirect($this->referer());
            return;
        }

        $currentDay = Configure::read('app.timeHelper')->getCurrentDateTimeForDatabase();

        $invoiceToCustomer = new GenerateInvoiceToCustomer();
        $newInvoice = $invoiceToCustomer->run($data, $currentDay, $paidInCash);

        $linkToInvoice = Configure::read('app.htmlHelper')->link(
            __d('admin', 'Download'),
            '/admin/lists/getInvoice?file=' . $newInvoice->filename,
            [
                'class' => 'btn btn-outline-light btn-flash-message',
                'target' => '_blank',
                'escape' => false
            ],
        );
        $messageString = __d('admin', 'Invoice_number_{0}_of_{1}_was_generated_successfully.', [
            '<b>' . $newInvoice->invoice_number . '</b>',
            '<b>' . $customer   ->name . '</b>',
        ]);
        $this->Flash->success($messageString . ' ' . $linkToInvoice);

        $this->ActionLog = $this->getTableLocator()->get('ActionLogs');
        $this->ActionLog->customSave('invoice_added', $this->AppAuth->getUserId(), $newInvoice->id, 'invoices', $messageString);

        $this->redirect($this->referer());

    }

    public function preview()
    {

        $customerId = h($this->getRequest()->getQuery('customerId'));
        $paidInCash = h($this->getRequest()->getQuery('paidInCash'));

        $currentDay = Configure::read('app.timeHelper')->getCurrentDateForDatabase();
        if (!empty($this->getRequest()->getQuery('currentDay'))) {
            $currentDay = $this->getRequest()->getQuery('currentDay');
        }

        $customer = $this->Customer->find('all', [
            'conditions' => [
                'Customers.id_customer' => $customerId,
            ],
        ])->first();

        if (empty($customer)) {
            throw new NotFoundException();
        }

        $newInvoiceNumber = 'xxx';
        $newInvoiceDate = 'xx.xx.xxxx';

        $pdfWriter = new InvoiceToCustomerPdfWriter();
        $data = $this->Customer->Invoices->getDataForCustomerInvoice($customerId, $currentDay);
        if (!$data->new_invoice_necessary) {
            die(__d('admin', 'No_data_available_to_generate_an_invoice.'));
        }

        $pdfWriter->prepareAndSetData($data, $paidInCash, $newInvoiceNumber, $newInvoiceDate);

        if (!empty($this->request->getQuery('outputType')) && $this->request->getQuery('outputType') == 'html') {
            return $this->response->withStringBody($pdfWriter->writeHtml());
        }

        $invoicePdfFile = Configure::read('app.htmlHelper')->getInvoiceLink($customer->name, $customerId, date('Y-m-d'), $newInvoiceNumber);
        $invoicePdfFile = explode(DS, $invoicePdfFile);
        $invoicePdfFile = end($invoicePdfFile);
        $invoicePdfFile = substr($invoicePdfFile, 11);
        $invoicePdfFile = $this->request->getQuery('dateFrom'). '-' . $this->request->getQuery('dateTo') . '-' . $invoicePdfFile;
        $pdfWriter->setFilename($invoicePdfFile);

        die($pdfWriter->writeInline());
    }

    public function cancel()
    {

        $this->RequestHandler->renderAs($this, 'json');

        $invoiceId = h($this->getRequest()->getData('invoiceId'));

        $this->Invoice = $this->getTableLocator()->get('Invoices');
        $this->OrderDetail = $this->getTableLocator()->get('OrderDetails');
        $this->Payment = $this->getTableLocator()->get('Payments');

        $invoice = $this->Invoice->find('all', [
            'contain' => [
                'OrderDetails',
                'Payments',
                'Customers',
            ],
            'conditions' => [
                'Invoices.id' => $invoiceId,
            ],
        ])->first();

        if (empty($invoice)) {
            throw new NotFoundException();
        }

        $invoice->status = APP_DEL;
        $this->Invoice->save($invoice);

        foreach($invoice->order_details as $orderDetail) {
            $patchedEntity = $this->OrderDetail->patchEntity(
                $orderDetail,
                [
                    'order_state' => ORDER_STATE_ORDER_PLACED,
                    'id_invoice' => null,
                ]
            );
            $this->OrderDetail->save($patchedEntity);
        }

        foreach($invoice->payments  as $payment) {
            $patchedEntity = $this->Payment->patchEntity(
                $payment,
                [
                    'invoice_id' => null,
                ]
            );
            $this->Payment->save($patchedEntity);
        }

        $flashMessage = __d('admin', 'Invoice_number_{0}_of_{1}_was_successfully_cancelled.', [
            '<b>' . $invoice->invoice_number . '</b>',
            '<b>' . $invoice->customer->name . '</b>',
        ]);
        $this->Flash->success($flashMessage);

        $this->ActionLog = $this->getTableLocator()->get('ActionLogs');
        $this->ActionLog->customSave('invoice_cancelled', $this->AppAuth->getUserId(), $invoiceId, 'invoices', $flashMessage);

        $this->set([
            'status' => 1,
            'msg' => 'ok',
        ]);
        $this->viewBuilder()->setOption('serialize', ['status', 'msg']);

    }

    public function index()
    {

        $dateFrom = Configure::read('app.timeHelper')->getFirstDayOfThisYear();
        if (! empty($this->getRequest()->getQuery('dateFrom'))) {
            $dateFrom = h($this->getRequest()->getQuery('dateFrom'));
        }
        $this->set('dateFrom', $dateFrom);

        $dateTo = Configure::read('app.timeHelper')->getLastDayOfThisYear();
        if (! empty($this->getRequest()->getQuery('dateTo'))) {
            $dateTo = h($this->getRequest()->getQuery('dateTo'));
        }
        $this->set('dateTo', $dateTo);

        $customerId = '';
        if (! empty($this->getRequest()->getQuery('customerId'))) {
            $customerId = h($this->getRequest()->getQuery('customerId'));
        }
        $this->set('customerId', $customerId);

        $this->Customer = $this->getTableLocator()->get('Customers');
        $this->Invoice = $this->getTableLocator()->get('Invoices');

        $conditions = [
            'Invoices.id_customer > 0',
            'DATE_FORMAT(Invoices.created, \'%Y-%m-%d\') >= \'' . Configure::read('app.timeHelper')->formatToDbFormatDate($dateFrom) . '\'',
            'DATE_FORMAT(Invoices.created, \'%Y-%m-%d\') <= \'' . Configure::read('app.timeHelper')->formatToDbFormatDate($dateTo) . '\'',
        ];

        if ($customerId != '') {
            $conditions['Invoices.id_customer'] = $customerId;
        }

        $query = $this->Invoice->find('all', [
            'contain' => [
                'InvoiceTaxes',
                'Customers',
            ],
            'conditions' => $conditions,
        ]);

        $invoices = $this->paginate($query, [
            'sortableFields' => [
                'Invoices.id',
                'Invoices.invoice_number',
                'Invoices.created',
                'Customers.' . Configure::read('app.customerMainNamePart'),
                'Invoices.paid_in_cash',
                'Invoices.email_status',
            ],
            'order' => [
                'Invoices.id' => 'DESC'
            ]
        ])->toArray();

        $this->set('invoices', $invoices);

        $invoiceSums = [
            'total_sum_price_excl' => 0,
            'total_sum_tax' => 0,
            'total_sum_price_incl' => 0,
        ];

        foreach($invoices as $invoice) {

            if ($invoice->status < APP_ON) {
                continue;
            }

            foreach($invoice->invoice_taxes as $invoiceTax) {
                $invoiceSums['total_sum_price_excl'] += $invoiceTax->total_price_tax_excl;
                $invoiceSums['total_sum_tax'] += $invoiceTax->total_price_tax;
                $invoiceSums['total_sum_price_incl'] += $invoiceTax->total_price_tax_incl;
            }

        }
        $this->set('invoiceSums', $invoiceSums);

        $this->set('customersForDropdown', $this->Customer->getForDropdown());
        $this->set('title_for_layout', __d('admin', 'Invoices'));

        $preparedTaxRates = $this->Invoice->getPreparedTaxRatesForSumTable($invoices);
        $this->set('taxRates', $preparedTaxRates['taxRates']);
        $this->set('taxRatesSums', $preparedTaxRates['taxRatesSums']);

    }

}
