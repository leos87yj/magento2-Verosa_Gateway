<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Verosa\Pay\Model;

use \Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    const KEY_ACTIVE = 'active';
    const KEY_TESTING = 'testing';
    const KEY_COMPANY_ID = 'company_id';
    const KEY_USER_ID = 'user_id';
    const KEY_AUTH_KEY = 'auth_key';
    const KEY_DEBUG = 'debug';
    const KEY_USE_PROXY = 'use_proxy';
    const KEY_PROXY_URL = 'proxy_url';
    const KEY_BY_PASS_SSL = 'bypassssl';

    /**
     * @var string
     */
    protected $methodCode = 'verosa_pay';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var string
     */
    protected $companyId = '';

    /**
     * @var string
     */
    protected $userId;

    /**
     * @var string
     */
    protected $authKey;

    /**
     * @var string
     */
    protected $useProxy;

    /**
     * @var string
     */
    protected $proxyUrl;

    /**
     * @var string
     */
    protected $byPassSsl;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var int
     */
    protected $storeId = null;


    /**
     * @var array
     */
    protected $verosaSharedConfigFields = [
        'testing' => true,
        'company_id' => true,
        'user_id' => true,
        'auth_key' => true,
        'use_proxy' => true,
        'proxy_url' => true,
        'bypassssl' => true,
    ];

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        if ($this->getConfigData(self::KEY_ACTIVE) == 1)
            $this->initEnvironment(null);
    }

    /**
     * @param int $storeId
     * @return $this
     */
    public function setStoreId($storeId)
    {
        $this->storeId = $storeId;
        return $this;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param null|string $storeId
     *
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        if ($storeId == null) {
            $storeId = $this->storeId;
        }
        if (array_key_exists($field, $this->verosaSharedConfigFields)) {
            $code = Payment::METHOD_CODE;
        } else {
            $code = $this->methodCode;
        }
        $path = 'payment/' . $code . '/' . $field;
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }
    /**
     * Initializes environment. This function can be called more than once with different storeId
     *
     * @param int $storeId
     * @return $this
     */
    public function initEnvironment($storeId)
    {
        $this->comanyId = $this->getConfigData(self::KEY_COMPANY_ID, $storeId);
        $this->userId = $this->getConfigData(self::KEY_USER_ID, $storeId);
        $this->authKey = $this->getConfigData(self::KEY_AUTH_KEY, $storeId);
        $this->useProxy = $this->getConfigData(self::KEY_USE_PROXY, $storeId);
        $this->proxyUrl = $this->getConfigData(self::KEY_PROXY_URL, $storeId);
        $this->byPassSsl = $this->getConfigData(self::KEY_BY_PASS_SSL, $storeId);
        $this->url = $this->getConfigData(self::KEY_TESTING, $storeId) ? 'https://devgate.cold-beat.com/vault2/request.php' : 'https://gate.verosa.com/vault2/request.php';

        $this->storeId = $storeId;
        return $this;
    }


    /**
     * @return bool
     */
    public function isActive()
    {
        return (bool)(int)$this->getConfigData(self::KEY_ACTIVE, $this->storeId);
    }
    /**
     * @return string
     */
    public function getCompanyId()
    {
        return $this->comanyId;
    }
    /**
     * @return string
     */
    public function getUserId()
    {
        return $this->userId;
    }
    /**
     * @return string
     */
    public function getAuthKey()
    {
        return $this->authKey;
    }
    /**
     * @return string
     */
    public function getUseProxy()
    {
        return $this->useProxy;
    }
    /**
     * @return string
     */
    public function getProxyUrl()
    {
        return $this->proxyUrl;
    }

    /**
     * @return string
     */
    public function getByPassSsl()
    {
        return $this->byPassSsl;
    }
    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return bool
     */
    public function isDebugEnabled()
    {
        return (bool)(int)$this->getConfigData(self::KEY_DEBUG);
    }
}
