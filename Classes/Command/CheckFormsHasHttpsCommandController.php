<?php
declare(strict_types=1);

namespace Ujamii\UjamiiDsgvo\Command;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\Request;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use Ujamii\UjamiiDsgvo\Service\DbOperationsService;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Class CleanupCommandController
 * @package Ujamii\UjamiiDsgvo\Command
 */
class CheckFormsHasHttpsCommandController extends CommandController
{
    /** @var RequestFactoryInterface */
    private $requestFactory;

    /**
     * @var \Ujamii\UjamiiDsgvo\Service\DbOperationsService
     */
    protected $service;

    /**
     * Cleans up old and deleted records in the database to comply with the DSGVO rules in Germany, which are
     * based on the privacy shield regulations valid in the whole EU.
     *
     * @param int $pageUid The uid of the page where the ts config is read from. If you have different configs for
     *                     subtrees, use the uid here.
     * @param string $mode See DbOperationsService::MODE_* constants [select|delete|anonymize], default is "delete".
     *
     * @throws \Exception
     */
    public function checkFormTargetCommand($pageUid = 1, $mode = DbOperationsService::MODE_ANONYMIZE)
    {
        $this->requestFactory = $this->objectManager->get(RequestFactory::class);


        $tsConfig = BackendUtility::getPagesTSconfig($pageUid);
        if (isset($tsConfig['module.']['tx_ujamiidsgvo_dsgvocheck.']) && is_array($tsConfig['module.']['tx_ujamiidsgvo_dsgvocheck.'])) {
            $tsConfig = GeneralUtility::removeDotsFromTS($tsConfig['module.']['tx_ujamiidsgvo_dsgvocheck.']);

            $this->service = $this->objectManager->get(DbOperationsService::class);
            $this->service->setTsConfiguration($tsConfig);

            $result = $this->service->getPageTreeByRootPageId(1);

            $doc = new \DOMDocument();
            $request = $this->objectManager->get(Request::class, 'http://t3cm.ddev.site/');

            $pages = [];

            libxml_use_internal_errors(true);

            $doc->loadHTMLFile('http://t3cm.ddev.site/?id=38');
            foreach ($result as $pageId) {
                $this->outputLine($pageId);
                try {
                    libxml_use_internal_errors(true);

                    $doc->loadHTMLFile('http://t3cm.ddev.site/?id=' . $pageId);

                    $this->outputLine($pageId);

                    if ($doc->getElementsByTagName('form')->count() > 0) {
                        $pages[] = $pageId;
                        /** @var \DOMNode $form */
                        foreach ($doc->getElementsByTagName('form') as $form)
                        {
                            $formAction = (string) $form->attributes->getNamedItem('action')->nodeValue;

                            if (strtolower(substr($formAction, 0, 5)) === 'https') {
                                \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump('OK');
                            }

                            if (strtolower(substr($formAction, 0, 5)) === 'http:') {
                                \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump('FAIL');
                            }

                            if (strtolower(substr($formAction, 0, 4)) !== 'http') {

                                $urlHttp = HttpUtility::buildUrl([
                                    'scheme' => 'http',
                                    'host' => 't3cm.ddev.site',
                                    'query' => 'id=' . $pageId
                                ]);

                                $urlHttps = HttpUtility::buildUrl([
                                    'scheme' => 'https',
                                    'host' => 't3cm.ddev.site',
                                    'query' => 'id=' . $pageId
                                ]);

                                $additionalOptions = [
                                    'headers' => ['Cache-Control' => 'no-cache'],
                                    'allow_redirects' => false
                                ];

                                $responseHttp = $this->requestFactory->request($urlHttp, 'GET', $additionalOptions);
                                $responseHttps = $this->requestFactory->request($urlHttps, 'GET', $additionalOptions);

                                if ($responseHttp->getStatusCode() !== 200 && $responseHttps->getStatusCode() === 200) {
                                    \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump('OK');
                                } else if ($responseHttp->getStatusCode() === 200) {
                                    \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump('NOT OK');
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            $this->outputLine(implode(', ', $pages));
            //$this->outputLine(DebuggerUtility::var_dump($result,
             //   'FALSE means Extension not installed, integer is amount of handled records.', 8, true, true, true));
        } else {
            throw new \Exception('TS could not be loaded!');
        }
    }

}
