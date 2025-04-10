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
        $orderTable = $connection->getTableName('sales_order');
        $addressTable = $connection->getTableName('customer_address_entity');

        $regionTable = $connection->getTableName('directory_country_region');

        $select = $connection->select()
            ->from(['ce' => $customerEntityTable], ['email', 'firstname', 'lastname'])
            ->joinInner(
                ['so' => $orderTable],
                'ce.entity_id = so.customer_id',
                []
            )
            ->joinLeft(
                ['cae' => $addressTable],
                'ce.default_billing = cae.entity_id',
                ['street', 'city', 'postcode', 'country_id', 'telephone', 'region_id']
            )
            ->joinLeft(
                ['r' => $regionTable],
                'r.region_id = cae.region_id',
                ['region_code' => 'code']
            )
            ->group('ce.entity_id')
            ->order('ce.entity_id DESC')
            ->limit(5);

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
            $address = $customer['street'];
            $city = $customer['city'];
            $state = $customer['region_code'];
            $zip = $customer['postcode'];
            $country = $customer['country_id'];
            $telephone = $customer['telephone'];
            // dd($address, $city);
            $c_no = rand(10, 10000);
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
                    "CustomerNo" => "M" . $c_no,
                    "CustomerName" => $name,
                    "AddressLine1" => $address,
                    "City" => $city,
                    "State" => $state,
                    "ZipCode" => $zip,
                    "CountryCode" => $country,
                    "TelephoneNo" => $telephone,
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

            $response = curl_exec($curl);

            if (curl_errno($curl)) {
                echo 'cURL Error: ' . curl_error($curl);
            } else {
                header('Content-Type: application/json');
                $this->messageManager->addNoticeMessage(__($response));
            }

            $data = json_decode($response, true);
            if ($data && isset($data['customer_no'])) {
                $customerNo = $data['customer_no'];
                $ARDivisionNo = $data['ARDivisionNo'];
                
                $this->messageManager->addNoticeMessage(__($customerNo . "=" . $ARDivisionNo));
            } else {
                echo "Customer number not found in response.";
            }

            curl_close($curl);
        }
    }
}
