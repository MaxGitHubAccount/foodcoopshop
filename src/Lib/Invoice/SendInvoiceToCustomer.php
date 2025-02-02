<?php
/**
 * FoodCoopShop - The open source software for your foodcoop
 *
 * Licensed under the GNU Affero General Public License version 3
 * For full copyright and license information, please see LICENSE
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         FoodCoopShop 3.5.0
 * @license       https://opensource.org/licenses/AGPL-3.0
 * @author        Mario Rothauer <office@foodcoopshop.com>
 * @copyright     Copyright (c) Mario Rothauer, https://www.rothauer-it.com
 * @link          https://www.foodcoopshop.com
 */

namespace App\Lib\Invoice;

use App\Lib\HelloCash\HelloCash;
use App\Mailer\AppMailer;

class SendInvoiceToCustomer
{

    public $customerName;
    public $customerEmail;
    public $creditBalance;
    public $invoicePdfFile;
    public $invoiceNumber;
    public $invoiceDate;
    public $invoiceId;
    public $paidInCash;
    public $isCancellationInvoice;
    public $originalInvoiceId;

    public function run()
    {

        $customerName = $this->customerName;
        $customerEmail = $this->customerEmail;
        $creditBalance = $this->creditBalance;
        $invoicePdfFile = $this->invoicePdfFile;
        $invoiceNumber = $this->invoiceNumber;
        $invoiceDate = $this->invoiceDate;
        $invoiceId = $this->invoiceId;
        $paidInCash = $this->paidInCash;
        $isCancellationInvoice = (bool) $this->isCancellationInvoice;
        $originalInvoiceId = $this->originalInvoiceId ?? $invoiceId;

        $subject = __('Invoice_number_abbreviataion_{0}_{1}', [$invoiceNumber, $invoiceDate]);
        $emailTemplate = 'Admin.send_invoice_to_customer';
        if ($isCancellationInvoice) {
            $emailTemplate = 'Admin.send_cancellation_invoice_to_customer';
            $subject = __('Cancellation_invoice_number_abbreviataion_{0}_{1}', [$invoiceNumber, $invoiceDate]);
        }

        $email = new AppMailer();
        $email->viewBuilder()->setTemplate($emailTemplate);
        $email->setTo($customerEmail)
        ->setSubject($subject)
        ->setViewVars([
            'paidInCash' => $paidInCash,
            'customerName' => $customerName,
            'creditBalance' => $creditBalance,
        ]);

        if (!empty($invoicePdfFile)) {
            $email->addAttachments([$invoicePdfFile]);
        } else {
            $helloCash = new HelloCash();
            $attachmentPrefix = __('Invoice');
            if ($isCancellationInvoice) {
                $attachmentPrefix = __('Cancellation_invoice');
            }
            $email->addAttachments([
                str_replace(' ', '_', $attachmentPrefix) . '_' . $invoiceNumber . '.pdf' => [
                    'data' => $helloCash->getInvoice($originalInvoiceId, $isCancellationInvoice)->getStringBody(),
                    'mimetype' => 'application/pdf',
                ],
            ]);
        }
        $email->afterRunParams = [
            'invoiceId' => $invoiceId,
        ];
        $email->addToQueue();

    }

}
