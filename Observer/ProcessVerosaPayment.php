<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Verosa\Pay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Verosa\Pay\Model\Payment;

class ProcessVerosaPayment implements ObserverInterface
{
    const CONFIG_PATH_CAPTURE_ACTION    = 'capture_action';
    const CONFIG_PATH_PAYMENT_ACTION    = 'payment_action';
    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $transactionFactory;
    /**
     * @var \Verosa\Pay\Model\Config\Cc
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param \Verosa\Pay\Model\Config\Cc $config
     */
    public function __construct(
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Verosa\Pay\Model\Config\Cc $config
   ) {
        $this->config = $config;
        $this->transactionFactory = $transactionFactory;
    }

    /**
     * If it's configured to capture on shipment - do this
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $shipment = $observer->getEvent()->getShipment();

        $order = $shipment->getOrder();
        if ($order->getPayment()->getMethod() == Payment::METHOD_CODE
            && $order->canInvoice()
            && $this->shouldInvoice()
        ) {
            $qtys = [];
            foreach ($shipment->getAllItems() as $shipmentItem) {
                $qtys[$shipmentItem->getOrderItem()->getId()] = $shipmentItem->getQty();
            }
            foreach ($order->getAllItems() as $orderItem) {
                if (!array_key_exists($orderItem->getId(), $qtys)) {
                    $qtys[$orderItem->getId()] = 0;
                }
            }
            $invoice = $order->prepareInvoice($qtys);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->register();
            $transaction = $this->transactionFactory->create();
            $transaction->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();
        }

        return $this;
    }


    /**
     * If it's configured to capture on each shipment
     *
     * @return bool
     */
    private function shouldInvoice()
    {
        $flag = (($this->config->getConfigData(self::CONFIG_PATH_PAYMENT_ACTION) ==
                \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE) &&
            ($this->config->getConfigData(self::CONFIG_PATH_CAPTURE_ACTION) ==
                Payment::CAPTURE_ON_SHIPMENT));

        return $flag;
    }
}
