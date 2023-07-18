<?php

namespace Plugins\Newsletters\Controllers;

use App\Core\BaseController;
use App\Core\Database;
use App\Helpers\NotificationHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\UserActionLogHelper;
use App\Helpers\ValidationHelper;
use App\Models\User;
use Plugins\Newsletters\Models\PluginNewsletterUnsubscribe;

class NewslettersController extends BaseController
{

    public function newsletterUnsubscribe() {
        // for later
        $request = $this->getRequest();
        $db = Database::getDatabase();

        // prepare variables
        $e = '';
        if ($request->query->has('e')) {
            $e = trim($request->query->get('e'));
        }

        // handle submissions
        if ($request->request->has('submitme')) {
            // validation
            $e = trim($request->request->get('e'));
            if (!strlen($e)) {
                NotificationHelper::setError(TranslateHelper::t("please_enter_your_email_address", "Please enter your email address"));
            }
            elseif (!ValidationHelper::validEmail($e)) {
                NotificationHelper::setError(TranslateHelper::t("your_email_address_is_invalid", "Your email address is invalid"));
            }
            elseif ($this->inDemoMode()) {
                NotificationHelper::setError(TranslateHelper::t("no_changes_in_demo_mode"));
            }
            else {
                $account = User::loadOne('email', $e);
                if (!$account) {
                    NotificationHelper::setError(TranslateHelper::t("newsletter_unsubscribe_could_not_find_account", "Could not find an account with that email address"));
                }
            }

            if (!NotificationHelper::isErrors()) {
                // make sure we have no deleted record already
                $pluginNewsletterUnsubscribe = PluginNewsletterUnsubscribe::loadOne('user_id', $account->id);
                if ($pluginNewsletterUnsubscribe) {
                    NotificationHelper::setError(TranslateHelper::t("newsletter_unsubscribe_account_already_unsubscribed", "The email address you've provided has already been unsubscribed from our mailing list"));
                }
            }

            // unsubscribe
            if (!NotificationHelper::isErrors()) {
                // set as unsubscribed
                $pluginNewsletterUnsubscribe = PluginNewsletterUnsubscribe::create();
                $pluginNewsletterUnsubscribe->user_id = $account->id;
                $pluginNewsletterUnsubscribe->date_unsubscribed = date('Y-m-d H:i:s');
                if ($pluginNewsletterUnsubscribe->save()) {
                    // user action logs
                    UserActionLogHelper::log('Unsubscribed from newsletter', 'ACCOUNT', 'UPDATE', [
                        'plugin' => 'newsletters',
                        'user_id' => $account->id,
                        'data' => UserActionLogHelper::getNewDataFromObject($pluginNewsletterUnsubscribe),
                    ]);

                    NotificationHelper::setSuccess(TranslateHelper::t("newsletter_unsubscribe_successfully_unsubscribed", "Your email address has been successfully removed from our mailing lists"));
                    $e = '';
                }
                else {
                    NotificationHelper::setError(TranslateHelper::t("newsletter_unsubscribe_problem_unsubscribing", "There was a problem unsubscribing your from our mailing list. Please contact us and we'll manually remove you"));
                }
            }
        }

        // load template
        return $this->render('newsletter_unsubscribe.html', array(
                    'e' => $e,
                        ), PLUGIN_DIRECTORY_ROOT . 'newsletters/views');
    }

    public function newsletterSubscribe() {
        // for later
        $request = $this->getRequest();
        $db = Database::getDatabase();

        // prepare variables
        $e = '';
        if ($request->query->has('e')) {
            $e = trim($request->query->get('e'));
        }

        // handle submissions
        if ($request->request->has('submitme')) {
            // validation
            $e = trim($request->request->get('e'));
            if (!strlen($e)) {
                NotificationHelper::setError(TranslateHelper::t("please_enter_your_email_address", "Please enter your email address"));
            }
            elseif (!ValidationHelper::validEmail($e)) {
                NotificationHelper::setError(TranslateHelper::t("your_email_address_is_invalid", "Your email address is invalid"));
            }
            elseif ($this->inDemoMode()) {
                NotificationHelper::setError(TranslateHelper::t("no_changes_in_demo_mode"));
            }
            else {
                $account = User::loadOne('email', $e);
                if (!$account) {
                    NotificationHelper::setError(TranslateHelper::t("newsletter_subscribe_could_not_find_account", "Could not find an account with that email address"));
                }
            }

            // subscribe
            if (!NotificationHelper::isErrors()) {
                // set as subscribed
                $pluginNewsletterUnsubscribe = PluginNewsletterUnsubscribe::loadOne('user_id', $account->id);
                if ($pluginNewsletterUnsubscribe) {
                    $pluginNewsletterUnsubscribe->delete();
                }

                // user action logs
                UserActionLogHelper::log('Subscribed to newsletter', 'ACCOUNT', 'UPDATE', [
                    'plugin' => 'newsletters',
                    'user_id' => $account->id,
                ]);

                // set response message
                NotificationHelper::setSuccess(TranslateHelper::t("newsletter_subscribe_successfully_subscribed", "Your email address has been successfully added to our mailing lists"));
                $e = '';
            }
        }

        // load template
        return $this->render('newsletter_subscribe.html', array(
                    'e' => $e,
                        ), PLUGIN_DIRECTORY_ROOT . 'newsletters/views');
    }

}
