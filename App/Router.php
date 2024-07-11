<?php

namespace Scandiweb\Router\App;

use Magento\Framework\App\Router\Base as SourceRouter;
use Magento\Framework\App\RequestInterface;

class Router extends SourceRouter
{
    /**
     * Create matched controller instance
     *
     * @param \Magento\Framework\App\RequestInterface $request
     * @param array $params
     * @return \Magento\Framework\App\ActionInterface|null
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function matchAction(\Magento\Framework\App\RequestInterface $request, array $params)
    {
        $moduleFrontName = $this->matchModuleFrontName($request, $params['moduleFrontName']);
        if (!strlen((string) $moduleFrontName)) {
            return null;
        }

        /**
         * Searching router args by module name from route using it as key
         */
        $modules = $this->_routeConfig->getModulesByFrontName($moduleFrontName);
        if (empty($modules) === true) {
            return null;
        }

        /**
         * Going through modules to find appropriate controller
         */
        $currentModuleName = null;
        $actionPath = null;
        $action = null;
        $actionInstance = null;

        $actionPath = $this->matchActionPath($request, $params['actionPath']);
        $action = $request->getActionName() ?: ($params['actionName'] ?: $this->_defaultPath->getPart('action'));
        $this->removeTrailingSlash($request);
        $this->_checkShouldBeSecure($request, '/' . $moduleFrontName . '/' . $actionPath . '/' . $action);

        foreach ($modules as $moduleName) {
            $currentModuleName = $moduleName;

            $actionClassName = $this->actionList->get($moduleName, $this->pathPrefix, $actionPath, $action);
            if (!$actionClassName || !is_subclass_of($actionClassName, $this->actionInterface)) {
                continue;
            }

            $actionInstance = $this->actionFactory->create($actionClassName);
            break;
        }

        if (null == $actionInstance) {
            $actionInstance = $this->getNotFoundAction($currentModuleName);
            if ($actionInstance === null) {
                return null;
            }
            $action = self::NO_ROUTE;
        }

        // set values only after all the checks are done
        $request->setModuleName($moduleFrontName);
        $request->setControllerName($actionPath);
        $request->setActionName($action);
        $request->setControllerModule($currentModuleName);
        $request->setRouteName($this->_routeConfig->getRouteByFrontName($moduleFrontName));
        if (isset($params['variables'])) {
            $request->setParams($params['variables']);
        }
        return $actionInstance;
    }

    /**
     * Remove trailing slashes from URL's
     *
     * @param RequestInterface $request
     * @return void
     */
    protected function removeTrailingSlash(RequestInterface $request)
    {
        $pathInfo = $request->getPathInfo();
        $query = $request->getQuery();
        $queryParams = $query->getArrayCopy();
        if (substr($pathInfo, -1) === '/') {
            $newPath = rtrim($pathInfo, '/');
            $baseUrl = $request->getScheme() . '://' . $request->getHttpHost();
            $newUrl = $baseUrl . $newPath;
            
            if (count($queryParams) > 0) {
                $queryString = http_build_query($queryParams);
                $newUrl .= '?' . $queryString;
            }

            $this->_responseFactory->create()->setRedirect($newUrl, 301)->sendResponse();
            exit;
        }
    }
}