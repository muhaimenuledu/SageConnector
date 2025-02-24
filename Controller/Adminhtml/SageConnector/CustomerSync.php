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
        $url = $this->_scopeConfig->getValue('hww_SageConnector/general/url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if (empty($url)) {
            $this->messageManager->addErrorMessage(__('Sage API URL is not configured.'));
            return $this->_redirect($this->_redirect->getRefererUrl());
        }

        $connection = $this->_resourceConnection->getConnection();
        $customerEntityTable = $connection->getTableName('customer_entity');
        $cpsCustomersTable = $connection->getTableName('cps_customers');

        // Fetch the last 10 customers (ordered by entity_id descending)
        $select = $connection->select()
            ->from(['ce' => $customerEntityTable], ['email', 'firstname', 'lastname'])
            ->join(['cc' => $cpsCustomersTable], 'ce.email = cc.email_address', ['customer_no' => 'customer_no'])
            ->order('ce.entity_id DESC') // Get most recent customers
            ->limit(1);

        $customers = $connection->fetchAll($select);

        if (!empty($customers)) {
            $this->syncCustomers($customers, $url);
        } else {
            $this->messageManager->addErrorMessage(__('No customers found for synchronization.'));
        }

        return $this->_redirect($this->_redirect->getRefererUrl());
    }

    private function syncCustomers(array $customers, string $url)
    {
        foreach ($customers as $customer) {
            $name = trim(($customer['firstname'] ?? '') . ' ' . ($customer['lastname'] ?? ''));
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://52.186.11.198:88/customer/create/',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode([
                    "ARDivisionNo" => "20",
                    "CustomerNo" => $customer['customer_no'],
                    "CustomerName" => $name,
                    "AddressLine1" => "9400 Ashton Road",
                    "City" => "Philadelphia",
                    "State" => "PA",
                    "ZipCode" => "19114",
                    "CountryCode" => "USA",
                    "TelephoneNo" => "(215) 969-3500",
                    "EmailAddress" => $customer['email'],
                    "TaxSchedule" => "AVATAX",
                    "TermsCode" => "00",
                    "SalespersonDivisionNo" => "10",
                    "SalespersonNo" => "9999",
                    "PriceLevel" => "R"
                ]),
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
                echo 'cURL Error: ' . curl_error($curl);
            } else {
                // Print the response
                header('Content-Type: application/json');
                $this->messageManager->addNoticeMessage(__($response));
            }
            // Close cURL session
            curl_close($curl);
        }
    }
}
