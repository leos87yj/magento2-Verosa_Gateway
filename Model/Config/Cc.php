<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Verosa\Pay\Model\Config;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\CollectionFactory as TransactionCollectionFactory;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;
use Magento\Payment\Model\InfoInterface;

class Cc extends \Verosa\Pay\Model\Config
{
    const KEY_USE_CVV = 'useccv';
    const KEY_ACTIVE = 'active';

    /**
     * @var string
     */
    protected $methodCode = 'verosa_pay';

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;


    //@codeCoverageIgnoreStart
    /**
     * @return bool
     */
    public function isActive()
    {
        return (bool)(int)$this->getConfigData(self::KEY_ACTIVE, $this->storeId);
    }

    /**
     * If to validate cvv
     *
     * @return boolean
     */
    public function useCvv()
    {
        return (bool)(int)$this->getConfigData(self::KEY_USE_CVV, $this->storeId);
    }

    //@codeCoverageIgnoreEnd
}
