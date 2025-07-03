<?php
namespace MediaLounge\Storyblok\Controller;

use Storyblok\Api\StoryblokClient;
use Storyblok\Api\StoryblokClientInterface;
use Storyblok\Api\StoriesApi;
use Storyblok\Api\Domain\Value\Dto\Version;
use Storyblok\Api\Request\StoryRequest;
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
use Psr\Log\LoggerInterface;
use Storyblok\Api\Exception\ApiException;

class Router implements RouterInterface
{
    private LoggerInterface $logger;

    /**
     * @var ActionFactory
     */
    private $actionFactory;

    /**
     * @var StoryblokClient
     */
    protected StoryblokClientInterface $storyblokClient;

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
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        ActionFactory $actionFactory,
        ScopeConfigInterface $scopeConfig,
        CacheInterface $cache,
        SerializerInterface $serializer,
        StoreManagerInterface $storeManager,
            LoggerInterface $logger
    ) {
        $this->actionFactory = $actionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->logger = $logger;

        $baseUri = $this->scopeConfig->getValue(
            'storyblok/general/api_path',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );

        $token = $this->scopeConfig->getValue(
            'storyblok/general/access_token',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );

        $timeout = $this->scopeConfig->getValue(
            'storyblok/general/timeout',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );
    
        $this->storyblokClient = new StoryblokClient(
            $baseUri,
            $token,
            $timeout
        );    
        $this->cache = $cache;
        $this->serializer = $serializer;
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        //$identifier = trim($request->getPathInfo(), '/');
        $paramStoryblok = $request->getParam('_storyblok');
        $originalPathInfo = trim($request->getOriginalPathInfo(), '/');
        $requestUri = trim($request->getRequestUri(), '/');
        $identifier = trim($request->getOriginalPathInfo(), '/');
        $this->logger->debug('MediaLounge\Storyblok\Controller\Router::match(): $identifier=' . $identifier);
        $this->logger->debug('MediaLounge\Storyblok\Controller\Router::match(): getParam(_storyblok)=' . $paramStoryblok);
        $this->logger->debug('MediaLounge\Storyblok\Controller\Router::match(): $originalPathInfo=' . $originalPathInfo);
        $this->logger->debug('MediaLounge\Storyblok\Controller\Router::match(): $requestUri=' . $requestUri);
        
        if (empty($identifier)) {
            $this->logger->debug('MediaLounge\Storyblok\Controller\Router::match()::Start::$identifier=EMPTY');
            return [];
        }

        try {
            $data = $this->cache->load($identifier);
            $this->logger->debug('MediaLounge\Storyblok\Controller\Router::match(): CACHED $data=' . json_encode($data));

            if (!$data || $paramStoryblok) {
                $storiesApi = new StoriesApi($this->storyblokClient, 'draft');
                $response = $storiesApi->bySlug($identifier, new StoryRequest(language: 'en'));

                $data = $this->serializer->serialize($response);
                $this->logger->debug('MediaLounge\Storyblok\Controller\Router::match()::serializer::$data=' . json_encode($data));

                if (!$paramStoryblok && !empty($response->story))
                {
                    $this->logger->debug('MediaLounge\Storyblok\Controller\Router::match()::CACHE->save=' . "storyblok_{$response->story['id']}");

                    $this->cache->save($data, $identifier, [
                        "storyblok_{$response->story['id']}"
                    ]);
                }
            }

            $data = $this->serializer->unserialize($data);
//            $this->logger->debug('MediaLounge\Storyblok\Controller\Router::match()::UNserialize::$data=' . json_encode($data));

            $this->logger->debug('MediaLounge\Storyblok\Controller\Router::match()::$data->story=' . json_encode($data['story']));


            if (!empty($data['story'])) {
                $request
                    ->setModuleName('storyblok')
                    ->setControllerName('index')
                    ->setParams([
                        'story' => $data['story']
                    ]);

                $this->logger->debug('MediaLounge\Storyblok\Controller\Router::match()::forward to storyblok Controller');

                return $this->actionFactory->create(Forward::class, ['request' => $request]);
            }
        } catch (ApiException $e) {
            $this->logger->debug('MediaLounge\Storyblok\Controller\Router::match(): ApiException $data=' . $e->getMessage() );
            return null;
        } catch (\Throwable $e) {
            $this->logger->error('MediaLounge\Storyblok\Controller\Router::match(): Unhandled Exception: ' . $e->getMessage());
            return null;
        }
        return null;
    }
}
