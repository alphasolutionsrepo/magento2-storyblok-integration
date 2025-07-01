<?php
namespace MediaLounge\Storyblok\Block;

use Magento\Framework\View\FileSystem;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\View\Element\AbstractBlock;
use MediaLounge\Storyblok\Block\Container\Element;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Storyblok\Api\StoryblokClient;
use Storyblok\Api\StoryblokClientInterface;
use Storyblok\Api\StoriesApi;
use Psr\Log\LoggerInterface;

class Container extends \Magento\Framework\View\Element\Template implements IdentityInterface
{

    private LoggerInterface $logger;

    /**
     * @var StoryblokClient
     */
    protected StoryblokClientInterface $storyblokClient;

    /** @var ScopeConfigInterface */
    protected ScopeConfigInterface $scopeConfig;

    /** @var StoreManagerInterface */
    protected StoreManagerInterface $storeManager;

    /**
     * @var FileSystem
     */
    private $viewFileSystem;

    public function __construct(
        LoggerInterface $logger,
        FileSystem $viewFileSystem,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->logger = $logger;
        $this->viewFileSystem = $viewFileSystem;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;

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
    }

    public function getCacheLifetime()
    {
        return parent::getCacheLifetime() ?: 3600;
    }

    public function getIdentities(): array
    {
        if (!empty($this->getSlug())) {
            return ["storyblok_slug_{$this->getSlug()}"];
        } elseif (!empty($this->getData('story')['id'])) {
            return ["storyblok_{$this->getData('story')['id']}"];
        }

        return [];
    }

    public function getCacheKeyInfo(): array
    {
        $info = parent::getCacheKeyInfo();

        if (!empty($this->getData('story')['id'])) {
            $info[] = "storyblok_{$this->getData('story')['id']}";
        } elseif (!empty($this->getSlug())) {
            $info[] = "storyblok_slug_{$this->getSlug()}";
        }

        return $info;
    }

    private function getStory(): array
    {

        $slug = $this->getData('slug') ?: $this->getRequest()->getParam('slug');
        $this->logger->debug('MediaLounge\Storyblok\Blok\Container::getStory()::Start::slug=' . $slug);
        if (empty($slug)) {
            $this->logger->debug('MediaLounge\Storyblok\Blok\Container::getStory()::Start::slug=EMPTY');
            return [];
        }

        if (!$this->getData('story')) {
            try {
                $storiesApi = new StoriesApi($this->storyblokClient);
                $this->logger->debug('MediaLounge\Storyblok\Blok\Container::getStory()::Start');
                $data = $storiesApi->bySlug($slug);
                $this->logger->debug('MediaLounge\Storyblok\Blok\Container::getStory()::$data' . json_encode($data));
                $this->setData('story', $data->story);
            } catch (ApiException $e) {
                return [];
            }
        }

        $this->logger->debug('MediaLounge\Storyblok\Blok\Container::getStory()::END');
        return $this->getData('story');
    }

    private function isArrayOfBlocks(array $data): bool
    {
        return count($data) !== count($data, COUNT_RECURSIVE);
    }

    private function createBlockFromData(array $blockData): Element
    {
        $block = $this->getLayout()
            ->createBlock(
                Element::class,
                $this->getNameInLayout()
                    ? $this->getNameInLayout() . '_' . $blockData['_uid']
                    : $blockData['_uid']
            )
            ->setData($blockData);

        $templatePath = $this->viewFileSystem->getTemplateFileName(
            "MediaLounge_Storyblok::story/{$blockData['component']}.phtml"
        );

        if ($templatePath) {
            $block->setTemplate("MediaLounge_Storyblok::story/{$blockData['component']}.phtml");
        } else {
            $block->setTemplate('MediaLounge_Storyblok::story/debug.phtml')->addData([
                'original_template' => "MediaLounge_Storyblok::story/{$blockData['component']}.phtml"
            ]);
        }

        $this->appendChildBlocks($block, $blockData);

        return $block;
    }

    private function appendChildBlocks(AbstractBlock $parentBlock, array $blockData): void
    {
        foreach ($blockData as $data) {
            if (is_array($data) && $this->isArrayOfBlocks($data)) {
                foreach ($data as $childData) {
                    // Ignore if rich text editor block
                    if (empty($childData['_uid'])) {
                        continue;
                    }

                    $childBlock = $this->createBlockFromData($childData);

                    $parentBlock->append($childBlock);
                }
            }
        }
    }

    protected function _toHtml(): string
    {
        $this->logger->debug('MediaLounge\Storyblok\Blok\Container::Start');
        $storyData = $this->getStory();
        $this->logger->debug('MediaLounge\Storyblok\Blok\Container::$storyData: ' . json_encode($storyData));

        if ($storyData) {
            $blockData = $storyData['content'] ?? [];
            $parentBlock = $this->createBlockFromData($blockData);

            return $parentBlock->toHtml();
        }

        return '';
    }
}
