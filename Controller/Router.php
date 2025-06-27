<?php
namespace MediaLounge\Storyblok\Controller;

use Storyblok\Api\StoryblokClient;
use Storyblok\Api\StoryblokClientInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Router implements RouterInterface
{
    /**
     * @var ActionFactory
     */
    private $actionFactory;

    /**
     * @var \Storyblok\Client
     */
    private $storyblokClient;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var StoreManagerInteface
     */
    private $storeManager;

    public function __construct(
        ActionFactory $actionFactory,
        ScopeConfigInterface $scopeConfig,
        StoryblokClient $storyblokClient,
        CacheInterface $cache,
        SerializerInterface $serializer,
        StoreManagerInterface $storeManager
    ) {
        $this->actionFactory = $actionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;

        $apipath = $this->scopeConfig->getValue(
            'storyblok/general/api_path',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );

        $accesstoken = $this->scopeConfig->getValue(
            'storyblok/general/access_token',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );

        $timeout = $this->scopeConfig->getValue(
            'storyblok/general/timeout',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );

       $storyblokClient = new StoryblokClient(
            baseUri: 'https://api-us.storyblok.com/v2/cdn',
            token: '8o0CRKHAtutaXvmQXVY17Qtt',
            timeout: 10 // optional
        );

        /*
        $storyblokClient = new StoryblokClient(
            baseUri: $apipath,
            token: $accesstoken,
            timeout: $timeout // optional
        );
        */
        $this->cache = $cache;
        $this->serializer = $serializer;
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        $identifier = trim($request->getPathInfo(), '/');

        try {
            $data = $this->cache->load($identifier);

            if (!$data || $request->getParam('_storyblok')) {
                $response = $this->storyblokClient->getStoryBySlug($identifier);
                $data = $this->serializer->serialize($response->getBody());

                if (!$request->getParam('_storyblok') && !empty($response->getBody()['story'])) {
                    $this->cache->save($data, $identifier, [
                        "storyblok_{$response->getBody()['story']['id']}"
                    ]);
                }
            }

            $data = $this->serializer->unserialize($data);

            if (!empty($data['story'])) {
                $request
                    ->setModuleName('storyblok')
                    ->setControllerName('index')
                    ->setActionName('index')
                    ->setParams([
                        'story' => $data['story']
                    ]);

                return $this->actionFactory->create(Forward::class, ['request' => $request]);
            }
        } catch (ApiException $e) {
            return null;
        }

        return null;
    }
}
