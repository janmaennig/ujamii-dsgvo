<?php

use Ujamii\UjamiiDsgvo\Command\CheckFormsHasHttpsCommandController;

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

call_user_func(function () {
    /**
     * CommandController for powermail tasks
     */
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers']['ujamii_dsgvo'] = \Ujamii\UjamiiDsgvo\Command\CleanupCommandController::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers']['ujamii_dsgvo_check_forms'] = CheckFormsHasHttpsCommandController::class;
});
