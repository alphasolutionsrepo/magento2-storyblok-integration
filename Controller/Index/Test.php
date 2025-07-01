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

        $baseUri = $this->scopeConfig->getValue(
            'storyblok/general/api_path',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );

        $this->logger->debug('MediaLounge\Storyblok\Controller\Index\Test::$baseUri: ' . $baseUri);

        $token = $this->scopeConfig->getValue(
            'storyblok/general/access_token',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );

        $this->logger->debug('MediaLounge\Storyblok\Controller\Index\Test::$token: ' . $token);

        $timeout = $this->scopeConfig->getValue(
            'storyblok/general/timeout',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );

        $this->logger->debug('MediaLounge\Storyblok\Controller\Index\Test::$timeout: ' . $timeout);
        
        $storyblokClient = new StoryblokClient(
            $baseUri,
            $token,
            $timeout
        );
        


        /** @var Page $resultPage */
        $resultPage = $this->pageFactory->create();

        // 'my.block.alias' must match the `name=` in your layout XML
        $block = $resultPage->getLayout()->getBlock('MediaLounge.test.block');


        $spacesApi = new SpacesApi($storyblokClient);
        $response = $spacesApi->me();
        $block->setData('myValue', json_encode($response));

        return $resultPage;
    }
}
