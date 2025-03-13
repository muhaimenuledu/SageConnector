<?php

namespace Harriswebworks\SageConnector\Controller\Adminhtml\SageConnector;

use Magento\Framework\App\Config\ScopeConfigInterface;

class AllOrderSync extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;
    protected $resultPageFactory;
    protected $_objectManager;
    protected $_orderCollectionFactory;
    protected $orderRepository;
    protected $_scopeConfig;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->_objectManager = $objectManager;
        $this->messageManager = $messageManager;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->orderRepository = $orderRepository;
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * Execute method to sync all orders
     */
    public function execute()
    {
        $collection = $this->_orderCollectionFactory->create()->addAttributeToSelect('*');

        foreach ($collection as $order) {
            $orderId = $order->getId();
            $customerEmail = $order->getCustomerEmail();
            $customerName = $order->getCustomerName();
            $dateCreate = $order->getCreatedAt();

            $params = [];
            foreach ($order->getAllVisibleItems() as $_item) {
                $sku = $_item->getSku();
                $qty = $_item->getQtyOrdered();
                $item = $_item->getItemId();

                $params[] = [
                    0,
                    0,
                    [
                        "default_code" => $sku,
                        "product_uom_qty" => $qty
                    ]
                ];
            }

            $data = json_encode($params, JSON_PRETTY_PRINT);

            $this->customerSync($customerName, $customerEmail, $orderId, $data, $dateCreate);
        }

        $this->messageManager->addSuccessMessage(__('All Order Data synced with Sage successfully.'));
        $this->_redirect('sales/order/index');
    }

    /**
     * Sync customer data to Sage
     */
    protected function customerSync($name, $email, $orderId, $data, $dateCreate)
    {
        $isEnabled = $this->_scopeConfig->getValue('hww_sageconnector/general/enable', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $url = $this->_scopeConfig->getValue('hww_sageconnector/general/url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if (!$isEnabled || !$url) {
            $this->messageManager->addErrorMessage(__('Sage connector is not enabled or URL is missing.'));
            return;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url . '/customerSyncAPI/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([
                "params" => [
                    "name" => $name,
                    "email" => $email
                ]
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $this->orderSync($email, $orderId, $data, $dateCreate);
    }

    /**
     * Sync order data to Sage
     */
    protected function orderSync($customerEmail, $orderId, $data, $dateCreate)
    {
        $isEnabled = $this->_scopeConfig->getValue('hww_sageconnector/general/enable', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $url = $this->_scopeConfig->getValue('hww_sageconnector/general/url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if (!$isEnabled || !$url) {
            $this->messageManager->addErrorMessage(__('Sage connector is not enabled or URL is missing.'));
            return;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url . '/orderSyncAPI/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([
                "params" => [
                    "customer_email" => $customerEmail,
                    "state" => "sale",
                    "magento_order_id" => $orderId,
                    "magento_order_date" => $dateCreate,
                    "order_line" => json_decode($data, true)
                ]
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
    }
}
