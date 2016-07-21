<?php
/**
 * Extension by Webinse  http://www.webinse.com/
 * If you need more information
 * or some tecnical issues occured please contacy via this e mail: info@webinse.com
 */
require_once 'Mage/Checkout/controllers/CartController.php';

class Webinse_Ajaxcart_IndexController extends Mage_Checkout_CartController {
    /**
     * @return Mage_Core_Controller_Varien_Action
     * Add action with ajax
     */
    public function addAction() {
        $response = array();
        $a = Mage::getVersion();
        $response['cart_url'] = Mage::getUrl('checkout/cart');
        $response['redirect_status'] = Mage::getStoreConfig('ajaxcart/all_settings/checkout_cart_redirect');
        $response['redirect_timeout'] = Mage::getStoreConfig('ajaxcart/all_settings/checkout_cart_redirect_timeout');
        $response['show_pop_up'] = Mage::getStoreConfig('ajaxcart/all_settings/show_pop_up_after_add');

        $cart = $this->_getCart();

        $params = $this->getRequest()->getParams();
        try {
            if(isset($params['qty'])) {
                $filter = new Zend_Filter_LocalizedToNormalized(
                    array('locale' => Mage::app()->getLocale()->getLocaleCode())
                );
                $params['qty'] = $filter->filter($params['qty']);
            }

            $session = $this->_getSession();
            //if button not contains id in url - load id by url
            if(array_key_exists('url', $params)) {
                $productUrl = str_replace(Mage::getBaseUrl(), "", $params['url']);
                $productId = Mage::getModel('core/url_rewrite')->setStoreId(Mage::app()->getStore()->getId())->loadByRequestPath($productUrl)->getProductId();
                $product = Mage::getModel('catalog/product')->load($productId);
            } elseif($wishlistItem = (int)$this->getRequest()->getParam('item')) { //if press button add to cart from sidebar wishlist
                $item = Mage::getModel('wishlist/item')->load($wishlistItem);
                $product = Mage::getModel('catalog/product')->load($item->getProductId());

                //Add to session wishlist id. It be useful in future if product has options
                $session->setWishlistItem($wishlistItem);
            } else {
                $product = $this->_initProduct();
            }

            /**
             *  If product Type_Grouped create another response, but if request from option icon
             *  not redirect twice
             */
            if($product->getTypeInstance(true) instanceof Mage_Catalog_Model_Product_Type_Grouped){
                if(!array_key_exists('ajaxAdd', $params)) {
                    $url = Mage::getUrl('*/*/options') . 'product_id/' . $product->getEntityId();
                    $response['status'] = 'SUCCESS';
                    $response['options_url'] = $url;
                    $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
                    return;
                }
            }
            /**
             *  If product have options create another response, but if request from option icon
             *  not redirect twice
             */
            if($product->getTypeInstance(true)->hasOptions($product) && !array_key_exists('qty', $params)) {
                $url = Mage::getUrl('*/*/options') . 'product_id/' . $product->getEntityId();
                $response['status'] = 'SUCCESS';
                $response['options_url'] = $url;
                $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
                return;
            }

            $related = $this->getRequest()->getParam('related_product');

            /**
             * Check product availability
             */
            if(!$product) {
                $response['status'] = 'ERROR';
                $response['message'] = $this->__('Unable to find Product ID');
            }

            $cart->addProduct($product, $params);
            if(!empty($related)) {
                $cart->addProductsByIds(explode(',', $related));
            }

//            $cart->save();
//            $session->setCartWasUpdated(true);

            /**
             * @todo remove wishlist observer processAddToCart
             */
            Mage::dispatchEvent('checkout_cart_add_product_complete',
                array('product' => $product, 'request' => $this->getRequest(), 'response' => $this->getResponse())
            );

            if(!$cart->getQuote()->getHasError()) {

                $cart->save();
                $session->setCartWasUpdated(true);

                $message = $this->__('%s was added to your shopping cart.', Mage::helper('core')->escapeHtml($product->getName()));
                $response['status'] = 'SUCCESS';
                $response['message'] = $message;

                $this->loadLayout();
                $toplink = $this->getLayout()->getBlock('top.links')->toHtml();
                $sidebar_block = $this->getLayout()->getBlock('cart_sidebar');

                //check if product was added from wishlist
                if($wishlistItem = $session->getWishlistItem()) {
                    $item = Mage::getModel('wishlist/item')->load($wishlistItem);
                    $wishlist = $this->_getWishlist($item->getWishlistId());
                    $item->delete();
                    $wishlist->save();
                }

                if($this->_getWishlist()) {
                    $wishlist_sidebar = $this->getLayout()->getBlock('wishlist_sidebar');
                    $wishlistHtml = $wishlist_sidebar->toHtml();
                    $response['wishlist'] = $wishlistHtml;
                } else {
                    $response['wishlist'] = '<div class="block block-wishlist" style="display: none"></div>';
                }

                Mage::register('referrer_url', $this->_getRefererUrl());
                if($sidebar_block) {
                    $sidebar = $sidebar_block->toHtml();
                    $response['sidebar'] = $sidebar;
                }

                //check if theme contains minicart block
                if($minicart = $this->getMinicartBlock()) {
                    $response['minicart'] = $minicart;
                }
                $response['toplink'] = $toplink;

                //Create choice block
                Mage::register('current_product', $product);
                $response['productinfo'] = $this->getLayout()->createBlock('core/template')->setTemplate('ajaxcart/ajaxcart.phtml')->toHtml();
            }
            else {
                $response['status'] = 'ERROR';
                $response['message'] = $this->
                    __('Check your cart. The requested quantity for some added products is not available in your cart.');
                $response['show_pop_up'] = 0;
                $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
                return;
            }
        } catch(Mage_Core_Exception $e) {
            $msg = "";
            if($session->getUseNotice(true)) {
                $msg = $e->getMessage();
            } else {
                $messages = array_unique(explode("\n", $e->getMessage()));
                foreach($messages as $message) {
                    $msg .= $message . "\n";
                }
            }

            $response['status'] = 'ERROR';
            $response['message'] = $msg;
        } catch(Exception $e) {
            $response['status'] = 'ERROR';
            $response['message'] = $this->__('Cannot add the item to shopping cart.');
            $response['message'] = $e->getMessage();
            Mage::logException($e);
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
        return;
    }

    public function optionsAction() {
        $productId = $this->getRequest()->getParam('product_id');
        // Prepare helper and params
        $viewHelper = Mage::helper('catalog/product_view');

        $params = new Varien_Object();
        $params->setCategoryId(false);
        $params->setSpecifyOptions(false);

        // Render page
        try {
            $viewHelper->prepareAndRender($productId, $this, $params);
        } catch(Exception $e) {
            if($e->getCode() == $viewHelper->ERR_NO_PRODUCT_LOADED) {
                if(isset($_GET['store']) && !$this->getResponse()->isRedirect()) {
                    $this->_redirect('');
                } elseif(!$this->getResponse()->isRedirect()) {
                    $this->_forward('noRoute');
                }
            } else {
                Mage::logException($e);
                $this->_forward('noRoute');
            }
        }
    }

    public function compareAction() {
        $response = array();

        if($productId = (int)$this->getRequest()->getParam('product')) {
            $product = Mage::getModel('catalog/product')
                ->setStoreId(Mage::app()->getStore()->getId())
                ->load($productId);

            if($product->getId()) {
                Mage::getSingleton('catalog/product_compare_list')->addProduct($product);
                $response['status'] = 'SUCCESS';
                $response['message'] = $this->__('The product %s has been added to comparison list.', Mage::helper('core')->escapeHtml($product->getName()));
                Mage::register('referrer_url', $this->_getRefererUrl());
                Mage::helper('catalog/product_compare')->calculate();
                Mage::dispatchEvent('catalog_product_compare_add_product', array('product' => $product));
                $this->loadLayout();
                $sidebar_block = $this->getLayout()->getBlock('catalog.compare.sidebar');
                $sidebar = $sidebar_block->toHtml();
                $response['sidebar'] = $sidebar;
            }
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
        return;
    }

    public function compareRemoveAction() {
        $response = array();

        if($productId = $this->getRequest()->getParam('id')) {
            $response['status'] = 'SUCCESS';
            if($this->getRequest()->getParam('id') > 0) {
                $product = Mage::getModel('catalog/product')
                    ->setStoreId(Mage::app()->getStore()->getId())
                    ->load($productId);
                if($product->getId()) {
                    Mage::getSingleton('catalog/product_compare_list')->removeProduct($product);
                    Mage::helper('catalog/product_compare')->calculate();
                    Mage::dispatchEvent('catalog_product_compare_add_product', array('product' => $product));
                    $response['message'] = $this->__('The product %s has been removed from comparison list.', Mage::helper('core')->escapeHtml($product->getName()));
                }
            } else {
                $items = Mage::getResourceModel('catalog/product_compare_item_collection');
                if(Mage::getSingleton('customer/session')->isLoggedIn()) {
                    $items->setCustomerId(Mage::getSingleton('customer/session')->getCustomerId());
                } else {
                    $items->setVisitorId(Mage::getSingleton('log/visitor')->getId());
                }
                try {
                    $items->clear();
                    Mage::helper('catalog/product_compare')->calculate();
                    $response['message'] = $this->__('The products has been removed from comparison list.');
                } catch(Mage_Core_Exception $e) {
                    $response['status'] = 'ERROR';
                    $response['message'] = $e->getMessage();
                } catch(Exception $e) {
                    $response['status'] = 'ERROR';
                    $response['message'] = $e;
                }
            }
            Mage::register('referrer_url', $this->_getRefererUrl());
            $this->loadLayout();
            $sidebar_block = $this->getLayout()->getBlock('catalog.compare.sidebar');
            $sidebar = $sidebar_block->toHtml();
            $response['sidebar'] = $sidebar;
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
        return;
    }

    protected function _getWishlist() {
        $wishlist = Mage::registry('wishlist');
        if($wishlist) {
            return $wishlist;
        }

        try {
            if($customerId = Mage::getSingleton('customer/session')->getCustomerId()) {
                $wishlist = Mage::getModel('wishlist/wishlist')->loadByCustomer($customerId, true);
                Mage::register('wishlist', $wishlist);
            }
        } catch(Mage_Core_Exception $e) {
            Mage::getSingleton('wishlist/session')->addError($e->getMessage());
        } catch(Exception $e) {
            Mage::getSingleton('wishlist/session')->addException($e,
                Mage::helper('wishlist')->__('Cannot create wishlist.')
            );
            return false;
        }

        return $wishlist;
    }

    public function addWishListAction() {

        $response = array();
        if(!Mage::getStoreConfigFlag('wishlist/general/active')) {
            $response['status'] = 'ERROR';
            $response['message'] = $this->__('Wishlist Has Been Disabled By Admin');
        }
        if(!Mage::getSingleton('customer/session')->isLoggedIn()) {
            $response['status'] = 'ERROR';
            $response['message'] = $this->__('Please Login First');
        }

        if(empty($response)) {
            $wishlist = $this->_getWishlist();
            if(!$wishlist) {
                $response['status'] = 'ERROR';
                $response['message'] = $this->__('Unable to Create Wishlist');
            } else {

                $productId = (int)$this->getRequest()->getParam('product');
                if(!$productId) {
                    $response['status'] = 'ERROR';
                    $response['message'] = $this->__('Product Not Found');
                } else {

                    $product = Mage::getModel('catalog/product')->load($productId);
                    if(!$product->getId() || !$product->isVisibleInCatalog()) {
                        $response['status'] = 'ERROR';
                        $response['message'] = $this->__('Cannot specify product.');
                    } else {

                        try {
                            $requestParams = $this->getRequest()->getParams();
                            $buyRequest = new Varien_Object($requestParams);

                            $result = $wishlist->addNewItem($product, $buyRequest);
                            if(is_string($result)) {
                                Mage::throwException($result);
                            }
                            $wishlist->save();

                            Mage::dispatchEvent(
                                'wishlist_add_product',
                                array(
                                    'wishlist' => $wishlist,
                                    'product' => $product,
                                    'item' => $result
                                )
                            );

                            Mage::helper('wishlist')->calculate();

                            $message = $this->__('%1$s has been added to your wishlist.', $product->getName());
                            $response['status'] = 'SUCCESS';
                            $response['message'] = $message;

                            Mage::unregister('wishlist');

                            $this->loadLayout();
                            $toplink = $this->getLayout()->getBlock('top.links')->toHtml();
                            $sidebar_block = $this->getLayout()->getBlock('wishlist_sidebar');
                            $sidebar = $sidebar_block->toHtml();
                            $response['toplink'] = $toplink;
                            $response['wishlist'] = $sidebar;
                        } catch(Mage_Core_Exception $e) {
                            $response['status'] = 'ERROR';
                            $response['message'] = $this->__('An error occurred while adding item to wishlist: %s', $e->getMessage());
                        } catch(Exception $e) {
                            mage::log($e->getMessage());
                            $response['status'] = 'ERROR';
                            $response['message'] = $this->__('An error occurred while adding item to wishlist.');
                        }
                    }
                }
            }

        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
        return;
    }

    public function wishlistRemoveAction() {
        $response = array();
        if(!Mage::getStoreConfigFlag('wishlist/general/active')) {
            $response['status'] = 'ERROR';
            $response['message'] = $this->__('Wishlist Has Been Disabled By Admin');
        }
        if(!Mage::getSingleton('customer/session')->isLoggedIn()) {
            $response['status'] = 'ERROR';
            $response['message'] = $this->__('Please Login First');
        }

        if(empty($response)) {
            $wishlist = $this->_getWishlist();
            if(!$wishlist) {
                $response['status'] = 'ERROR';
                $response['message'] = $this->__('Unable to Create Wishlist');
            } else {

                $productId = (int)$this->getRequest()->getParam('item');
                if(!$productId) {
                    $response['status'] = 'ERROR';
                    $response['message'] = $this->__('Product Not Found');
                } else {
                    try {
                        $item = Mage::getModel('wishlist/item')->load($productId);
                        $wishlist = $this->_getWishlist($item->getWishlistId());
                        $item->delete();
                        $wishlist->save();

                        $message = $this->__('Product has been removed from your wishlist.');
                        $response['status'] = 'SUCCESS';
                        $response['message'] = $message;

                        $this->loadLayout();
                        if($wishlist->getItemsCount()) {
                            $sidebar_block = $this->getLayout()->getBlock('wishlist_sidebar');
                            $sidebar = $sidebar_block->toHtml();
                            $response['wishlist'] = $sidebar;
                        } else {
                            $response['wishlist'] = '<div class="block block-wishlist" style="display: none"></div>';
                        }
                        $toplink = $this->getLayout()->getBlock('top.links')->toHtml();
                        $response['toplink'] = $toplink;
                    } catch(Mage_Core_Exception $e) {
                        $response['status'] = 'ERROR';
                        $response['message'] = $this->__('An error occurred while removing item to wishlist: %s', $e->getMessage());
                    } catch(Exception $e) {
                        mage::log($e->getMessage());
                        $response['status'] = 'ERROR';
                        $response['message'] = $this->__('An error occurred while removing item to wishlist.');
                    }
                }
            }
        }
        Mage::helper('wishlist')->calculate();
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
        return;
    }

    /**
     * Method for delete item from cart sidebar and from checkout page
     */
    public function deleteAction() {
        $id = (int)$this->getRequest()->getParam('id');
        $response = array();
        if($id) {
            try {
                $this->_getCart()->removeItem($id)->save();
            } catch(Exception $e) {
                Mage::logException($e);
                $response['status'] = 'ERROR';
                $response['message'] = $this->__('Cannot remove the item.');
            }
        }

        $this->loadLayout();
        $block = $this->getLayout()->getBlock('cart_sidebar')->toHtml();
        $response['sidebar'] = $block;

        //check if theme contains minicart block
        if($minicart = $this->getMinicartBlock()) {
            $response['minicart'] = $minicart;
        } elseif($toplink = $this->getToplinkBlock()) { //check if theme contains top links block
            $response['toplink'] = $toplink;
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    public function deleteCheckoutAction() {
        $id = (int)$this->getRequest()->getParam('id');
        $response = array();
        if($id) {
            try {
                $this->_getCart()->removeItem($id)->save();
            } catch(Exception $e) {
                Mage::logException($e);
                $response['status'] = 'ERROR';
                $response['message'] = $this->__('Cannot remove the item.');
            }
        }

        $this->loadLayout();
        $block = $this->getLayout()->getBlock('checkout.cart')->toHtml();
        $response['checkout'] = $block;
        //check if theme contains minicart block
        if($minicart = $this->getMinicartBlock()) {
            $response['minicart'] = $minicart;
        } elseif($toplink = $this->getToplinkBlock()) { //check if theme contains top links block
            $response['toplink'] = $toplink;
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    public function deleteAllAction() {
        $response = array();
        try {
            Mage::getSingleton('checkout/cart')->truncate()->save();
        } catch(Exception $e) {
            Mage::logException($e);
            $response['status'] = 'ERROR';
            $response['message'] = $this->__('Cannot remove the item.');
        }
        $block = $this->loadLayout()->getLayout()->getBlock('checkout.cart')->toHtml();
        $response['checkout'] = $block;
        //check if theme contains minicart block
        if($minicart = $this->getMinicartBlock()) {
            $response['minicart'] = $minicart;
        } elseif($toplink = $this->getToplinkBlock()) { //check if theme contains top links block
            $response['toplink'] = $toplink;
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    public function updateAction() {
        $id = (int)$this->getRequest()->getParam('id');
        $qty = (float)$this->getRequest()->getParam('qty');
        $response = array();
        if($id) {
            try {
                $item = Mage::getSingleton('checkout/session')->getQuote()->getItemById($id);
                $qtyInStock = $item->getProduct()->getStockItem()->getQty();
                if($qty <= $qtyInStock){
                    $item->setQty($qty)->save();
                }
                else {
                    $response['status'] = 'ERROR';
                    $response['message'] = $this->__( 'Only '.$qtyInStock.' items left in stock.');
                    $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
                    return;
                }
                $superAttribute = $this->getRequest()->getParam('super_attribute');
                foreach($item->getOptions() as $option) {
                    if($option->getCode() == 'info_buyRequest') {
                        $unserialized = unserialize($option->getValue());
                        $unserialized['super_attribute'] = $superAttribute;
                        $option->setValue(serialize($superAttribute));
                    } elseif($option->getCode() == 'attributes') {
                        $option->setValue(serialize($superAttribute));
                    }
                }

                Mage::getSingleton('checkout/cart')->save();

            } catch(Exception $e) {
                Mage::logException($e);
                $response['status'] = 'ERROR';
                $response['message'] = $this->__('Cannot remove the item.');
            }
        }
        $block = $this->loadLayout()->getLayout()->getBlock('checkout.cart')->toHtml();
        $response['checkout'] = $block;
        //check if theme contains minicart block
        if($minicart = $this->getMinicartBlock()) {
            $response['minicart'] = $minicart;
        } elseif($toplink = $this->getToplinkBlock()) { //check if theme contains top links block
            $response['toplink'] = $toplink;
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    /**
     * Create block for rwd template
     */
    private function getMinicartBlock() {
        return ($minicart_block = $this->getLayout()->getBlock('minicart_head')) ? $minicart_block->toHtml() : null;
    }

    private function getToplinkBlock() {
        return ($toplink = $this->getLayout()->getBlock('top.links')) ? $toplink->toHtml() : null;
    }
}
