<?php
namespace MediaLounge\Storyblok\Model\ItemProvider;

use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;
use Magento\Sitemap\Model\ItemProvider\ConfigReaderInterface;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use Storyblok\Api\StoryblokClient;
use Storyblok\Api\StoryblokClientInterface;

class Story implements ItemProviderInterface
{
    const STORIES_PER_PAGE = 100;

    /**
     * @var SitemapItemInterfaceFactory
     */
    private $itemFactory;

    /**
     * @var ConfigReaderInterface
     */
    private $configReader;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;
    
    /** @var ScopeConfigInterface */
    protected ScopeConfigInterface $scopeConfig;
    
    /**
     * @var StoryblokClient
     */
    private $storyblokClient;

    public function __construct(
        ConfigReaderInterface $configReader,
        SitemapItemInterfaceFactory $itemFactory,
        ScopeConfigInterface $scopeConfig,
        StoryblokClientInterface $storyblokClient,
        StoreManagerInterface $storeManager
    ) {
        $this->itemFactory = $itemFactory;
        $this->configReader = $configReader;
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
            baseUri: $apipath,
            token: $accesstoken,
            timeout: $timeout 
        );
    }

    public function getItems($storeId)
    {
        $response = $this->getStories();
        $stories = $response->getBody()['stories'];

        $totalPages = $response->getHeaders()['Total'][0] / self::STORIES_PER_PAGE;
        $totalPages = ceil($totalPages);

        if ($totalPages > 1) {
            $paginatedStories = [];

            for ($page = 2; $page <= $totalPages; $page++) {
                $pageResponse = $this->getStories($page);
                $paginatedStories = $pageResponse->getBody()['stories'];
            }

            $stories = array_merge($stories, $paginatedStories);
        }

        $items = array_map(function ($item) use ($storeId) {
            return $this->itemFactory->create([
                'url' => $item['full_slug'],
                'updatedAt' => $item['published_at'],
                'priority' => $this->configReader->getPriority($storeId),
                'changeFrequency' => $this->configReader->getChangeFrequency($storeId)
            ]);
        }, $stories);

        return $items;
    }

    private function getStories(int $page = 1): \Storyblok\Client
    {
        $response = $this->storyblokClient->getStories([
            'page' => $page,
            'per_page' => self::STORIES_PER_PAGE,
            'filter_query[component][like]' => 'page'
        ]);

        return $response;
    }
}
