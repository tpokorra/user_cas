<?php

/**
 * ownCloud - user_cas
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\UserCAS\Service;

use \OCP\IConfig;
use \OC\User\Session;
use \OCP\IURLGenerator;

/**
 * Class UserService
 *
 * @package OCA\UserCAS\Service
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * @since 1.4.0
 */
class AppService
{

    /**
     * @var string $appName
     */
    private $appName;

    /**
     * @var \OCP\IConfig $appConfig
     */
    private $config;

    /**
     * @var \OC\User\Manager $userManager
     */
    private $userManager;

    /**
     * @var \OC\User\Session $userSession
     */
    private $userSession;

    /**
     * @var \OCP\IURLGenerator $urlGenerator
     */
    private $urlGenerator;

    /**
     * @var \OCA\UserCAS\User\Backend $backend
     */
    private $backend;

    /**
     * @var string
     */
    private $casVersion;

    /**
     * @var string
     */
    private $casHostname;

    /**
     * @var string
     */
    private $casPort;

    /**
     * @var string
     */
    private $casPath;

    /**
     * @var string
     */
    private $casDebugFile;

    /**
     * @var string
     */
    private $casCertPath;

    /**
     * @var string
     */
    private $casServiceUrl;

    /**
     * @var boolean
     */
    private $casInitialized = FALSE;


    /**
     * UserService constructor.
     * @param $appName
     * @param \OCP\IConfig $config
     * @param \OC\User\Session $userSession
     * @param \OCP\IURLGenerator $urlGenerator
     */
    public function __construct($appName, IConfig $config, \OC\User\Manager $userManager, Session $userSession, IURLGenerator $urlGenerator, \OCA\UserCAS\User\Backend $backend)
    {

        $this->appName = $appName;
        $this->config = $config;
        $this->userManager = $userManager;
        $this->userSession = $userSession;
        $this->urlGenerator = $urlGenerator;
        $this->backend = $backend;
    }


    /**
     * init method.
     */
    public function init()
    {

        $serverHostName = (isset($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : '';

        // Gather all app config values
        $this->casVersion = $this->config->getAppValue('user_cas', 'cas_server_version', '2.0');
        $this->casHostname = $this->config->getAppValue('user_cas', 'cas_server_hostname', $serverHostName);
        $this->casPort = $this->config->getAppValue('user_cas', 'cas_server_port', 443);
        $this->casPath = $this->config->getAppValue('user_cas', 'cas_server_path', '/cas');
        $this->casDebugFile = $this->config->getAppValue('user_cas', 'cas_debug_file', '');
        $this->casCertPath = $this->config->getAppValue('user_cas', 'cas_cert_path', '');
        $this->casServiceUrl = $this->config->getAppValue('user_cas', 'cas_service_url', '');

        if (!class_exists('\\phpCAS')) {

            \OCP\Util::writeLog('cas', 'phpCAS library could not be loaded. The class was not found.', \OCP\Util::ERROR);

            $this->casInitialized = FALSE;
        }

        if (!\phpCAS::isInitialized()) {

            try {

                \phpCAS::setVerbose(TRUE);

                if (!empty($this->casDebugFile)) {

                    \phpCAS::setDebug($this->casDebugFile);
                }


                # Initialize client
                \phpCAS::client($this->casVersion, $this->casHostname, intval($this->casPort), $this->casPath);


                if (!empty($this->casServiceUrl)) {

                    \phpCAS::setFixedServiceURL($this->casServiceUrl);
                }

                if (!empty($this->casCertPath)) {

                    \phpCAS::setCasServerCACert($this->casCertPath);
                } else {

                    \phpCAS::setNoCasServerValidation();
                }

                \OCP\Util::writeLog('cas', "phpCAS has been successfully initialized.", \OCP\Util::DEBUG);

                $this->casInitialized = TRUE;


            } catch (\CAS_Exception $e) {

                \OCP\Util::writeLog('cas', "phpCAS has thrown an exception with code: " . $e->getCode() . " and message: " . $e->getMessage() . ".", \OCP\Util::ERROR);

                $this->casInitialized = FALSE;
            }
        } else {

            \OCP\Util::writeLog('cas', "phpCAS has already been initialized.", \OCP\Util::DEBUG);
        }
    }

    /**
     * Register User Backend.
     */
    public function registerBackend()
    {

        $this->userManager->registerBackend($this->backend);
    }

    /**
     * Check if login should be enforced using user_cas.
     *
     * @return bool TRUE|FALSE
     */
    public function isEnforceAuthentication()
    {
        if (\OC::$CLI) {
            return FALSE;
        }

        if ($this->config->getAppValue($this->appName, 'cas_force_login', false) !== 'on') {
            return FALSE;
        }

        if ($this->userSession->isLoggedIn()) {
            return FALSE;
        }

        $script = basename($_SERVER['SCRIPT_FILENAME']);
        return !in_array(
            $script,
            array(
                'cron.php',
                'public.php',
                'remote.php',
                'status.php',
            )
        );
    }

    /**
     * Create a link to $route with URLGenerator.
     *
     * @param string $route
     * @return string
     */
    public function linkToRoute($route)
    {

        return $this->urlGenerator->linkToRoute($route);
    }

    /**
     * @return boolean
     */
    public function isCasInitialized()
    {
        return $this->casInitialized;
    }
}