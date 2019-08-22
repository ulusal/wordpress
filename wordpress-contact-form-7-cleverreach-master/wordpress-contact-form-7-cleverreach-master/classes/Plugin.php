<?php

namespace Pixelarbeit\CF7Cleverreach;

use Pixelarbeit\CF7Cleverreach\Config\Config;
use Pixelarbeit\CF7Cleverreach\Controllers\FormConfigController;
use Pixelarbeit\CF7Cleverreach\Controllers\SettingsPageController;
use Pixelarbeit\CF7Cleverreach\CF7\SubmissionHandler;

use Pixelarbeit\Cleverreach\Api as CleverreachApi;
use Pixelarbeit\Wordpress\Logger\Logger;
use Pixelarbeit\Wordpress\Notifier\Notifier;



class Plugin
{
    public static $name = 'cf7-cleverreach-integration';
    public static $prefix = 'wpcf7-cleverreach_';
    public static $version = '2.1';
    public static $title = 'Contact Form 7 - Cleverreach Integration';

    public static $clientId = 'dDHV6YpJm3';
    public static $clientSecret = 'ysqrbL2NNKTwGWphfWMRkZu1VA0kjnoS';

    private static $instance;



    /**
     * Private constructor for singleton
     *
     * @author Dennis Koch <info@pixelarbeit.de>
     * @since 1.0
     */
    private function __construct() {}



    /**
     * Get instance for singleton instance
     *
     * @author Dennis Koch <info@pixelarbeit.de>
     * @since 1.1
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
    


    /**
     * Initializes the plugin
     *
     * @author Dennis Koch <info@pixelarbeit.de>
     * @since 1.0
     */
    public function init()
    {
        $this->notifier = new Notifier('CF7 to Cleverreach');
        $this->logger = new Logger('CF7 to Cleverreach:');

        $forms = FormConfigController::getInstance();
        $forms->init($this);
        
        $settings = SettingsPageController::getInstance();
        $settings->init($this);
        
        /* Backend hooks */
        register_uninstall_hook(__FILE__, [__CLASS__, 'uninstall']);
        add_action('init', [$this, 'checkUpdate'], 10, 2);
        add_action('delete_post', [$this, 'deleteConfig'], 10, 1);
        
        /* Frontend hooks */
        add_action('wpcf7_mail_sent', [$this, 'onCF7MailSent']);
    }


    
    /**
     * Handles CF7 form submits
     *
     * @author Dennis Koch <info@pixelarbeit.de>
     * @since 1.1
     */
    public function onCf7MailSent($form)
    {
        $options = Config::getOptions($form->id());
        
        if (isset($options['active']) == false || $options['active'] == false) {
            return;
        }

        $api = $this->getApi();

        $submissionHandler = new SubmissionHandler($api);
        $submissionHandler->handleForm($form);
   
    }
    
    

    /**
     * Update routine
     *
     * @author Dennis Koch <info@pixelarbeit.de>
     * @since 1.1
     */
    public function checkUpdate()
    {
        if (version_compare($this->getVersion(), '1.1.1', '<')) {
            $token = get_option('cf7-cleverreach_token');
            update_option(self::$prefix . 'api-token', $token);
            delete_option('cf7-cleverreach_token');
            
            $this->logger->info('Updated to version 1.1');
        }  

        $this->setVersion(self::$version);
    }



    /**
     * Get saved cleverreach api token
     *
     * @author Dennis Koch <info@pixelarbeit.de>
     * @since 1.1
     */
    public function getApiToken()
    {
        return get_option(self::$prefix . 'api-token');
    }



    /**
     * Get saved plugin version
     *
     * @author Dennis Koch <info@pixelarbeit.de>
     * @since 1.1
     */
    public function getVersion()
    {
        return get_option(self::$prefix . 'version', '1.0');
    }
    
    

    /**
     * Set saved plugin version
     *
     * @author Dennis Koch <info@pixelarbeit.de>
     * @since 1.1
     */
    public function setVersion($version)
    {
        return update_option(self::$prefix . 'version', $version);
    }

    

    /**
     * Delete config for given post id if it is cf7 form
     *
     * @author Dennis Koch <info@pixelarbeit.de>
     * @since 1.0
     */
    public function deleteConfig($postId)
    {   
        if (get_post_type($postId) == \WPCF7_ContactForm::post_type) {
            Config::deleteConfig($postId);
        }
    }


    
    /**
     * Get Cleverreach API
     *
     * @return void
     * @author Dennis Koch <info@pixelarbeit.de>
     * @since 1.2
     */
    public function getApi()
    {
        $token = $this->getApiToken();
        
        if (($token = $this->getApiToken()) == false) {
            $this->notifier->warning('Incomplete configuration.');
            return new CleverreachApi();
        }

        return new CleverreachApi($token);
    }



    /**
     * Uninstall function. Deletes all config.
     *
     * @author Dennis Koch <info@pixelarbeit.de>
     * @since 1.0
     */
    public static function uninstall()
    {
        $forms = \WPCF7_ContactForm::find();
        foreach ($forms as $form) {
            Config::deleteConfig($form->id());
        }
    }
}
