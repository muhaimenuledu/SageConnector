<?php
namespace Harriswebworks\SageConnector\Controller\Adminhtml\SageConnector;

use Magento\Backend\App\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultFactory;

class GetCustomers extends Action
{
    protected $_scopeConfig;

    public function __construct(
        Action\Context $context,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->_scopeConfig = $scopeConfig;
    }

    public function execute()
    {
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        // Get the URL from the configuration
        $url = $this->_scopeConfig->getValue('hww_SageConnector/general/url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        // Initialize cURL session
        $curl = curl_init();

        // Set cURL options
        curl_setopt_array($curl, [
            CURLOPT_URL => $url . '/customer/',
            CURLOPT_RETURNTRANSFER => true, // Return the response instead of outputting it directly
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30, // Set timeout for the request
            CURLOPT_FOLLOWLOCATION => true, // Follow any "Location" header that the server sends
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, // Use HTTP 1.1
            CURLOPT_CUSTOMREQUEST => 'GET', // Send a GET request
            CURLOPT_SSL_VERIFYHOST => 0, // Don't check the SSL certificate's host name
            CURLOPT_SSL_VERIFYPEER => false, // Skip SSL verification (useful for self-signed certificates)
        ]);

        // Execute the cURL request and capture the response
        $response = curl_exec($curl);
        if ($response === false) {
            echo 'cURL Error: ' . curl_error($curl);
        } else {
            // Print the response
            echo $response;
        }
        $error = curl_error($curl);

        // Close cURL session
        curl_close($curl);

        // Success message
        // $this->messageManager->addSuccessMessage(__('Customer Data synced with Sage successfully.'));
        // $this->_redirect($this->_redirect->getRefererUrl());

        // If cURL error occurs, return error message
        if ($error) {
            $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $result->setContents('Curl Error: ' . $error);
            return $result;
        } else {
            // Print the formatted response
            $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $result->setContents('<pre>' . print_r(json_decode($response, true), true) . '</pre>');
            return $result;
        }
    }
}
