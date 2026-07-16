<?php

namespace Sugar_Calendar\Vendor\ProductApi\Auth;

use Sugar_Calendar\Vendor\ProductApi\Context;
use Sugar_Calendar\Vendor\ProductApi\Options;
/**
 * Auth Manager.
 *
 * @since 1.0.0
 */
class AuthManager
{
    /**
     * Context instance.
     *
     * @since 1.0.0
     *
     * @var Context
     */
    private $context;
    /**
     * Options instance.
     *
     * @since 1.0.0
     *
     * @var Options
     */
    private $options;
    /**
     * Site ownership verification provider instance.
     *
     * @since 1.0.0
     *
     * @var SiteOwnershipVerificationProvider
     */
    private $site_ownership_verification_provider;
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param Context $context Context instance.
     * @param Options $options Options instance.
     */
    public function __construct(Context $context, Options $options)
    {
        $this->context = $context;
        $this->options = $options;
        $this->site_ownership_verification_provider = new SiteOwnershipVerificationProvider($this->context, $this->options);
    }
    /**
     * Hooks.
     *
     * @since 1.0.0
     */
    public function hooks()
    {
        $this->site_ownership_verification_provider->hooks();
    }
}
