<?php

/*
 * This file is part of the Zikula package.
 *
 * Copyright Zikula Foundation - http://zikula.org/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zikula\Bundle\CoreInstallerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Routing\RouterInterface;
use Zikula\Bundle\CoreBundle\Bundle\AbstractCoreModule;
use Zikula\Bundle\CoreInstallerBundle\Util\ControllerUtil;
use Zikula\Bundle\CoreInstallerBundle\Util\ConfigUtil;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Zikula\Common\Translator\TranslatorInterface;
use Zikula\ExtensionsModule\Api\ExtensionApi;
use Zikula\ExtensionsModule\Entity\ExtensionEntity;

/**
 * Class AbstractController
 */
abstract class AbstractController
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var EngineInterface
     */
    protected $templatingService;

    /**
     * @var ControllerUtil
     */
    protected $util;

    /**
     * @var FormFactory
     */
    protected $form;

    /**
     * @var ConfigUtil
     */
    protected $configUtil;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->router = $this->container->get('router');
        $this->templatingService = $this->container->get('templating');
        $this->form = $this->container->get('form.factory');
        $this->util = $this->container->get('zikula_core_installer.controller.util');
        $this->configUtil = $this->container->get('zikula_core_installer.config.util');
        $this->translator = $container->get('translator.default');
    }

    /**
     * @param $moduleName
     * @return bool
     */
    protected function installModule($moduleName)
    {
        $module = $this->container->get('kernel')->getModule($moduleName);
        /** @var AbstractCoreModule $module */
        $className = $module->getInstallerClass();
        $reflectionInstaller = new \ReflectionClass($className);
        $installer = $reflectionInstaller->newInstance();
        $installer->setBundle($module);
        if ($installer instanceof ContainerAwareInterface) {
            $installer->setContainer($this->container);
        }

        if ($installer->install()) {
            return true;
        }

        return false;
    }

    /**
     * Scan the filesystem and sync the modules table. Set all core modules to active state
     * @return bool
     */
    protected function reSyncAndActivateModules()
    {
        $extensionsInFileSystem = $this->container->get('zikula_extensions_module.bundle_sync_helper')->scanForBundles();
        $this->container->get('zikula_extensions_module.bundle_sync_helper')->syncExtensions($extensionsInFileSystem);

        /** @var ExtensionEntity[] $extensions */
        $extensions = $this->container->get('zikula_extensions_module.extension_repository')->findAll();
        foreach ($extensions as $extension) {
            if ($extension->getName() != 'ZikulaPageLockModule' && \ZikulaKernel::isCoreModule($extension->getName())) {
                $extension->setState(ExtensionApi::STATE_ACTIVE);
            }
        }
        $this->container->get('doctrine')->getManager()->flush();

        return true;
    }

    /**
     * Set an admin category for a module
     * @param $moduleName
     * @param $translatedCategoryName
     */
    protected function setModuleCategory($moduleName, $translatedCategoryName)
    {
        $modulesCategories = $this->container->get('doctrine')
            ->getRepository('ZikulaAdminModule:AdminCategoryEntity')->getIndexedCollection('name');
        $moduleEntity = $this->container->get('doctrine')
            ->getRepository('ZikulaExtensionsModule:ExtensionEntity')->findOneBy(['name' => $moduleName]);
        $this->container->get('doctrine')
            ->getRepository('ZikulaAdminModule:AdminModuleEntity')
            ->setModuleCategory($moduleEntity, $modulesCategories[$translatedCategoryName]);
    }
}
