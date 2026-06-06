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
     * Handbid Customization: carries the {idp, targetApp} JSON in the SAML
     * RelayState so it survives the IdP round-trip on every IdP product.
     *
     * php-saml's Auth::login() unconditionally sets the request RelayState from
     * its $returnTo argument (and falls back to the bare self-URL when $returnTo
     * is empty). Passing the JSON as $returnTo is therefore the only way to put
     * it in the RelayState an IdP is contractually required to echo back:
     *   - Okta (e.g. Disney on D3) echoes RelayState verbatim and ignores the
     *     non-standard TargetResource query param, so the JSON must ride RelayState.
     *   - PingFederate (prod Disney) keeps working via the retained TargetResource;
     *     the ACS reads RelayState ?? TargetResource, so either carrier resolves.
     * Signature-safe: the request signature is built from $parameters['RelayState']
     * after Auth::login() assigns it, so the signed value is the JSON.
     *
     * @return void
     */
    public function run()
    {
        $relayStateParams = json_encode(['idp' => $this->idpName, 'targetApp' => $this->targetApp]);

        if ($this->idpName == 'disney') {
            // Retained for prod's PingFederate IdP, which consumes/echoes TargetResource.
            $this->params['TargetResource'] = $relayStateParams;
        }

        // An explicit returnTo (if a caller ever sets one) still wins; otherwise the
        // JSON payload becomes the RelayState instead of php-saml's bare self-URL.
        $returnTo = $this->returnTo ?: $relayStateParams;

        $this->samlInstance->login($returnTo, $this->params);
    }
}
