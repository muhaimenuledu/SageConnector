<?php

namespace Harriswebworks\SageConnector\Controller\Adminhtml\SageConnector;

use Magento\Backend\App\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\ResourceConnection;

class CustomerSync extends Action
{
    protected $messageManager;
    protected $resultPageFactory;
    protected $_scopeConfig;
    protected $_resourceConnection;

    public function __construct(
        Action\Context $context,
        ManagerInterface $messageManager,
        PageFactory $resultPageFactory,
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->messageManager = $messageManager;
        $this->_scopeConfig = $scopeConfig;
        $this->_resourceConnection = $resourceConnection;
    }

    public function execute()
    {
        // Get the Sage API URL from configuration
        $url = $this->_scopeConfig->getValue('hww_SageConnector/general/url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if (empty($url)) {
            $this->messageManager->addErrorMessage(__('Sage API URL is not configured.'));
            $this->_redirect($this->_redirect->getRefererUrl());
            return;
        }

        // Fetch customer data with specific email
        $connection = $this->_resourceConnection->getConnection();
        $customerEntityTable = $connection->getTableName('customer_entity');
        $cpsCustomersTable = $connection->getTableName('cps_customers');

        // SQL query to join customer_entity with cps_customers on email
        $select = $connection->select()
            ->from(
                ['ce' => $customerEntityTable],
                ['email']
            )
            ->join(
                ['cc' => $cpsCustomersTable],
                'ce.email = cc.email_address',
                ['customer_no' => 'customer_no']
            )
            ->where('ce.email = ?', 'm.islam@email.com') // Filter by specific email
            ->limit(1); // Only fetch one customer

        // Fetch customer
        $customer = $connection->fetchRow($select);

        // Check if customer is found
        if ($customer) {
            $email = $customer['email'];
            $customerNo = $customer['customer_no'];

            // Prepare data for sync (as an example)
            $postData = json_encode([
                "ARDivisionNo" => "10",
                "CustomerNo" => $customerNo,
                "EmailAddress" => $email
            ]);

            // Initialize cURL session for syncing data
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

            // Check for cURL errors
            if (curl_errno($curl)) {
                $errorMessage = 'cURL Error: ' . curl_error($curl);
                $this->messageManager->addErrorMessage($errorMessage);
            } else {
                $this->messageManager->addSuccessMessage(__('Customer Email: ' . $email . ' Synced Successfully. Response: ' . $response));
            }

            // Close cURL session
            curl_close($curl);
        } else {
            $this->messageManager->addErrorMessage(__('No customer found with the email m.islam@email.com.'));
        }

        // Redirect back to the referring page
        $this->_redirect($this->_redirect->getRefererUrl());
    }
}
