<?php

namespace Plugins\Newsletters\Controllers;

use App\Core\BaseController;
use App\Core\Database;
use App\Helpers\CoreHelper;
use App\Helpers\FileHelper;
use App\Helpers\PluginHelper;
use App\Helpers\ThemeHelper;
use App\Models\File;
use Plugins\Newsletters\Controllers\NewslettersController;

class HooksController extends BaseController
{

    public function adminPluginNav($params = null) {
        // output within the admin left-hand navigation
        $navigation = [
            [
                'link_url' => '#', 'link_text' => 'Newsletters', 'link_key' => 'newsletters', 'icon_class' => 'fa fa-envelope', 'children' => [
                    ['link_url' => 'admin/manage_newsletter?create=1', 'link_text' => 'Create Newsletter', 'link_key' => 'newsletters_manage_newsletter'],
                    ['link_url' => 'admin/manage_newsletter?create=2', 'link_text' => 'Create Service Email', 'link_key' => 'newsletters_manage_newsletter'],
                    ['link_url' => 'admin/manage_newsletter', 'link_text' => 'Manage Newsletters', 'link_key' => 'newsletters_manage_newsletter'],
                    ['link_url' => 'admin/export_user_data', 'link_text' => 'Export User Data', 'link_key' => 'newsletters_export_user_data'],
            ]
            ],
        ];

        // return array
        return $navigation;
    }

    public function postDeleteUserData($params = null) {
        // connect db
        $db = Database::getDatabase();

        // remove user from mailing list data
        $db->query('DELETE '
                . 'FROM plugin_newsletter_unsubscribe '
                . 'WHERE user_id = :user_id', [
            'user_id' => (int) $params['User']->id,
        ]);
    }

}
