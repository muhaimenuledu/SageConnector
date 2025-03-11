<?php

namespace Harriswebworks\SageConnector\Controller\Adminhtml\SageConnector;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

class MassSync extends \Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction
{
    protected $collectionFactory;
    protected $scopeConfig;
    protected $logger;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        parent::__construct($context, $filter);
        $this->collectionFactory = $collectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    protected function massAction(AbstractCollection $collection)
    {
        $countSyncedOrders = 0;

        foreach ($collection as $order) {
            try {
                $c_no = rand(10, 10000);
                $customerEmail = $order->getCustomerEmail();
                $customerName = $order->getCustomerName();
                $orderId = $order->getId();
                // dd($orderId);
                $createdAt = $order->getCreatedAt();

                $orderItems = [];
                foreach ($order->getAllVisibleItems() as $item) {
                    $orderItems[] = [
                        0,
                        0,
                        [
                            'default_code' => $item->getSku(),
                            'product_uom_qty' => $item->getQtyOrdered()
                        ]
                    ];
                }

                $data = json_encode($orderItems, JSON_PRETTY_PRINT);
                $this->syncCustomer($customerName, $customerEmail, $c_no);
                //$this->syncOrder($customerEmail, $orderId, $createdAt, $data, $c_no);
                $countSyncedOrders++;
                
            } catch (\Exception $e) {
                $this->logger->error(__('Order Sync Error: %1', $e->getMessage()));
            }
        }

        if ($countSyncedOrders) {
            $this->messageManager->addSuccessMessage(__('%1 order(s) synced successfully.', $countSyncedOrders));
        } else {
            $this->messageManager->addErrorMessage(__('No orders were synced.'));
        }

        return $this->resultRedirectFactory->create()->setPath('sales/order/index');
    }

    private function syncCustomer($name, $email, $c_no)
    {


        //sage 
        
        // dd($email);
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
                "AddressLine1" => "9400 Ashton Road",
                "City" => "Philadelphia",
                "State" => "PA",
                "ZipCode" => "19114",
                "CountryCode" => "USA",
                "TelephoneNo" => "(215) 969-3500",
                "EmailAddress" => $email,
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
            
            $this->messageManager->addNoticeMessage(__($customerNo));
            //orderSage
            $curl = curl_init();
        
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://52.186.11.198:88/post_so_header.php',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_POSTFIELDS => json_encode([
                    "SalesOrderNo" => "MG" . $c_no,
                    "OrderDate" => "2022-01-30",
                    "OrderType" => "S",
                    "OrderStatus" => "H",
                    "ARDivisionNo" => "10",
                    "CustomerNo" => $customerNo,
                    "BillToName" => "INYODO MARTIAL ARTS",
                    "BillToAddress1" => "101 Fawcett Rd Unit 140",
                    "BillToCity" => "Avon",
                    "BillToState" => "CO",
                    "BillToZipCode" => "81620",
                    "BillToCountryCode" => "USA",
                    "ShipToCode" => "01",
                    "ShipToName" => "INYODO MARTIAL ARTS",
                    "ShipToAddress1" => "Ups: Leave @ Liquor Store if out",
                    "ShipToCity" => "Avon",
                    "ShipToState" => "CO",
                    "ShipToZipCode" => "81620-5375",
                    "ShipToCountryCode" => "USA",
                    "ShipVia" => "09",
                    "ShipWeight" => "00018",
                    "CustomerPONo" => "Hold-Card",
                    "WarehouseCode" => "000",
                    "ConfirmTo" => "DAVID SMITH",
                    "TermsCode" => "00",
                    "TaxSchedule" => "AVATAX",
                    "InvalidTaxCalc" => "N",
                    "PrintSalesOrders" => "N",
                    "PrintPickingSheets" => "Y",
                    "BatchFax" => "N",
                    "BatchEmail" => "N",
                    "EmailAddress" => $email,
                    "FreightCalculationMethod" => "A",
                    "LotSerialLinesExist" => "N",
                    "SalespersonDivisionNo" => "10",
                    "SalespersonNo" => "HN",
                    "SplitCommissions" => "N",
                    "PaymentTypeCategory" => "D",
                    "ResidentialAddress" => "Y",
                    "TaxableSubjectToDiscount" => "280.69",
                    "NonTaxableSubjectToDiscount" => ".00",
                    "TaxSubjToDiscPrcntOfTotSubjTo" => "100.00",
                    "DiscountRate" => ".000",
                    "DiscountAmt" => ".00",
                    "TaxableAmt" => "408.49",
                    "NonTaxableAmt" => ".00",
                    "SalesTaxAmt" => "20.02",
                    "Weight" => "18.00",
                    "FreightAmt" => ".00",
                    "DepositAmt" => ".00",
                    "CommissionRate" => ".000",
                    "SplitCommRate2" => ".000",
                    "SplitCommRate3" => ".000",
                    "SplitCommRate4" => ".000",
                    "SplitCommRate5" => ".000",
                    "NumberOfShippingLabels" => "0",
                    "LastNoOfShippingLabels" => "1",
                    "TimeCreated" => "16.8521",
                    "UserCreatedKey" => "0000000224",
                    "TimeUpdated" => "11.51153",
                    "UserUpdatedKey" => "0000000204",
                    "UDF_CAPPEDITEMS" => "N",
                    "UDF_MSDE_TOT" => "408.49",
                    "UDF_PC_CODE" => "437534",
                    "UDF_PROFITPERCENT" => "10.00",
                    "UDF_PROFITTYPE" => "D",
                    "UDF_SHIP_COUNT" => "0",
                    "UDF_TOTPROFIT" => "311.88",
                    "UDF_TYPEORDER" => "Standard",
                    "SalesOrderPrinted" => "Y",
                    "PickingSheetPrinted" => "N",
                    "UDF_READ_BACK_ORDER" => "N",
                    "PayBalance" => "N"
                ]),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
                // Disable SSL verification
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0
            ));
            
            $response = curl_exec($curl);
            
            if (curl_errno($curl)) {
                echo 'cURL Error: ' . curl_error($curl);
            } else {
                header('Content-Type: application/json');
                $this->messageManager->addNoticeMessage(__($response));
            }
            
            curl_close($curl);


            //orderSage
        } else {
            echo "Customer number not found in response.";
        }

        curl_close($curl);

        //sage

        /*
        dd($name);
        $url = $this->scopeConfig->getValue(
            'hww_SageConnector/general/url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $payload = json_encode([
            'params' => [
                'name' => $name,
                'email' => $email
            ]
        ]);

        $this->makeRequest($url . '/customerSyncAPI', $payload);
        */
    }

    private function syncOrder($customerEmail, $orderId, $createdAt, $data, $c_no)
    {
        //sage

        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://52.186.11.198:88/post_so_header.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS => json_encode([
                "SalesOrderNo" => $orderId,
                "OrderDate" => "2022-01-30",
                "OrderType" => "S",
                "OrderStatus" => "H",
                "ARDivisionNo" => "20",
                "CustomerNo" => "M001",
                "BillToName" => "INYODO MARTIAL ARTS",
                "BillToAddress1" => "101 Fawcett Rd Unit 140",
                "BillToCity" => "Avon",
                "BillToState" => "CO",
                "BillToZipCode" => "81620",
                "BillToCountryCode" => "USA",
                "ShipToCode" => "01",
                "ShipToName" => "INYODO MARTIAL ARTS",
                "ShipToAddress1" => "Ups: Leave @ Liquor Store if out",
                "ShipToCity" => "Avon",
                "ShipToState" => "CO",
                "ShipToZipCode" => "81620-5375",
                "ShipToCountryCode" => "USA",
                "ShipVia" => "09",
                "ShipWeight" => "00018",
                "CustomerPONo" => "Hold-Card",
                "WarehouseCode" => "000",
                "ConfirmTo" => "DAVID SMITH",
                "TermsCode" => "00",
                "TaxSchedule" => "AVATAX",
                "InvalidTaxCalc" => "N",
                "PrintSalesOrders" => "N",
                "PrintPickingSheets" => "Y",
                "BatchFax" => "N",
                "BatchEmail" => "N",
                "EmailAddress" => $customerEmail,
                "FreightCalculationMethod" => "A",
                "LotSerialLinesExist" => "N",
                "SalespersonDivisionNo" => "10",
                "SalespersonNo" => "HN",
                "SplitCommissions" => "N",
                "PaymentTypeCategory" => "D",
                "ResidentialAddress" => "Y",
                "TaxableSubjectToDiscount" => "280.69",
                "NonTaxableSubjectToDiscount" => ".00",
                "TaxSubjToDiscPrcntOfTotSubjTo" => "100.00",
                "DiscountRate" => ".000",
                "DiscountAmt" => ".00",
                "TaxableAmt" => "408.49",
                "NonTaxableAmt" => ".00",
                "SalesTaxAmt" => "20.02",
                "Weight" => "18.00",
                "FreightAmt" => ".00",
                "DepositAmt" => ".00",
                "CommissionRate" => ".000",
                "SplitCommRate2" => ".000",
                "SplitCommRate3" => ".000",
                "SplitCommRate4" => ".000",
                "SplitCommRate5" => ".000",
                "NumberOfShippingLabels" => "0",
                "LastNoOfShippingLabels" => "1",
                "TimeCreated" => "16.8521",
                "UserCreatedKey" => "0000000224",
                "TimeUpdated" => "11.51153",
                "UserUpdatedKey" => "0000000204",
                "UDF_CAPPEDITEMS" => "N",
                "UDF_MSDE_TOT" => "408.49",
                "UDF_PC_CODE" => "437534",
                "UDF_PROFITPERCENT" => "10.00",
                "UDF_PROFITTYPE" => "D",
                "UDF_SHIP_COUNT" => "0",
                "UDF_TOTPROFIT" => "311.88",
                "UDF_TYPEORDER" => "Standard",
                "SalesOrderPrinted" => "Y",
                "PickingSheetPrinted" => "N",
                "UDF_READ_BACK_ORDER" => "N",
                "PayBalance" => "N"
            ]),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            // Disable SSL verification
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0
        ));
        
        $response = curl_exec($curl);
        
        if (curl_errno($curl)) {
            echo 'cURL Error: ' . curl_error($curl);
        } else {
            header('Content-Type: application/json');
            $this->messageManager->addNoticeMessage(__($response));
        }
        
        curl_close($curl);

        //sage

        /*
        $url = $this->scopeConfig->getValue(
            'hww_SageConnector/general/url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $payload = json_encode([
            'params' => [
                'customer_email' => $customerEmail,
                'state' => 'sale',
                'magento_order_id' => $orderId,
                'magento_order_date' => $createdAt,
                'order_line' => json_decode($data)
            ]
        ]);

        $this->makeRequest($url . '/orderSyncAPI', $payload);
        */
    }

    private function makeRequest($url, $payload)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode !== 200) {
            $this->logger->error(__('API Error: %1', curl_error($ch)));
        }

        curl_close($ch);

        return json_decode($response, true);
    }
}
