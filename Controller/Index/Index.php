<?php
namespace MediaLounge\Storyblok\Controller\Index;

use Magento\Framework\View\Result\Page;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Psr\Log\LoggerInterface;

class Index extends Action implements HttpGetActionInterface
{

    private LoggerInterface $logger;

    /**
     * @var PageFactory
     */
    private $pageFactory;

    public function __construct(Context $context, PageFactory $pageFactory, LoggerInterface $logger)
    {
        parent::__construct($context);

        $this->pageFactory = $pageFactory;
        $this->logger = $logger;
    }

    public function execute(): ResultInterface
    {
        $story = $this->getRequest()->getParam('story', null);

        $this->logger->debug('MediaLounge\Storyblok\Controller\Index:: execute():: $story=' . json_encode($story));

        if (!$story) {
            throw new NotFoundException(__('Story parameter is missing.'));
        }

        /** @var Page $resultPage */
        $resultPage = $this->pageFactory->create();
        $resultPage = $this->setMetaFields($resultPage, $story);

        $resultPage
            ->getLayout()
            ->getBlock('storyblok.page')
            ->setStory($story);

        $this->logger->debug('MediaLounge\Storyblok\Controller\Index:: execute():: $resultPage=' . json_encode($resultPage));

        return $resultPage;
    }

    private function setMetaFields(Page $resultPage, array $story)
    {
        $metaTitle = '';
        $metaDescription = '';

        foreach ($story['content'] as $data) {
            if (is_array($data) && $this->isMetaFieldsBlock($data)) {
                $metaTitle = $data['title'];
                $metaDescription = $data['description'];
            }
        }

        if ($metaTitle) {
            $resultPage
                ->getConfig()
                ->getTitle()
                ->set($metaTitle);
        } else {
            $resultPage
                ->getConfig()
                ->getTitle()
                ->set($story['name']);
        }

        if ($metaDescription) {
            $resultPage->getConfig()->setDescription($metaDescription);
        }

        return $resultPage;
    }

    private function isMetaFieldsBlock(array $data)
    {
        return !empty($data['plugin']) && $data['plugin'] === 'meta-fields';
    }
}
