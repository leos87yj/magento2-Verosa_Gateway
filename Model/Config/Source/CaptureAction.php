<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Verosa\Pay\Model\Config\Source;

use \Verosa\Pay\Model\Payment;

/**
 * Class CaptureAction
 * @codeCoverageIgnore
 */
class CaptureAction implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Possible actions to capture
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => Payment::CAPTURE_ON_INVOICE,
                'label' => __('Invoice'),
            ],
            [
                'value' => Payment::CAPTURE_ON_SHIPMENT,
                'label' => __('Shipment'),
            ],
        ];
    }
}
