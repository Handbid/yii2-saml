<?php

namespace asasmoyo\yii2saml;

use Yii;
use Exception;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Settings;
use yii\base\BaseObject;

/**
 * This class wraps OneLogin_Saml2_Auth class by creating an instance of that class
 * using configurations specified in configFileName variable inside @app/config folder.
 *
 * Handbid Fork: Added dynamic IdP loading from database via idpModelClass config.
 */
class Saml extends BaseObject
{

    /**
     * The file in which contains OneLogin_Saml2_Auth configurations.
     * @var string
     */
    public $configFileName = '@app/config/saml.php';

    /**
     * OneLogin_Saml2_Auth instance.
     * @var \OneLogin\Saml2\Auth
     */
    private $instance;

    /**
     * Configurations for OneLogin_Saml2_Auth.
     * @var array
     */
    public $config;

    /**
     * The name of the current IdP being used.
     * @var string|null
     */
    private $idpName;

    public function init()
    {
        parent::init();

        if (empty($this->config)) {
            $configFile = Yii::getAlias($this->configFileName);
            $this->config = require($configFile);
        }

        // Handbid: Load IdP from database if idpModelClass is configured
        $this->loadIdpFromDatabase();

        $this->instance = new Auth($this->config);
    }

    /**
     * Handbid: Load IdP configuration from database using the configured model class.
     * This allows dynamic IdP configuration (e.g., Disney SSO) to be stored in the database
     * rather than in config files.
     */
    protected function loadIdpFromDatabase()
    {
        // Only proceed if idpModelClass is configured
        if (empty($this->config['idpModelClass'])) {
            return;
        }

        $idpModelClass = $this->config['idpModelClass'];

        // Get IdP name from query param or RelayState body param
        $request = Yii::$app->getRequest();
        $this->idpName = $request->getQueryParam('idp');

        if (empty($this->idpName)) {
            // Try to get from RelayState (used in SAML response callbacks)
            $relayStateJson = $request->getBodyParam('RelayState');
            // Also check TargetResource (used by Disney SSO)
            if (empty($relayStateJson)) {
                $relayStateJson = $request->getBodyParam('TargetResource');
            }
            if (!empty($relayStateJson)) {
                $relayState = json_decode($relayStateJson, true);
                if (is_array($relayState) && !empty($relayState['idp'])) {
                    $this->idpName = $relayState['idp'];
                }
            }
        }

        // Skip IdP validation for certain actions (like metadata)
        $actionsWithIdPCheckSkipped = $this->config['actionsWithIdPCheckSkipped'] ?? [];
        if (!empty($actionsWithIdPCheckSkipped)) {
            $pathInfo = $request->pathInfo ?? '';
            $urlSegments = explode('/', $pathInfo);
            $actionId = end($urlSegments);
            if (in_array($actionId, $actionsWithIdPCheckSkipped)) {
                // Skip IdP loading for metadata and similar endpoints
                return;
            }
        }

        // Load IdP from database if we have a name
        if (!empty($this->idpName) && class_exists($idpModelClass)) {
            $idpModel = $idpModelClass::findOne(['name' => $this->idpName]);
            if (!empty($idpModel) && method_exists($idpModel, 'getConfigAsArray')) {
                $this->config['idp'] = $idpModel->configAsArray;
            }
        }

        // Remove Handbid-specific config keys before passing to onelogin/php-saml
        unset($this->config['idpModelClass']);
        unset($this->config['actionsWithIdPCheckSkipped']);
    }

    /**
     * Get the current IdP name.
     * @return string|null
     */
    public function getIdpName()
    {
        return $this->idpName;
    }

    /**
     * Call the login method on OneLogin_Saml2_Auth.
     */
    public function login($returnTo = null, $parameters = array(), $forceAuthn = false, $isPassive = false)
    {
        return $this->instance->login($returnTo, $parameters, $forceAuthn, $isPassive);
    }

    /**
     * Call the logout method on OneLogin_Saml2_Auth.
     */
    public function logout($returnTo = null, $parameters = array(), $nameId = null, $sessionIndex = null)
    {
        return $this->instance->logout($returnTo, $parameters, $nameId, $sessionIndex);
    }

    /**
     * Call the getAttributes method on OneLogin_Saml2_Auth.
     */
    public function getAttributes()
    {
        return $this->instance->getAttributes();
    }

    /**
     * Call the getAttribute method on OneLogin_Saml2_Auth.
     */
    public function getAttribute($name)
    {
        return $this->instance->getAttribute($name);
    }

    /**
     * Returns the metadata of this Service Provider in xml.
     * @return string Metadata in xml
     * @throws Exception
     * @throws OneLogin\Saml2\Error
     */
    public function getMetadata()
    {
        $samlSettings = new Settings($this->config, true);
        $metadata = $samlSettings->getSPMetadata();

        $errors = $samlSettings->validateMetadata($metadata);
        if (!empty($errors)) {
            throw new Exception('Invalid Metadata Service Provider');
        }

        return $metadata;
    }

    /**
     * Call the processResponse method on OneLogin_Saml2_Auth.
     */
    public function processResponse()
    {
        $this->instance->processResponse();
    }

    public function processSLO()
    {
        $this->instance->processSLO();
    }

    /**
     * Call the getErrors method on OneLogin_Saml2_Auth.
     */
    public function getErrors()
    {
        return $this->instance->getErrors();
    }

    /**
     * Call the getLastErrorReason method on OneLogin_Saml2_Auth.
     */
    public function getLastErrorReason()
    {
        return $this->instance->getLastErrorReason();
    }

    /**
     * Check if debug is enabled on OneLogin_Saml2_Auth.
     */
    public function isDebugActive()
    {
        $samlSettings = $this->instance->getSettings();
        return $samlSettings->isDebugActive();
    }
}
