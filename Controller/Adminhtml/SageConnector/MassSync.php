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
                $orderNumber = preg_replace('/^000/', '', $order->getIncrementId());
                $createdAt = $order->getCreatedAt();
                $orderDate = date('Y-m-d', strtotime($createdAt));
                $hour = (int)date('H', strtotime($createdAt));
                $minute = (int)date('i', strtotime($createdAt));
                $orderTimeDecimal = $hour + ($minute / 60);

                $orderItems = [];
                foreach ($order->getAllVisibleItems() as $item) {
                    $itemCode = $item->getSku();
                    $orderItems[] = [
                        0,
                        0,
                        [
                            'default_code' => $itemCode,
                            'product_uom_qty' => $item->getQtyOrdered(),
                            'unit_price' => $item->getPrice()
                        ]
                    ];
                }

                $data = json_encode($orderItems, JSON_PRETTY_PRINT);
                $this->syncCustomer($customerName, $customerEmail, $c_no, $orderNumber, $itemCode, $orderDate, $order, $orderTimeDecimal, $orderItems);
                $countSyncedOrders++;

            } catch (\Exception $e) {
                $this->logger->error(__('Order Sync Error: %1', $e->getMessage()));
            }
        }

        if ($countSyncedOrders) {
            $this->messageManager->addSuccessMessage(__('%1 order(s) synced successfully.', $countSyncedOrders));
        } else {
            $this->messageManager->addErrorMessage(__('No orders were synced / Order exists in sage'));
        }

        return $this->resultRedirectFactory->create()->setPath('sales/order/index');
    }

    private function syncCustomer($name, $email, $c_no, $orderNumber, $itemCode, $orderDate, $order, $orderTimeDecimal, $orderItems)
    {
        
        $billingAddress = $order->getBillingAddress();

        $billToName = $billingAddress->getName();
        $billToAddress1 = $billingAddress->getStreetLine(1);
        $billToAddress2 = $billingAddress->getStreetLine(2) ?? '';
        $billToAddress3 = ''; // Magento by default has only 2 lines for address
        $billToCity = $billingAddress->getCity();
        $billToState = $billingAddress->getRegionCode();
        $billToZip = $billingAddress->getPostcode();
        $billToCountry = $billingAddress->getCountryId();
        $billToPhone = $billingAddress->getTelephone();

        $shippingAddress = $order->getShippingAddress();
        $shipToName = $shippingAddress->getName();
        $shipToAddress1 = $shippingAddress->getStreetLine(1);
        $shipToAddress2 =  $shippingAddress->getStreetLine(2);
        $shipToAddress3 =  $shippingAddress->getStreetLine(3);
        $shipToCity =  $shippingAddress->getCity();
        $shipToState =  $shippingAddress->getRegionCode();
        $shipToZip = $shippingAddress->getPostcode();
        $shipToCountry =  $shippingAddress->getCountryId();

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
                "AddressLine1" => $billToAddress1,
                "City" => $billToCity,
                "State" => $billToState,
                "ZipCode" => $billToZip,
                "CountryCode" => $billToCountry,
                "TelephoneNo" => $billToPhone,
                "EmailAddress" => $email,
                "TaxSchedule" => "AVATAX",
                "TermsCode" => "00",
                "SalespersonDivisionNo" => "10",
                "SalespersonNo" => "9999",
                "PriceLevel" => "R"
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            echo 'cURL Error: ' . curl_error($curl);
        } else {
            $this->messageManager->addNoticeMessage(__("customer_no fetched from sage & order syncing" . $response));
        }

        $data = json_decode($response, true);

        if ($data && isset($data['customer_no'])) {
            $customerNo = $data['customer_no'];
            $ARDivisionNo = $data['ARDivisionNo'];

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://52.186.11.198:88/post_so_header.php',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode([
                    "SalesOrderNo" => "M" . $orderNumber,
                    "OrderDate"=> $orderDate,
                    "OrderType"=> "S",
                    "OrderStatus"=> "H",
                    "ARDivisionNo"=> $ARDivisionNo,
                    "CustomerNo"=> $customerNo,
                    "BillToName"=> $billToName,
                    "BillToAddress1"=> $billToAddress1,
                    "BillToAddress2"=> $billToAddress2,
                    "BillToAddress3"=> $billToAddress3,
                    "BillToCity"=> $billToCity,
                    "BillToState"=> $billToState,
                    "BillToZipCode"=> $billToZip,
                    "BillToCountryCode"=> $billToCountry,
                    "ShipToCode"=> "01",
                    "ShipToName"=> $shipToName,
                    "ShipToAddress1"=> $shipToAddress1,
                    "ShipToAddress2"=> $shipToAddress2,
                    "ShipToAddress3"=> $shipToAddress3,
                    "ShipToCity"=> $shipToCity,
                    "ShipToState"=> $shipToState,
                    "ShipToZipCode"=> $shipToZip,
                    "ShipToCountryCode"=> $shipToCountry,
                    "ShipVia"=> "09",
                    "ShipWeight"=> "00018",
                    "CustomerPONo"=> "Hold-Card",
                    "WarehouseCode"=> "000",
                    "ConfirmTo"=> $billToName,
                    "Comment"=> "",
                    "TermsCode"=> "00",
                    "TaxSchedule"=> "NONTAX",
                    "TaxExemptNo"=> "",
                    "InvalidTaxCalc"=> "N",
                    "PrintSalesOrders"=> "N",
                    "PrintPickingSheets"=> "Y",
                    "BatchFax"=> "N",
                    "BatchEmail"=> "N",
                    "EmailAddress"=> $email,
                    "FreightCalculationMethod"=> "A",
                    "LotSerialLinesExist"=> "N",
                    "SalespersonDivisionNo"=> "10",
                    "SalespersonNo"=> "HN",
                    "SplitCommissions"=> "N",
                    "PaymentTypeCategory"=> "D",
                    "ResidentialAddress"=> "Y",
                    "TaxableSubjectToDiscount"=> " ",
                    "NonTaxableSubjectToDiscount"=> " ",
                    "TaxSubjToDiscPrcntOfTotSubjTo"=> " ",
                    "DiscountRate"=> " ",
                    "DiscountAmt"=> " ",
                    "TaxableAmt"=> " ",
                    "NonTaxableAmt"=> " ",
                    "SalesTaxAmt"=> " ",
                    "Weight"=> " ",
                    "FreightAmt"=> " ",
                    "DepositAmt"=> " ",
                    "CommissionRate"=> " ",
                    "SplitCommRate2"=> " ",
                    "SplitCommRate3"=> " ",
                    "SplitCommRate4"=> " ",
                    "SplitCommRate5"=> " ",
                    "NumberOfShippingLabels" => " ",
                    "LastNoOfShippingLabels" =>  " ",
                    "DateCreated"=> $orderDate,
                    "TimeCreated"=> $orderTimeDecimal,
                    "UserCreatedKey" => "0000000224",
                    "DateUpdated" => "",
                    "TimeUpdated" => " ",
                    "UserUpdatedKey" => "0000000204",
                    "UDF_CAPPEDITEMS" => "N",
                    "UDF_MSDE_TOT" => " ",
                    "UDF_PC_CODE" => " ",
                    "UDF_PROFITPERCENT" => " ",
                    "UDF_PROFITTYPE" => "D",
                    "UDF_SHIP_COUNT" => " ",
                    "UDF_TOTPROFIT" => " ",
                    "UDF_TYPEORDER" => "Standard",
                    "SalesOrderPrinted" => " ",
                    "PickingSheetPrinted" => " ",
                    "UDF_READ_BACK_ORDER" => " ",
                    "PayBalance" => " "
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0
            ]);

            $response = curl_exec($curl);
            $orderResponse = json_decode($response, true);
            $orderStatus = $orderResponse['status'];

            if ($orderStatus === 'success') {
                $this->messageManager->addNoticeMessage(__("Post Header Infromation " . $orderStatus . " SalesOrderNo => M" . $orderNumber));
                
                $customerGroupId = $order->getCustomerGroupId();
                $priceLevelMap = [
                    1 => 'R', // Retail
                    2 => 'W', // Wholesale
                ];
                $priceLevel = $priceLevelMap[$customerGroupId] ?? 'R'; // fallback to R

                $lineSeq = 1;
                foreach ($orderItems as $item) {
                    $itemCode = $item[2]['default_code'];
                    $qty = $item[2]['product_uom_qty'];
                    $unitPrice = $item[2]['unit_price'];
                    $extensionAmt = $qty * $unitPrice;
                    // Calculate discount percent from order item
                    $discountPercent = 0;
                    $orderItemObj = $order->getItemBySku($itemCode); // get actual order item object
                    if ($orderItemObj) {
                        $rowTotal = $orderItemObj->getRowTotal();
                        $discountAmount = $orderItemObj->getDiscountAmount();
                        if ($rowTotal > 0) {
                            $discountPercent = round(($discountAmount / $rowTotal) * 100, 2);
                        }
                    }

                    $curl = curl_init();
                    curl_setopt_array($curl, [
                        CURLOPT_URL => 'https://52.186.11.198:88/post_so_detail.php',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS => json_encode([
                            "SalesOrderNo" => "M" . $orderNumber,
                            "LineKey" => str_pad($lineSeq, 6, "0", STR_PAD_LEFT),
                            "LineSeqNo" => str_pad($lineSeq, 14, "0", STR_PAD_LEFT),
                            "ItemCode" => $itemCode,
                            "ItemType" => "1",
                            "WarehouseCode" => "000",
                            "UnitOfMeasure" => "EACH",
                            "PriceLevel" => $priceLevel,
                            "TaxClass" => "TX",
                            "QuantityOrdered" => $qty,
                            "UnitPrice" => $item[2]['unit_price'],
                            "ExtensionAmt" => $extensionAmt,
                            "Discount" => $discountPercent > 0 ? "Y" : "N",
                            "LineDiscountPercent" => $discountPercent

                        ]),
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                        CURLOPT_SSL_VERIFYHOST => 0,
                        CURLOPT_SSL_VERIFYPEER => 0
                    ]);
            
                    $response_so_detail = curl_exec($curl);
                    curl_close($curl);
                    $this->messageManager->addNoticeMessage(__("SO Detail Response: " . $response_so_detail));
            
                    $lineSeq++;
                }
            }

            curl_close($curl);
        } else {
            echo "Customer number not found in response.";
        }

        curl_close($curl);
    }



    private function syncOrder($customerEmail, $orderNumber, $createdAt, $data, $c_no)
    {
        //sage
        //this function will work when customer doesn't exists in sage
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://52.186.11.198:88/post_so_header.php',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode ([
            "SalesOrderNo" => "M" . $orderNumber,
            "OrderDate"=> "2022-01-30",
            "OrderType"=> "S",
            "OrderStatus"=> "H",
            "ARDivisionNo"=> "10",
            "CustomerNo"=> "CUST001",
            "BillToName"=> "INYODO MARTIAL ARTS",
            "BillToAddress1"=> "101 Fawcett Rd Unit 140",
            "BillToAddress2"=> "",
            "BillToAddress3"=> "",
            "BillToCity"=> "Avon",
            "BillToState"=> "CO",
            "BillToZipCode"=> "81620",
            "BillToCountryCode"=> "USA",
            "ShipToCode"=> "01",
            "ShipToName"=> "INYODO MARTIAL ARTS",
            "ShipToAddress1"=> "Ups: Leave @ Liquor Store if out",
            "ShipToAddress2"=> "uor",
            "ShipToAddress3"=> "",
            "ShipToCity"=> "Avon",
            "ShipToState"=> "CO",
            "ShipToZipCode"=> "81620-5375",
            "ShipToCountryCode"=> "USA",
            "ShipVia"=> "09",
            "ShipWeight"=> "00018",
            "CustomerPONo"=> "Hold-Card",
            "WarehouseCode"=> "000",
            "ConfirmTo"=> "DAVID SMITH",
            "Comment"=> "",
            "TermsCode"=> "00",
            "TaxSchedule"=> "AVATAX",
            "TaxExemptNo"=> "",
            "InvalidTaxCalc"=> "N",
            "PrintSalesOrders"=> "N",
            "PrintPickingSheets"=> "Y",
            "BatchFax"=> "N",
            "BatchEmail"=> "N",
            "EmailAddress"=> "bobcat@inyodomartialarts.com",
            "FreightCalculationMethod"=> "A",
            "LotSerialLinesExist"=> "N",
            "SalespersonDivisionNo"=> "10",
            "SalespersonNo"=> "HN",
            "SplitCommissions"=> "N",
            "PaymentTypeCategory"=> "D",
            "ResidentialAddress"=> "Y",
            "TaxableSubjectToDiscount"=> "280.69",
            "NonTaxableSubjectToDiscount"=> ".00",
            "TaxSubjToDiscPrcntOfTotSubjTo"=> "100.00",
            "DiscountRate"=> ".000",
            "DiscountAmt"=> ".00",
            "TaxableAmt"=> "408.49",
            "NonTaxableAmt"=> ".00",
            "SalesTaxAmt"=> "20.02",
            "Weight"=> "18.00",
            "FreightAmt"=> ".00",
            "DepositAmt"=> ".00",
            "CommissionRate"=> ".000",
            "SplitCommRate2"=> ".000",
            "SplitCommRate3"=> ".000",
            "SplitCommRate4"=> ".000",
            "SplitCommRate5"=> ".000",
            "NumberOfShippingLabels" => "0",
            "LastNoOfShippingLabels" =>  "1",
            "DateCreated"=> "",
            "TimeCreated"=> "16.8521",
            "UserCreatedKey" => "0000000224",
            "DateUpdated" => "",
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
        CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $this->messageManager->addNoticeMessage(__($response . "M" . $orderNumber));
        //sage
    }
}
