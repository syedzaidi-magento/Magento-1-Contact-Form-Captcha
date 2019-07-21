<?php
require_once(Mage::getModuleDir('controllers', 'Mage_Contacts').DS.'IndexController.php');
class MageCaptcha_Formcaptcha_IndexController extends Mage_Contacts_IndexController
{

    public function postAction()
    {
        $request = $this->getRequest();
        $post = $request->getPost();
        $customerSession = Mage::getSingleton('customer/session');
        if ($post) {
            $translate = Mage::getSingleton('core/translate');
            $translate->setTranslateInline(false);
            try {
                $postObject = new Varien_Object();
                $postObject->setData($post);
                $error = false;
                if ($this->_zendNEVdate($post['name']) || $this->_zendNEVdate($post['comment'])) {
                    $error = true;
                }

                if (!Zend_Validate::is(trim($post['email']), 'EmailAddress')) {
                    $error = true;
                }

                if (Zend_Validate::is(trim($post['hideit']), 'NotEmpty')) {
                    $error = true;
                }

                $formId = 'contact_us';
                $captchaModel = Mage::helper('captcha')->getCaptcha($formId);
                if ($captchaModel->isRequired()) {
                    if (!$captchaModel->isCorrect($this->_getCaptchaString($request, $formId))) {
                        $customerSession->addError(Mage::helper('captcha')->__('Incorrect CAPTCHA.'));
                        $this->setFlag(”, Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true);
                        $customerSession->setCustomerFormData($post);
                        $this->getResponse()->setRedirect(Mage::getUrl('*/*/'));
                        return;
                    }
                }

                if ($error) {
                    Mage::throwException('error found');
                }

                $tempData = $this->_getTempData();
                $temp = $tempData['temp'];
                $send = $tempData['send'];
                $recp = $tempData['recp'];
                $mailTemplate = Mage::getModel('core/email_template');
                $mailTemplate->setDesignConfig(array('area' => 'frontend'))
                             ->setReplyTo($post['email'])
                             ->sendTransactional($temp, $send, $recp, null, array('data' => $postObject));

                if (!$mailTemplate->getSentSuccess()) {
                    Mage::throwException('error found');
                }

                $translate->setTranslateInline(true);
                $errorText = 'Your inquiry was submitted and will be responded to as soon as possible.';
                $errorText .= 'Thank you for contacting us.';
                $customerSession->addSuccess(Mage::helper('contacts')->__($errorText));
                $this->_redirect('*/*/');
                return;
            } catch (Exception $e) {
                $translate->setTranslateInline(true);
                $errorText = 'Unable to submit your request. Please, try again later';
                $customerSession->addError(Mage::helper('contacts')->__($errorText));
                $this->_redirect('*/*/');
                return;
            }
        } else {
            $this->_redirect('*/*/');
        }
    }

    protected function _getTempData()
    {
        $returnArr = array();
        $returnArr['temp'] = Mage::getStoreConfig(self::XML_PATH_EMAIL_TEMPLATE);
        $returnArr['send'] = Mage::getStoreConfig(self::XML_PATH_EMAIL_SENDER);
        $returnArr['recp'] = Mage::getStoreConfig(self::XML_PATH_EMAIL_RECIPIENT);
        return $returnArr;
    }

    protected function _zendNEVdate($var)
    {
        if (!Zend_Validate::is(trim($var), 'NotEmpty')) {
            return true;
        } else {
            return false;
        }
    }

    protected function _getCaptchaString($request, $formId)
    {
        $captchaParams = $request->getPost(Mage_Captcha_Helper_Data::INPUT_NAME_FIELD_VALUE);
        return $captchaParams[$formId];
    }
}