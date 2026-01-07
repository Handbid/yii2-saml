<?php

namespace asasmoyo\yii2saml\actions;

/**
 * This class provides action for initiating login process using Saml.
 *
 * Handbid Customization: Added modelClass, targetApp, idpName properties
 * and RelayState/TargetResource handling for Disney SSO integration.
 */
class LoginAction extends BaseAction
{
    /**
     * @var string Model class for the user identity
     */
    public $modelClass;

    /**
     * @var string Target application (ios, android, web, manager)
     */
    public $targetApp;

    /**
     * @var string Identity Provider name
     */
    public $idpName;

    /**
     * @var string|null URL to return to after login
     */
    public $returnTo = null;

    /**
     * @var array Additional parameters for the SAML request
     */
    public $params = [];

    /**
     * Initiate login process using Saml.
     *
     * Handbid Customization: Handles RelayState for standard IdPs
     * and TargetResource for Disney SSO.
     *
     * @return void
     */
    public function run()
    {
        $relayStateParams = json_encode(['idp' => $this->idpName, 'targetApp' => $this->targetApp]);

        if ($this->idpName == 'disney') {
            $this->params['TargetResource'] = $relayStateParams;
        } else {
            $this->params['RelayState'] = $relayStateParams;
        }

        $this->samlInstance->login($this->returnTo, $this->params);
    }
}
