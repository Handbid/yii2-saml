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

    /**
     * Whether the IdP was successfully loaded from the database.
     * Used to skip Auth instantiation for actions that don't need an IdP (e.g., saml-metadata).
     * @var bool
     */
    private $idpLoaded = false;

    public function init()
    {
        parent::init();

        if (empty($this->config)) {
            $configFile = Yii::getAlias($this->configFileName);
            $this->config = require($configFile);
        }

        // Handbid: Load IdP from database if idpModelClass is configured
        $this->loadIdpFromDatabase();

        // Only instantiate Auth if an IdP was loaded. For actions like saml-metadata,
        // the IdP is not needed and Auth instantiation would fail with idp_not_found.
        if ($this->idpLoaded) {
            $this->instance = new Auth($this->config);
        }
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
        $idpModel = null;
        if (!empty($this->idpName) && class_exists($idpModelClass)) {
            $idpModel = $idpModelClass::findOne(['name' => $this->idpName]);
        }

        // Fallback: If IdP not found by name (e.g., RelayState was not preserved by IdP like Comcast Azure AD),
        // try to detect IdP from the SAML response Issuer element
        if (empty($idpModel) && class_exists($idpModelClass)) {
            $samlResponse = $request->getBodyParam('SAMLResponse');
            if (!empty($samlResponse)) {
                $xml = base64_decode($samlResponse);
                if (preg_match('/<Issuer[^>]*>([^<]+)<\/Issuer>/i', $xml, $matches)) {
                    $issuerEntityId = trim($matches[1]);
                    $idpModel = $idpModelClass::findOne(['samlEntityId' => $issuerEntityId]);
                    if (!empty($idpModel)) {
                        $this->idpName = $idpModel->name;
                        Yii::info("Detected IdP '{$this->idpName}' from SAML response Issuer: {$issuerEntityId}", 'single_sign_on');
                    }
                }
            }
        }

        if (!empty($idpModel) && method_exists($idpModel, 'getConfigAsArray')) {
            $this->config['idp'] = $idpModel->configAsArray;
            $this->idpLoaded = true;

            // Per-IdP SP entityId support: Some IdPs (like Disney) have a different audience
            // configured that doesn't match our default SP entityId. Override the SP entityId
            // based on the IdP to pass strict audience validation.
            $spEntityId = $this->getSpEntityIdForIdp($idpModel);
            if (!empty($spEntityId)) {
                $this->config['sp']['entityId'] = $spEntityId;
                Yii::info("Using SP entityId '{$spEntityId}' for IdP '{$this->idpName}'", 'single_sign_on');
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
     * Get the SP entityId to use for a specific IdP.
     *
     * Some IdPs have a different audience configured that doesn't match our default SP entityId.
     * This method returns the correct SP entityId for the IdP to pass strict audience validation.
     *
     * Future: This should be stored in the sso_identity_providers.spEntityId database column.
     * For now, we use a hardcoded mapping for known IdPs.
     *
     * @param object $idpModel The IdP model
     * @return string|null The SP entityId to use, or null to use the default
     */
    protected function getSpEntityIdForIdp($idpModel): ?string
    {
        // First check if the IdP model has a spEntityId property (future database support)
        if (property_exists($idpModel, 'spEntityId') && !empty($idpModel->spEntityId)) {
            return $idpModel->spEntityId;
        }

        // Temporary hardcoded mapping until spEntityId column is added to database
        // TODO: Add spEntityId column to sso_identity_providers table and remove this hardcoding
        $idpSpEntityIdMap = [
            'disney' => 'handbid',  // Disney's IdP sends audience='handbid'
            // 'flyers-charities' uses default 'https://rest.hand.bid/sp' - no override needed
        ];

        $idpName = $idpModel->name ?? null;
        return $idpSpEntityIdMap[$idpName] ?? null;
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
