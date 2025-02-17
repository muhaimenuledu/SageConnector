<?php

namespace Harriswebworks\SageConnector\Controller\Adminhtml\SageConnector;

use Magento\Backend\App\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;

class CustomerSync extends Action
{
    protected $messageManager;
    protected $resultPageFactory;
    protected $_scopeConfig;

    public function __construct(
        Action\Context $context,
        ManagerInterface $messageManager,
        PageFactory $resultPageFactory,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->messageManager = $messageManager;
        $this->_scopeConfig = $scopeConfig;
    }

    public function execute()
    {
        // Check if the module is enabled
        // $isEnabled = $this->_scopeConfig->getValue('hww_SageConnector/general/enable', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        // if (!$isEnabled) {
        //     $this->messageManager->addErrorMessage(__('Sage Connector module is disabled in the configuration.'));
        //     $this->_redirect($this->_redirect->getRefererUrl());
        //     return;
        // }

        // Get the Sage API URL from configuration
        $url = $this->_scopeConfig->getValue('hww_SageConnector/general/url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if (empty($url)) {
            $this->messageManager->addErrorMessage(__('Sage API URL is not configured.'));
            $this->_redirect($this->_redirect->getRefererUrl());
            return;
        }

        // Fixed data for syncing one customer
        $postData = json_encode([
            "ARDivisionNo" => "10",
            "CustomerNo" => "C123465"
        ]);

        // Initialize cURL session
        $curl = curl_init();

        // Set cURL options
        curl_setopt_array($curl, [
            CURLOPT_URL => $url . '/customer/create/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            // Disable SSL verification for self-signed certificates
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        // Execute cURL request and get response
        $response = curl_exec($curl);
        // dd($response);

        // Check for cURL errors
        if (curl_errno($curl)) {
            $errorMessage = 'cURL Error: ' . curl_error($curl);
            $this->messageManager->addErrorMessage($errorMessage);
        } else {
            $this->messageManager->addSuccessMessage(__($response));
        }

        // Close cURL session
        curl_close($curl);

        // Redirect back to the referring page
        $this->_redirect($this->_redirect->getRefererUrl());
    }
}
