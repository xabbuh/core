<?php

/**
 * Copyright Zikula Foundation 2014 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

namespace Zikula\Bundle\CoreBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Strip the Front Controller (index.php) from the URI
 */
class StripFrontControllerListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array(
                array('onKernelRequest', 1023),
            )
        );
    }

    /**
     * Strip the Front Controller (index.php) from the URI
     *
     * @param GetResponseEvent $event An GetResponseEvent instance
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $requestUri = $event->getRequest()->getRequestUri();
        $frontController = \System::getVar('entrypoint', 'index.php');
        $stripEntryPoint = (bool) \System::getVar('shorturlsstripentrypoint', false);
        $containsFrontController = (strpos($requestUri, "$frontController/") !== false);

        if ($containsFrontController && $stripEntryPoint) {
            $url = str_ireplace("$frontController/", "", $requestUri);
            $response = new RedirectResponse($url, 301);
            $event->setResponse($response);
            $event->stopPropagation();
        }
    }
}
