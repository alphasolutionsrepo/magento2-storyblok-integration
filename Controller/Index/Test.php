<?php
namespace MediaLounge\Storyblok\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Storyblok\Api\SpacesApi;
use Storyblok\Api\StoryblokClient;
use Psr\Log\LoggerInterface;


class Test extends Action
{
    private LoggerInterface $logger;
    
    protected $pageFactory;
    
    /** @var ScopeConfigInterface */
    protected ScopeConfigInterface $scopeConfig;

    /** @var StoreManagerInterface */
    protected StoreManagerInterface $storeManager;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        PageFactory $pageFactory,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ){
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }
    public function execute() {

        $this->logger->debug('MediaLounge\Storyblok\Controller\Index\Test::execute called');
        /** @var Page $resultPage */
        $resultPage = $this->pageFactory->create();

        // 'my.block.alias' must match the `name=` in your layout XML
        $block = $resultPage->getLayout()->getBlock('MediaLounge.test.block');

        $apipath = $this->scopeConfig->getValue(
            'storyblok/general/api_path',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );

        $this->logger->debug('MediaLounge\Storyblok\Controller\Index\Test::$apipath: ' . $apipath);

        $accesstoken = $this->scopeConfig->getValue(
            'storyblok/general/access_token',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );

        $this->logger->debug('MediaLounge\Storyblok\Controller\Index\Test::$accesstoken: ' . $accesstoken);

        $timeout = $this->scopeConfig->getValue(
            'storyblok/general/timeout',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );

        $this->logger->debug('MediaLounge\Storyblok\Controller\Index\Test::$timeout: ' . $timeout);

        $storyblokClient = new StoryblokClient(
            baseUri: $apipath,
            token: $accesstoken,
            timeout: $timeout // optional
        );

        $spacesApi = new SpacesApi($storyblokClient);
        $response = $spacesApi->me();
        $block->setData('myValue', json_encode($response));

        return $resultPage;
    }
}
