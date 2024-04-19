<?php

namespace C3\EnvironmentBanner\Helper;

use C3\EnvironmentBanner\Model\Colours;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

class Data extends \Magento\Framework\App\Helper\AbstractHelper {

    protected $_environments = null;
    protected $_colours2 = null;
    protected $_colours = null;
    protected $_ips = null;
    protected $_remoteAddress = null;

    /**
     * @param Context $context
     * @param Colours                $colours
     */
    public function __construct(Context $context, Colours $colours, RemoteAddress $remoteAddress)
    {
        parent::__construct($context);
        $this->_colours2 = $colours;
        $this->_remoteAddress = $remoteAddress;
    }

    /**
     * Whether to display, given environment, settings etc.
     *
     * @return bool
     */
    public function isDisplayFrontendBanner()
    {
        //Check that output is enabled, else return false
        if (!$this->isFrontendBannerEnabled()) {
            return false;
        }

        // Check that the given environment is recognised, else false
        $environments = $this->getEnvironments();
        if (!isset($environments[$this->getEnvironment()])) {
            return false;
        }

        // Never display on production if the remote address is not whitelisted, or if no background colour set (can be used to indicate production)
        if (($this->getEnvironment() == 'production' && !$this->isRemoteAddressWhitelisted()) || $this->getEnvColours()->getFeBgcolor() === null) {
            return false;
        }

        // If a IP whitelist is set, never display if the remote address is not in the whitelist
        if ($this->isRemoteAddressWhitelisted() === false) return false;

        // We're enabled, in a recognised environment, so... display!
        return true;
    }

    /**
     * Whether to display, given environment, settings etc.
     *
     * @return bool
     */
    public function isDisplayAdminBanner()
    {
        // Check that output is enabled, else return false
        if (!$this->isChangeAdminColour()) {
            return false;
        }

        // Check that the given environment is recognised, else false
        $environments = $this->getEnvironments();
        if (!isset($environments[$this->getEnvironment()])) {
            return false;
        }

        // Never display if no background colour set (can be used to indicate skipping)
        if ($this->getEnvColours()->getBeColor() === null) {
            return false;
        }

        // We're enabled, in a recognised environment, so... display!
        return true;
    }

    /**
     * Get environments array, indexed by environment code
     *
     * @return array
     */
    protected function getEnvironments()
    {
        // Lazily load environments from config
        if ($this->_environments === null) {
            //$envConfig = unserialize(Mage::getStoreConfig("{$this->_configPrefix}/environments/environments"));
            $envConfig = json_decode($this->scopeConfig->getValue('environmentbanner/environments/environments', \Magento\Store\Model\ScopeInterface::SCOPE_STORE), true) ?: [];

            // Make into associative array
            $this->_environments = array();
            foreach ($envConfig as $env) {
                $this->_environments[$env['env']] = $env;
            }
        }

        return $this->_environments;
    }

    /**
     * Get colours set for current environment
     *
     * @return C3_EnvironmentBanner_Model_Colours|null Null if cannot find colours for current environment
     */
    public function getEnvColours()
    {
        // Lazily load colours from environment data
        if ($this->_colours === null) {
            $environments = $this->getEnvironments();
            if (!isset($environments[$this->getEnvironment()])) {
                return null;
            }
            $data = $environments[$this->getEnvironment()];
            //$this->_colours = Mage::getModel('c3_environmentbanner/colours')
              //  ->setData($data);
            $this->_colours = $this->_colours2;
            $this->_colours->setData($data);

        }

        return $this->_colours;
    }

    /**
     * Get whitelisted IPs for current environment
     *
     * @return array|null Null if cannot find IPs for current environment
     */
    public function getEnvIPs()
    {
        // Lazily load IPs from environment data
        if ($this->_ips === null) {
            $environments = $this->getEnvironments();
            if (!isset($environments[$this->getEnvironment()])) {
                return null;
            }
            $this->_ips = array_map('trim', explode(',', $environments[$this->getEnvironment()]['ip_whitelist']));
        }

        return $this->_ips;
    }

    /**
     * Return remote IP address of visitor
     *
     * @return false|string False if cannot find remote address
     */
    public function getRemoteAddress()
    {
        return $this->_remoteAddress->getRemoteAddress();
    }

    /**
     * Return remote IP address of visitor
     *
     * @return bool|null @null if either the remote address does not exist, or the IP whitelist is empty. @false If the
     * remorte address is not in the whitelist, and @true otherwise.
     */
    public function isRemoteAddressWhitelisted(): bool|null
    {
        // If no whitelist set, return @null to indciate this specific situation
        if (!$this->isIpWhitelistSet()) return null;

        // For safety, always return @false if the IP whitelist is set, but we are unable to retireve the remote address
        if (!$$this->getRemoteAddress()) return false;

        return in_array(
            $this->getRemoteAddress(),
            $this->getEnvIPs() ?? []
        );
    }

    /**
     * Checks if the IP whitelist has any valid values set
     *
     * @return bool @true if an item exists in the array which is not an empty string
     */
    public function isIpWhitelistSet(): bool
    {
        $ips = $this->getEnvIPs();

        if (!is_array($ips) || count($ips) == 0) return false;

        foreach ($ips as $ip) {
            if ($ip !== "") return true;
        }

        return false;
    }

    /**
     * Whether the display-banner functionality is turned on
     *
     * @return bool
     */
    public function isFrontendBannerEnabled()
    {
        return ($this->scopeConfig->getValue('environmentbanner/frontend/enabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == true);
    }

    /**
     * Whether to change the colour of the admin banner according to the environment
     *
     * @return bool
     */
    public function isDisplayEnvName() {
        return ($this->scopeConfig->getValue('environmentbanner/admin/display_env', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == true);
    }

    public function isChangeAdminColour()
    {
        return ($this->scopeConfig->getValue('environmentbanner/admin/colour_change') == true);
    }

    /**
     * Whether to display the name of the environment in admin
     *
     * @return bool
     */
    public function isDisplayAdminEnv()
    {
        return ($this->scopeConfig->getValue('environmentbanner/admin/display_env') == true);
    }

    /**
     * Filename of the admin logo - defaults to 'logo.gif'.
     *
     * @return string
     */
    public function getAdminLogoFilename() {
        return ($this->scopeConfig->getValue('environmentbanner/admin/logo_filename') == true);
    }

    /**
     * Return the current application environment. If not set, return null
     *
     * @return null|string
     */
    public function getEnvironment()
    {
        if (!isset($_SERVER['APPLICATION_ENV'])) {
            return null;
        }

        return $_SERVER['APPLICATION_ENV'];
    }
}
