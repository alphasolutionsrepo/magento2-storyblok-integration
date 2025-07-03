<?php
namespace MediaLounge\Storyblok\Block\Container;

//use Storyblok\RichtextRender\Resolver;
use Magento\Framework\View\Element\Template\Context;
//use Storyblok\RichtextRender\ResolverFactory as StoryblokResolver;
use Tiptap\Editor;
use Storyblok\Tiptap\Extension\Storyblok;
use Psr\Log\LoggerInterface;


class Element extends \Magento\Framework\View\Element\Template
{
     private LoggerInterface $logger;
    /**
     * @var Editor
     */
    private $editor;

    public function __construct(
        //StoryblokResolver $storyblokResolver,
        Context $context,
        LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);

        //$this->storyblokResolver = $storyblokResolver->create();
        $this->editor = new Editor(['extensions' => [new Storyblok(),],]);
        $this->logger = $logger;
    }

    protected function _toHtml(): string
    {
        $editable = $this->getData('_editable') ?? '';

        return $editable . parent::_toHtml();
    }

    public function renderWysiwyg(array $arrContent): string
    {
        $this->editor->setContent($arrContent);
        $html = $this->editor->getHTML();

        return $html;
    }

    public function transformImage(string $image, string $param = ''): string
    {
        $imageService = '//img2.storyblok.com/';
        $resource = preg_replace('/(https?:)?\/\/a.storyblok.com/', '', $image);

        return $imageService . $param . $resource;
    }

    public function __call($method, $args)
    {
        // check for minimum length of 7 ('get' and 'html')
        if (!strlen($method) > 7) {
            return parent::__call($method, $args);
        }
        $start = substr($method, 0, 3);
        $end = substr($method, -4);
        if ($start === 'get' && $end === 'Html') {
            $key = strtolower(substr($method, 3, -4));
            return $this->getStoryBlockChilds($key);
        }
        return parent::__call($method, $args);
    }

    protected function getStoryBlockChilds(string $key): ?string
    {
        $data = $this->getData($key);
        if (!$data) {
            return null;
        }

        $name = $this->getNameInLayout();
        $namePrefix = substr($name, 0, strrpos($name, '_') + 1);

        $html = '';
        foreach ($data as $row) {
            $html .= $this->getChildHtml($namePrefix . $row['_uid']);
        }
        return $html;
    }
}
