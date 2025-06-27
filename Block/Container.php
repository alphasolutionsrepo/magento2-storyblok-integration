<?php
namespace MediaLounge\Storyblok\Block;

use Magento\Framework\View\FileSystem;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\View\Element\AbstractBlock;
use MediaLounge\Storyblok\Block\Container\Element;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Storyblok\Api\StoryblokClient;
use Storyblok\Api\StoryblokClientInterface;

class Container extends \Magento\Framework\View\Element\Template implements IdentityInterface
{
    /**
     * @var StoryblokClient
     */
    private $storyblokClient;

    /**
     * @var FileSystem
     */
    private $viewFileSystem;

    public function __construct(
        FileSystem $viewFileSystem,
        StoryblokClient $storyblokClient,
        ScopeConfigInterface $scopeConfig,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->viewFileSystem = $viewFileSystem;

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
        if (!$this->getData('story')) {
            try {
                $storyblokClient = $this->storyblokClient->getStoryBySlug($this->getSlug());
                $data = $storyblokClient->getBody();

                $this->setData('story', $data['story']);
            } catch (ApiException $e) {
                return [];
            }
        }

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
        $storyData = $this->getStory();

        if ($storyData) {
            $blockData = $storyData['content'] ?? [];
            $parentBlock = $this->createBlockFromData($blockData);

            return $parentBlock->toHtml();
        }

        return '';
    }
}
