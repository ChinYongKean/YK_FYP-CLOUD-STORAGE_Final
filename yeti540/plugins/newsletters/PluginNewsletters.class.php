<?php

// plugin namespace

namespace Plugins\Newsletters;

// core includes
use App\Core\Database;
use App\Helpers\CoreHelper;
use App\Services\Plugin;
use Plugins\Newsletters\PluginConfig;

class PluginNewsletters extends Plugin
{
    public $config = null;
    public $data = null;

    public function __construct() {
        // load plugin config
        $this->config = (new PluginConfig())->getPluginConfig();
    }

    public function registerRoutes(\FastRoute\RouteCollector $r) {
        // register plugin routes
        $r->addRoute(['GET', 'POST'], '/' . ADMIN_FOLDER_NAME . '/plugin/' . $this->config['folder_name'] . '/settings', '\plugins\\' . $this->config['folder_name'] . '\controllers\admin\PluginController/pluginSettings');
        $r->addRoute(['GET'], '/' . ADMIN_FOLDER_NAME . '/manage_newsletter', '\plugins\\'.$this->config['folder_name'].'\controllers\admin\PluginController/manageNewsletter');
        $r->addRoute(['GET', 'POST'], '/' . ADMIN_FOLDER_NAME . '/ajax/manage_newsletter', '\plugins\\'.$this->config['folder_name'].'\controllers\admin\PluginController/ajaxManageNewsletter');
        $r->addRoute(['GET', 'POST'], '/' . ADMIN_FOLDER_NAME . '/ajax/manage_newsletter_add_form', '\plugins\\'.$this->config['folder_name'].'\controllers\admin\PluginController/ajaxManageNewsletterAddForm');
        $r->addRoute(['POST'], '/' . ADMIN_FOLDER_NAME . '/ajax/manage_newsletter_add_process', '\plugins\\'.$this->config['folder_name'].'\controllers\admin\PluginController/ajaxManageNewsletterAddProcess');
        $r->addRoute(['POST'], '/' . ADMIN_FOLDER_NAME . '/ajax/manage_newsletter_remove', '\plugins\\'.$this->config['folder_name'].'\controllers\admin\PluginController/ajaxManageNewsletterRemove');
        $r->addRoute(['GET', 'POST'], '/' . ADMIN_FOLDER_NAME . '/export_user_data', '\plugins\\'.$this->config['folder_name'].'\controllers\admin\PluginController/exportUserData');
        $r->addRoute(['GET'], '/' . ADMIN_FOLDER_NAME . '/manage_newsletter_view', '\plugins\\'.$this->config['folder_name'].'\controllers\admin\PluginController/manageNewsletterView');
        $r->addRoute(['GET', 'POST'], '/newsletter_unsubscribe', '\plugins\\'.$this->config['folder_name'].'\controllers\NewslettersController/newsletterUnsubscribe');
        $r->addRoute(['GET', 'POST'], '/newsletter_subscribe', '\plugins\\'.$this->config['folder_name'].'\controllers\NewslettersController/newsletterSubscribe');
    }

    public function getPluginDetails() {
        return $this->config;
    }

    public function uninstall() {
        // setup database
        $db = Database::getDatabase();

        // remove plugin specific tables
        $sQL = 'DROP TABLE plugin_newsletter';
        $db->query($sQL);
        $sQL = 'DROP TABLE plugin_newsletter_sent';
        $db->query($sQL);
        $sQL = 'DROP TABLE plugin_newsletter_unsubscribe';
        $db->query($sQL);

        return parent::uninstall();
    }

    public function getRecipients($userGroup, $includeUnsubs = false) {
        // setup database
        $db = Database::getDatabase();

        $clause = 'level_id > 0 AND ';
        switch ($userGroup) {
            // string versions kept for older data
            case 'free only':
                $clause .= 'level_id = 1';
                break;
            case 'premium only':
                $clause .= 'level_id = 2';
                break;
            case 'moderator only':
                $clause .= 'level_id = 10';
                break;
            case 'admin only':
                $clause .= 'level_id = 20';
                break;
            case is_numeric($userGroup):
                if ((int) $userGroup > 0) {
                    $clause .= 'level_id = ' . (int) $userGroup;
                }
                else {
                    // all registered
                    $clause .= '1=1';
                }
                break;
            default:
                // all registered
                $clause .= '1=1';
                break;
        }

        $sQL = 'SELECT * '
                . 'FROM users '
                . 'WHERE status=\'active\' '
                . 'AND ' . $clause;
        if ($includeUnsubs == false) {
            $sQL .= ' AND id NOT IN (SELECT user_id FROM plugin_newsletter_unsubscribe)';
        }
        $sQL .= ' AND (email != "" AND email IS NOT NULL)';

        return $db->getRows($sQL);
    }

    public function sendNewsletter($subject, $htmlContent, $toEmail, $fromEmail) {
        return CoreHelper::sendHtmlEmail($toEmail, $subject, $htmlContent, $fromEmail);
    }

}
