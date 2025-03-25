<?php

namespace Harriswebworks\SageConnector\Controller\Adminhtml\Sageconnector;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class ProductSync extends Action
{
    protected $jsonFactory;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $apiUrl = 'https://52.186.11.198:88/product/export.php';

            // Initialize cURL
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYPEER => false, // Disable SSL certificate validation
                CURLOPT_SSL_VERIFYHOST => false, // Disable host validation
                CURLOPT_CUSTOMREQUEST => 'GET',
            ]);

            $response = curl_exec($curl);

            $this->messageManager->addNoticeMessage(__($response));

            if (curl_errno($curl)) {
                throw new \Exception('Curl error: ' . curl_error($curl));
            }

            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            // $result = $this->jsonFactory->create();

            // if ($httpCode == 200) {
            //     return $result->setData([
            //         'success' => true,
            //         'message' => 'API called successfully',
            //         'data' => json_decode($response, true)
            //     ]);
            // } else {
            //     return $result->setData([
            //         'success' => false,
            //         'message' => 'Failed to call API',
            //         'data' => json_decode($response, true)
            //     ]);
            // }
        } 
        catch (\Exception $e) {
            return $this->jsonFactory->create()->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        // return $result;
        // $this->messageMananger->addNoticeMessage($result);
        return $this->_redirect($this->_redirect->getRefererUrl());
    }
}
