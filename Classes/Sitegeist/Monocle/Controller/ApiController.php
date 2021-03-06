<?php
namespace Sitegeist\Monocle\Controller;

/**
 * This file is part of the Sitegeist.Monocle package
 *
 * (c) 2016
 * Martin Ficzel <ficzel@sitegeist.de>
 * Wilhelm Behncke <behncke@sitegeist.de>
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\Package\PackageManagerInterface;
use Sitegeist\Monocle\Fusion\FusionService;
use Sitegeist\Monocle\Fusion\FusionView;
use Sitegeist\Monocle\Fusion\ReverseFusionParser;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ApiController
 * @package Sitegeist\Monocle\Controller
 */
class ApiController extends ActionController
{

    /**
     * @var array
     */
    protected $defaultViewObjectName = 'Neos\Flow\Mvc\View\JsonView';

    /**
     * @Flow\Inject
     * @var PackageManagerInterface
     */
    protected $packageManager;

    /**
     * @Flow\Inject
     * @var FusionService
     */
    protected $fusionService;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @var array
     * @Flow\InjectConfiguration("viewportPresets")
     */
    protected $viewportPresets;

    /**
     * @var array
     * @Flow\InjectConfiguration("preview.additionalResources")
     */
    protected $additionalResources;

    /**
     * Get all styleguide objects
     *
     * @Flow\SkipCsrfProtection
     * @return void
     */
    public function styleguideObjectsAction()
    {
        $sitePackages = $this->packageManager->getFilteredPackages('available', null, 'neos-site');
        $sitePackage = reset($sitePackages);
        $sitePackageKey = $sitePackage->getPackageKey();

        $fusionAst = $this->fusionService->getMergedTypoScriptObjectTreeForSitePackage($sitePackageKey);
        $styleguideObjects = $this->fusionService->getStyleguideObjectsFromFusionAst($fusionAst);

        $this->view->assign('value', $styleguideObjects);
    }

    /**
     * Get all the configured resources
     *
     * @Flow\SkipCsrfProtection
     * @return void
     */
    public function styleguideResourcesAction()
    {
        $styleSheets = $this->additionalResources['styleSheets'];
        $javaScripts = $this->additionalResources['javaScripts'];

        $result = [
            'styleSheets' => [],
            'javaScripts' => []
        ];

        foreach ($styleSheets as $styleSheetPath) {
            $resolvedPath = $this->resolveResourcePathes($styleSheetPath);
            if ($resolvedPath) {
                $result['styleSheets'][] = $resolvedPath;
            }
        }

        foreach ($javaScripts as $javaScriptPath) {
            $resolvedPath = $this->resolveResourcePathes($javaScriptPath);
            if ($resolvedPath) {
                $result['javaScripts'][] = $resolvedPath;
            }
        }
        $this->view->assign('value', $result);
    }

    /**
     * Get all configured breakpoints
     *
     * @Flow\SkipCsrfProtection
     * @return void
     */
    public function viewportPresetsAction()
    {
        $this->view->assign('value', $this->viewportPresets);
    }

    /**
     * Render the given prototype
     *
     * @Flow\SkipCsrfProtection
     * @param string $prototypeName
     * @return void
     */
    public function renderPrototypeAction($prototypeName)
    {
        $sitePackages = $this->packageManager->getFilteredPackages('available', null, 'neos-site');
        $sitePackage = reset($sitePackages);
        $sitePackageKey = $sitePackage->getPackageKey();

        $prototypePreviewRenderPath = FusionService::RENDERPATH_DISCRIMINATOR . str_replace(['.', ':'], ['_', '__'], $prototypeName);

        // render html
        $fusionView = new FusionView();
        $fusionView->setControllerContext($this->getControllerContext());
        $fusionView->setFusionPath($prototypePreviewRenderPath);
        $fusionView->setPackageKey($sitePackageKey);

        // render fusion source
        $fusionObjectTree = $this->fusionService->getMergedTypoScriptObjectTreeForSitePackage($sitePackageKey);
        $fusionAst =  $fusionObjectTree['__prototypes'][$prototypeName];
        $fusionCode = ReverseFusionParser::restorePrototypeCode($prototypeName, $fusionAst);

        try {
            $html = $fusionView->render();
        } catch (\Exception $e) {
            $html = $e->getMessage();
        }

        $result = [
            'prototypeName' => $prototypeName,
            'renderedHtml' => $html,
            'renderedCode' => $fusionCode,
            'parsedCode' => Yaml::dump($fusionAst, 99)
        ];

        $this->view->assign('value', $result);
    }

    protected function resolveResourcePathes($path)
    {
        if (strpos($path, 'resource://') === 0) {
            try {
                list($package, $path) = $this->resourceManager->getPackageAndPathByPublicPath($path);
                return $this->resourceManager->getPublicPackageResourceUri($package, $path);
            } catch (Exception $exception) {
            }
        }
        return $path;
    }
}
