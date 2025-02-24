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

    const PAGE_SIZE = 10; // Number of customers per request

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

        $page = 0;
        do {
            $select = $connection->select()
                ->from(['ce' => $customerEntityTable], ['email', 'firstname', 'lastname'])
                ->join(['cc' => $cpsCustomersTable], 'ce.email = cc.email_address', ['customer_no' => 'customer_no'])
                ->limit(self::PAGE_SIZE, $page * self::PAGE_SIZE);

            $customers = $connection->fetchAll($select);

            if ($customers) {
                $this->syncCustomers($customers, $url);
            }
            $page++;
        } while (!empty($customers));

        $this->_redirect($this->_redirect->getRefererUrl());
    }

    private function syncCustomers(array $customers, string $url)
    {
        foreach ($customers as $customer) {
            $name = trim($customer['firstname'] . ' ' . $customer['lastname']);
            $postData = json_encode([
                "ARDivisionNo" => "10",
                "CustomerNo" => $customer['customer_no'],
                "CustomerName" => $name,
                "AddressLine1" => "9400 Ashton Road",
                "City" => "Philadelphia",
                "ZipCode" => "19114",
                "CountryCode" => "USA",
                "TelephoneNo" => "(215) 969-3500",
                "EmailAddress" => $customer['email'],
                "TaxSchedule" => "AVATAX",
                "TermsCode" => "00",
                "SalespersonDivisionNo" => "10",
                "SalespersonNo" => "9999",
                "PriceLevel" => "R"
            ]);

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $url . '/customer/create/',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if (curl_errno($curl)) {
                $this->messageManager->addErrorMessage(__('cURL Error: ' . curl_error($curl)));
            } else {
                $decodedResponse = json_decode($response, true);
                if ($httpCode >= 200 && $httpCode < 300) {
                    $message = __('Customer Email: %1 Synced Successfully.', $customer['email']);
                    if (!empty($decodedResponse)) {
                        $message .= ' Response: ' . print_r($decodedResponse, true);
                    }
                    $this->messageManager->addSuccessMessage($message);
                } else {
                    $errorMessage = __('Failed to sync Customer Email: %1. HTTP Code: %2', $customer['email'], $httpCode);
                    if (!empty($decodedResponse)) {
                        $errorMessage .= ' Response: ' . print_r($decodedResponse, true);
                    }
                    $this->messageManager->addErrorMessage($errorMessage);
                }
            }

            curl_close($curl);
        }
    }
}