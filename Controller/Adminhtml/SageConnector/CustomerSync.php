<?php

namespace Harriswebworks\SageConnector\Controller\Adminhtml\SageConnector;

use Magento\Backend\App\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;

class CustomerSync extends Action
{
    protected $messageManager;
    protected $resultPageFactory;
    protected $_scopeConfig;
    protected $_customerFactory;

    public function __construct(
        Action\Context $context,
        ManagerInterface $messageManager,
        PageFactory $resultPageFactory,
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $customerFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->messageManager = $messageManager;
        $this->_scopeConfig = $scopeConfig;
        $this->_customerFactory = $customerFactory;
    }

    public function execute()
    {
        $customerCollection = $this->_customerFactory->create();

        foreach ($customerCollection as $customer) {
            $fname = $customer->getFirstname();
            $lname = $customer->getLastname();
            $name = $fname . ' ' . $lname;
            $email = $customer->getEmail();
            $this->sendToSage($name, $email);
        }
        $this->messageManager->addSuccessMessage(__('Customer Data synced with Sage successfully.'));
        $this->_redirect($this->_redirect->getRefererUrl());
    }

    protected function sendToSage($name, $email)
    {
        $isEnabled = $this->_scopeConfig->getValue('hww_SageConnector/general/enable', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $url = $this->_scopeConfig->getValue('hww_SageConnector/general/url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if (!$isEnabled) {
            return;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url . '/api/customerSync',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                "params" => [
                    "name" => $name,
                    "email" => $email
                ]
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
    }
}
