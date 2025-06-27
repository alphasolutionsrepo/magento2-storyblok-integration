<?php
namespace MediaLounge\Storyblok\Block;

use Magento\Store\Model\ScopeInterface;

class Script extends \Magento\Framework\View\Element\Template
{
    public function getAccessToken(): ?string
    {
        return $this->_scopeConfig->getValue(
            'storyblok/general/access_token',
            ScopeInterface::SCOPE_STORE,
            $this->_storeManager->getStore()->getId()
        );
    }

    protected function _toHtml(): ?string
    {
        if ($this->getAccessToken() && $this->getRequest()->getParam('_storyblok')) {
            return parent::_toHtml();
        }

        return '';
    }
}
