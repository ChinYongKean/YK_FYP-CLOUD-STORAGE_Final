<?php

namespace Plugins\Filepreviewer\Controllers\Admin;

use App\Core\Database;
use App\Controllers\Admin\PluginController AS CorePluginController;
use App\Helpers\AdminHelper;
use App\Helpers\PluginHelper;
use App\Helpers\ThemeHelper;
use App\Helpers\UserActionLogHelper;
use App\Models\File;
use App\Models\Plugin;
use Plugins\Filepreviewer\Models\PluginFilepreviewerWatermark;

class PluginController extends CorePluginController {

    public function pluginSettings() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // load plugin details
        $folderName = 'filepreviewer';
        $plugin = Plugin::loadOneByClause('folder_name = :folder_name', array(
                    'folder_name' => $folderName,
        ));

        if (!$plugin) {
            return $this->redirect(ADMIN_WEB_ROOT . '/plugin_manage?error=' . urlencode('There was a problem loading the plugin details.'));
        }

        // dropdowns
        $watermarkPositionOptions = [];
        $watermarkPositionOptions['top left'] = 'Top-Left';
        $watermarkPositionOptions['top'] = 'Top-Middle';
        $watermarkPositionOptions['top right'] = 'Top-Right';
        $watermarkPositionOptions['right'] = 'Right';
        $watermarkPositionOptions['bottom right'] = 'Bottom-Right';
        $watermarkPositionOptions['bottom'] = 'Bottom-Middle';
        $watermarkPositionOptions['bottom left'] = 'Bottom-Left';
        $watermarkPositionOptions['left'] = 'Left';
        $watermarkPositionOptions['center'] = 'Middle';

        // available video players
        $videoPlayers = [];
        $videoPlayers['ultimate'] = 'Ultimate Player - No support for adverts.';
        $videoPlayers['elite'] = 'Elite Player - VAST/VMAP/IMA advert support.';
        $videoPlayers['videojs'] = 'VideoJS Player - VAST/VMAP/IMA advert support.';

        // available audio players
        $audioPlayers = [];
        $audioPlayers['ultimate'] = 'Ultimate Player';

        // test for image libraries
        $gdAvailable = false;
        if (function_exists('gd_info')) {
            $gdAvailable = true;
        }
        $imagemagickAvailable = false;
        if (class_exists("Imagick")) {
            $imagemagickAvailable = true;
        }

        // load imagemagick formats
        $formatsArr = [];
        if ($imagemagickAvailable == true) {
            $formats = \Imagick::queryformats();
            $totalFormats = count($formats);
            for ($i = 0; $i < $totalFormats; $i++) {
                // don't include video formats or pdfs
                if (in_array(strtolower($formats[$i]), array('mp4', 'mov', 'mpg', 'mpeg', 'pdf'))) {
                    continue;
                }
                $formatsArr[] = strtolower($formats[$i]);
            }
        } else {
            $formatsArr = explode('|', File::IMAGE_EXTENSIONS);
        }

        // prepare variables
        $plugin_enabled = (int) $plugin->plugin_enabled;
        $allow_direct_links = 1;
        $show_file_details_outside_account = 1;
        $non_show_viewer = 1;
        $free_show_viewer = 1;
        $paid_show_viewer = 1;
        $show_similar_items = 0;

        // images
        $enable_preview_image = 1;
        $preview_image_show_thumb = 1;
        $auto_rotate = 1;
        $image_library = 'gd';
        $image_size_w = 920;
        $image_size_h = 700;
        $watermark_enabled = 0;
        $watermark_contents = '';
        $watermark_filename = '';
        $watermark_position = 'bottom right';
        $watermark_padding = 10;
        $images_show_embedding = 1;
        $thumb_size_w = 180;
        $thumb_size_h = 150;
        $thumb_resize_method = 'cropped';
        $show_download_sizes = 1;
        $image_quality = 90;
        $supported_image_types = 'jpg,jpeg,png,gif,wbmp';
        $animate_gif_thumbnails = 0;

        // documents
        $enable_preview_document = 1;
        $preview_document_pdf_thumbs = 1;
        $preview_document_ext = 'doc,docx,xls,xlsx,ppt,pptx,pdf,pages,ai,psd,tiff,dxf,svg,eps,ps,ttf,otf,xps';
        $documents_show_embedding = 1;
        $documents_embed_document_size_w = 450;
        $documents_embed_document_size_h = 600;

        // videos
        $enable_preview_video = 1;
        $default_preview_video_player = 'ultimate';
        $preview_video_player = $default_preview_video_player;
        $preview_video_ext = 'mp4,flv,ogg';
        $videos_autoplay = 1;
        $videos_show_embedding = 1;
        $videos_embed_size_w = 640;
        $videos_embed_size_h = 320;

        // audio
        $enable_preview_audio = 1;
        $default_preview_audio_player = 'ultimate';
        $preview_audio_player = $default_preview_audio_player;
        $preview_audio_ext = 'mp3';
        $audio_autoplay = 1;
        $audio_show_embedding = 1;
        $audio_embed_size_w = 640;
        $audio_embed_size_h = 320;

        // text files & code
        $enable_preview_text = 1;
        $syntax_highlight_text = 1;
        $preview_text_ext = 'txt,php,html,htm,xhtml,js,css,srt,vtt,log,sql,xml,asp,aspx,cer,cfm,cgi,pl,jsp,py,rss,c,class,cpp,h,java,sh,vb,swift,cfg,ini,text';
        $text_show_embedding = 1;
        $text_embed_document_size_w = 450;
        $text_embed_document_size_h = 600;

        // load existing settings
        if (strlen($plugin->plugin_settings)) {
            $plugin_settings = json_decode($plugin->plugin_settings, true);
            if ($plugin_settings) {
                $allow_direct_links = (int) $plugin_settings['allow_direct_links'];
                $show_file_details_outside_account = isset($plugin_settings['show_file_details_outside_account']) ? (int) $plugin_settings['show_file_details_outside_account'] : 1;
                $show_similar_items = isset($plugin_settings['show_similar_items']) ? (int) $plugin_settings['show_similar_items'] : 0;

                // images
                $enable_preview_image = (int) $plugin_settings['enable_preview_image'];
                $preview_image_show_thumb = (int) $plugin_settings['preview_image_show_thumb'];
                $auto_rotate = (int) $plugin_settings['auto_rotate'];
                $image_library = trim($plugin_settings['image_library']);
                $watermark_enabled = (int) $plugin_settings['watermark_enabled'];
                $watermark_position = $plugin_settings['watermark_position'];
                $watermark_padding = (int) $plugin_settings['watermark_padding'];
                $images_show_embedding = (int) $plugin_settings['images_show_embedding'];
                $thumb_size_w = (int) $plugin_settings['thumb_size_w'] === 0 ? $thumb_size_w : (int) $plugin_settings['thumb_size_w'];
                $thumb_size_h = (int) $plugin_settings['thumb_size_h'] === 0 ? $thumb_size_h : (int) $plugin_settings['thumb_size_h'];
                $thumb_resize_method = $plugin_settings['thumb_resize_method'];
                $show_download_sizes = (int) $plugin_settings['show_download_sizes'];
                $image_quality = (int) $plugin_settings['image_quality'] == 0 ? 90 : $plugin_settings['image_quality'];
                $supported_image_types = $plugin_settings['supported_image_types'];
                $supported_image_types = strtolower($supported_image_types);
                $animate_gif_thumbnails = (int) $plugin_settings['animate_gif_thumbnails'];
                if ($watermark_enabled == 1) {
                    $watermark = $db->getRow("SELECT file_name, image_content "
                            . "FROM plugin_filepreviewer_watermark");
                    if ($watermark) {
                        $watermark_contents = $watermark['image_content'];
                        $watermark_filename = $watermark['file_name'];
                    } else {
                        $watermark_enabled = 0;
                    }
                }

                // documents
                $enable_preview_document = (int) $plugin_settings['enable_preview_document'];
                $preview_document_pdf_thumbs = (int) $plugin_settings['preview_document_pdf_thumbs'];
                $preview_document_ext = $plugin_settings['preview_document_ext'];
                $documents_show_embedding = (int) $plugin_settings['documents_show_embedding'];
                $documents_embed_document_size_w = (int) $plugin_settings['documents_embed_document_size_w'] === 0 ? $documents_embed_document_size_w : (int) $plugin_settings['documents_embed_document_size_w'];
                $documents_embed_document_size_h = (int) $plugin_settings['documents_embed_document_size_h'] === 0 ? $documents_embed_document_size_h : (int) $plugin_settings['documents_embed_document_size_h'];

                // videos
                $enable_preview_video = (int) $plugin_settings['enable_preview_video'];
                $preview_video_player = isset($plugin_settings['preview_video_player'])?$plugin_settings['preview_video_player']:$preview_video_player;
                $preview_video_ext = $plugin_settings['preview_video_ext'];
                $videos_autoplay = (int) $plugin_settings['videos_autoplay'];
                $videos_show_embedding = isset($plugin_settings['videos_show_embedding']) ? $plugin_settings['videos_show_embedding'] : $videos_show_embedding;
                $videos_embed_size_w = (int) $plugin_settings['videos_embed_size_w'] ? (int) $plugin_settings['videos_embed_size_w'] : $videos_embed_size_w;
                $videos_embed_size_h = (int) $plugin_settings['videos_embed_size_h'] ? (int) $plugin_settings['videos_embed_size_h'] : $videos_embed_size_h;

                // audio
                $enable_preview_audio = (int) $plugin_settings['enable_preview_audio'];
                $preview_audio_player = isset($plugin_settings['preview_audio_player'])?$plugin_settings['preview_audio_player']:$preview_audio_player;
                $preview_audio_ext = $plugin_settings['preview_audio_ext'];
                $audio_autoplay = (int) $plugin_settings['audio_autoplay'];
                $audio_show_embedding = isset($plugin_settings['audio_show_embedding']) ? $plugin_settings['audio_show_embedding'] : $audio_show_embedding;
                $audio_embed_size_w = (int) $plugin_settings['audio_embed_size_w'] ? (int) $plugin_settings['audio_embed_size_w'] : $audio_embed_size_w;
                $audio_embed_size_h = (int) $plugin_settings['audio_embed_size_h'] ? (int) $plugin_settings['audio_embed_size_h'] : $audio_embed_size_h;

                // text files & code
                if (isset($plugin_settings['enable_preview_text'])) {
                    $enable_preview_text = (int) $plugin_settings['enable_preview_text'];
                    $syntax_highlight_text = (int) $plugin_settings['syntax_highlight_text'];
                    $preview_text_ext = $plugin_settings['preview_text_ext'];
                    $text_show_embedding = (int) $plugin_settings['text_show_embedding'];
                    $text_embed_document_size_w = (int) $plugin_settings['text_embed_document_size_w'] === 0 ? $text_embed_document_size_w : (int) $plugin_settings['text_embed_document_size_w'];
                    $text_embed_document_size_h = (int) $plugin_settings['text_embed_document_size_h'] === 0 ? $text_embed_document_size_h : (int) $plugin_settings['text_embed_document_size_h'];
                }
            }
        }

        // handle page submissions
        if (isset($_REQUEST['submitted'])) {
            // get variables
            $oldPluginSettings = json_decode($plugin->plugin_settings, true);
            $plugin_enabled = (int) $_REQUEST['plugin_enabled'];
            $plugin_enabled = $plugin_enabled != 1 ? 0 : 1;
            $allow_direct_links = (int) $_REQUEST['allow_direct_links'];
            $show_file_details_outside_account = (int) $_REQUEST['show_file_details_outside_account'];
            $show_similar_items = (int) $_REQUEST['show_similar_items'];

            // images
            $enable_preview_image = (int) $_REQUEST['enable_preview_image'];
            $preview_image_show_thumb = (int) $_REQUEST['preview_image_show_thumb'];
            $auto_rotate = (int) $_REQUEST['auto_rotate'];
            $image_library = trim($_REQUEST['image_library']);
            $watermark_enabled = (int) $_REQUEST['watermark_enabled'];
            $watermark_position = $_REQUEST['watermark_position'];
            $watermark_padding = (int) $_REQUEST['watermark_padding'];
            $images_show_embedding = (int) $_REQUEST['images_show_embedding'];
            $thumb_size_w = (int) $_REQUEST['thumb_size_w'];
            $thumb_size_h = (int) $_REQUEST['thumb_size_h'];
            $thumb_resize_method = $_REQUEST['thumb_resize_method'];
            $show_download_sizes = (int) $_REQUEST['show_download_sizes'];
            $image_quality = (int) $_REQUEST['image_quality'];
            if ($image_quality > 100) {
                $image_quality = 100;
            } elseif ($image_quality <= 0) {
                $image_quality = 90;
            }
            $supported_image_types = trim($_REQUEST['supported_image_types']);
            $supported_image_types = strtolower(str_replace(' ', '', $supported_image_types));
            $animate_gif_thumbnails = (int) $_REQUEST['animate_gif_thumbnails'];

            // documents
            $enable_preview_document = (int) $_REQUEST['enable_preview_document'];
            $preview_document_pdf_thumbs = (int) $_REQUEST['preview_document_pdf_thumbs'];
            $preview_document_ext = trim(strtolower($_REQUEST['preview_document_ext']));
            $documents_show_embedding = (int) $_REQUEST['documents_show_embedding'];
            $documents_embed_document_size_w = (int) $_REQUEST['documents_embed_document_size_w'];
            $documents_embed_document_size_h = (int) $_REQUEST['documents_embed_document_size_h'];

            // videos
            $enable_preview_video = (int) $_REQUEST['enable_preview_video'];
            $preview_video_player = $_REQUEST['preview_video_player'] && ThemeHelper::getCurrentProductType() === 'file_hosting' ? $_REQUEST['preview_video_player'] : $default_preview_video_player;
            $preview_video_ext = trim(strtolower($_REQUEST['preview_video_ext']));
            $videos_autoplay = (int) $_REQUEST['videos_autoplay'];
            $videos_show_embedding = (int) $_REQUEST['videos_show_embedding'];
            $videos_embed_size_w = (int) $_REQUEST['videos_embed_size_w'];
            $videos_embed_size_h = (int) $_REQUEST['videos_embed_size_h'];

            // audio
            $enable_preview_audio = (int) $_REQUEST['enable_preview_audio'];
            $preview_audio_player = $_REQUEST['preview_audio_player'] && ThemeHelper::getCurrentProductType() === 'file_hosting' ? $_REQUEST['preview_audio_player'] : $default_preview_audio_player;
            $preview_audio_ext = trim(strtolower($_REQUEST['preview_audio_ext']));
            $audio_autoplay = (int) $_REQUEST['audio_autoplay'];
            $audio_show_embedding = (int) $_REQUEST['audio_show_embedding'];
            $audio_embed_size_w = (int) $_REQUEST['audio_embed_size_w'];
            $audio_embed_size_h = (int) $_REQUEST['audio_embed_size_h'];

            // text files & code
            $enable_preview_text = (int) $_REQUEST['enable_preview_text'];
            $syntax_highlight_text = (int) $_REQUEST['syntax_highlight_text'];
            $preview_text_ext = trim(strtolower($_REQUEST['preview_text_ext']));
            $text_show_embedding = (int) $_REQUEST['text_show_embedding'];
            $text_embed_document_size_w = (int) $_REQUEST['text_embed_document_size_w'];
            $text_embed_document_size_h = (int) $_REQUEST['text_embed_document_size_h'];

            // validate submission
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t('no_changes_in_demo_mode', 'No change permitted in demo mode.'));
            } elseif (strlen($supported_image_types) == 0) {
                AdminHelper::setError(AdminHelper::t("plugin_image_viewer_please_set_the_supported_formats", "Please set which formats to support in the format - jpg,png,gif"));
            } elseif ($watermark_enabled == 1) {
                // new uploaded image
                if (strlen($_FILES["watermark_image"]["name"])) {
                    // make sure we've got an image
                    $file = $_FILES["watermark_image"]["name"];
                    $extension = strtolower(end(explode(".", $file)));
                    if ($extension != 'png') {
                        AdminHelper::setError(AdminHelper::t("plugin_image_viewer_watermark_must_be_a_png", "Watermark image must be a png image."));
                    } else {
                        $watermark_filename = $_FILES["watermark_image"]["name"];
                        $watermark_contents = file_get_contents($_FILES["watermark_image"]["tmp_name"]);
                    }
                }
            } else {
                $watermark_contents = '';
                $watermark_filename = '';
            }

            // update the settings
            if (AdminHelper::isErrors() == false) {
                // compile new settings
                $settingsArr = [];
                $settingsArr['allow_direct_links'] = (int) $allow_direct_links;
                $settingsArr['show_file_details_outside_account'] = (int) $show_file_details_outside_account;
                $settingsArr['show_similar_items'] = (int) $show_similar_items;
                $settingsArr['non_show_viewer'] = (int) $non_show_viewer;
                $settingsArr['free_show_viewer'] = (int) $free_show_viewer;
                $settingsArr['paid_show_viewer'] = (int) $paid_show_viewer;

                // images
                $settingsArr['enable_preview_image'] = (int) $enable_preview_image;
                $settingsArr['preview_image_show_thumb'] = (int) $preview_image_show_thumb;
                $settingsArr['auto_rotate'] = (int) $auto_rotate;
                $settingsArr['image_library'] = $image_library;
                $settingsArr['image_size_w'] = (int) $image_size_w;
                $settingsArr['image_size_h'] = (int) $image_size_h;
                $settingsArr['watermark_enabled'] = (int) $watermark_enabled;
                $settingsArr['watermark_position'] = $watermark_position;
                $settingsArr['watermark_padding'] = (int) $watermark_padding;
                $settingsArr['images_show_embedding'] = (int) $images_show_embedding;
                $settingsArr['thumb_size_w'] = (int) $thumb_size_w;
                $settingsArr['thumb_size_h'] = (int) $thumb_size_h;
                $settingsArr['thumb_resize_method'] = $thumb_resize_method;
                $settingsArr['show_download_sizes'] = (int) $show_download_sizes;
                $settingsArr['image_quality'] = (int) $image_quality;
                $settingsArr['supported_image_types'] = $supported_image_types;
                $settingsArr['animate_gif_thumbnails'] = (int) $animate_gif_thumbnails;

                // documents
                $settingsArr['enable_preview_document'] = (int) $enable_preview_document;
                $settingsArr['preview_document_pdf_thumbs'] = (int) $preview_document_pdf_thumbs;
                $settingsArr['preview_document_ext'] = $preview_document_ext;
                $settingsArr['documents_show_embedding'] = (int) $documents_show_embedding;
                $settingsArr['documents_embed_document_size_w'] = (int) $documents_embed_document_size_w;
                $settingsArr['documents_embed_document_size_h'] = (int) $documents_embed_document_size_h;

                // video
                $settingsArr['enable_preview_video'] = (int) $enable_preview_video;
                $settingsArr['preview_video_player'] = $preview_video_player;
                $settingsArr['preview_video_ext'] = $preview_video_ext;
                $settingsArr['videos_autoplay'] = (int) $videos_autoplay;
                $settingsArr['videos_show_embedding'] = (int) $videos_show_embedding;
                $settingsArr['videos_embed_size_w'] = (int) $videos_embed_size_w;
                $settingsArr['videos_embed_size_h'] = (int) $videos_embed_size_h;

                // audio
                $settingsArr['enable_preview_audio'] = (int) $enable_preview_audio;
                $settingsArr['preview_audio_player'] = $preview_audio_player;
                $settingsArr['preview_audio_ext'] = $preview_audio_ext;
                $settingsArr['audio_autoplay'] = (int) $audio_autoplay;
                $settingsArr['audio_show_embedding'] = (int) $audio_show_embedding;
                $settingsArr['audio_embed_size_w'] = (int) $audio_embed_size_w;
                $settingsArr['audio_embed_size_h'] = (int) $audio_embed_size_h;

                // text files & code
                $settingsArr['enable_preview_text'] = (int) $enable_preview_text;
                $settingsArr['syntax_highlight_text'] = (int) $syntax_highlight_text;
                $settingsArr['preview_text_ext'] = $preview_text_ext;
                $settingsArr['text_show_embedding'] = (int) $text_show_embedding;
                $settingsArr['text_embed_document_size_w'] = (int) $text_embed_document_size_w;
                $settingsArr['text_embed_document_size_h'] = (int) $text_embed_document_size_h;

                $settingsArr['caching'] = 1;

                // update the plugin settings
                $plugin->plugin_enabled = $plugin_enabled;
                $plugin->plugin_settings = json_encode($settingsArr);
                $plugin->save();

                // update image watermark, delete existing
                $db->query("DELETE "
                        . "FROM plugin_filepreviewer_watermark "
                        . "WHERE category = 'images'");
                $pluginFilepreviewerWatermark = PluginFilepreviewerWatermark::create();
                $pluginFilepreviewerWatermark->file_name = $watermark_filename;
                $pluginFilepreviewerWatermark->category = 'images';
                $pluginFilepreviewerWatermark->image_content = $watermark_contents;
                $pluginFilepreviewerWatermark->save();

                // set onscreen alert
                PluginHelper::loadPluginConfigurationFiles(true);

                // user action logs
                UserActionLogHelper::logAdmin('Edited "'.$folderName.'" plugin settings', 'ADMIN', 'UPDATE', [
                    'plugin' => $folderName,
                    'data' => UserActionLogHelper::getChangedData($oldPluginSettings, $settingsArr),
                ]);

                AdminHelper::setSuccess('Plugin settings updated.');
            }
        }

        // load template
        return $this->render('admin/plugin_settings.html', array(
                    'pluginName' => $plugin->plugin_name,
                    'yesNoOptions' => array(0 => 'No', 1 => 'Yes'),
                    'plugin_enabled' => $plugin_enabled,
                    'allow_direct_links' => $allow_direct_links,
                    'show_file_details_outside_account' => $show_file_details_outside_account,
                    'show_similar_items' => $show_similar_items,
                    'non_show_viewer' => $non_show_viewer,
                    'free_show_viewer' => $free_show_viewer,
                    'paid_show_viewer' => $paid_show_viewer,
                    // images
                    'enable_preview_image' => $enable_preview_image,
                    'preview_image_show_thumb' => $preview_image_show_thumb,
                    'auto_rotate' => $auto_rotate,
                    'image_library' => $image_library,
                    'image_size_w' => $image_size_w,
                    'image_size_h' => $image_size_h,
                    'watermark_enabled' => $watermark_enabled,
                    'watermark_position' => $watermark_position,
                    'watermark_padding' => $watermark_padding,
                    'watermark_padding' => $watermark_padding,
                    'images_show_embedding' => $images_show_embedding,
                    'thumb_size_w' => $thumb_size_w,
                    'thumb_size_h' => $thumb_size_h,
                    'thumb_resize_method' => $thumb_resize_method,
                    'show_download_sizes' => $show_download_sizes,
                    'image_quality' => $image_quality,
                    'supported_image_types' => $supported_image_types,
                    'animate_gif_thumbnails' => $animate_gif_thumbnails,
                    'watermark_contents' => $watermark_contents,
                    'watermark_filename' => $watermark_filename,
                    'watermark_contents_base64' => strlen($watermark_contents) ? base64_encode($watermark_contents) : '',
                    // documents
                    'enable_preview_document' => $enable_preview_document,
                    'preview_document_pdf_thumbs' => $preview_document_pdf_thumbs,
                    'preview_document_ext' => $preview_document_ext,
                    'documents_show_embedding' => $documents_show_embedding,
                    'documents_embed_document_size_w' => $documents_embed_document_size_w,
                    'documents_embed_document_size_h' => $documents_embed_document_size_h,
                    // videos
                    'enable_preview_video' => $enable_preview_video,
                    'preview_video_player' => $preview_video_player,
                    'preview_video_ext' => $preview_video_ext,
                    'videos_autoplay' => $videos_autoplay,
                    'videos_show_embedding' => $videos_show_embedding,
                    'videos_embed_size_w' => $videos_embed_size_w,
                    'videos_embed_size_h' => $videos_embed_size_h,
                    'videoPlayers' => $videoPlayers,
                    // audio
                    'enable_preview_audio' => $enable_preview_audio,
                    'preview_audio_player' => $preview_audio_player,
                    'preview_audio_ext' => $preview_audio_ext,
                    'audio_autoplay' => $audio_autoplay,
                    'audio_show_embedding' => $audio_show_embedding,
                    'audio_embed_size_w' => $audio_embed_size_w,
                    'audio_embed_size_h' => $audio_embed_size_h,
                    'audioPlayers' => $audioPlayers,
                    // text files & code
                    'enable_preview_text' => $enable_preview_text,
                    'syntax_highlight_text' => $syntax_highlight_text,
                    'preview_text_ext' => $preview_text_ext,
                    'text_show_embedding' => $text_show_embedding,
                    'text_embed_document_size_w' => $text_embed_document_size_w,
                    'text_embed_document_size_h' => $text_embed_document_size_h,
                    // other
                    'watermark_position_options' => $watermarkPositionOptions,
                    'gdAvailable' => $gdAvailable,
                    'imagemagickAvailable' => $imagemagickAvailable,
                    'formatsArr' => $formatsArr,
                    'thumbResizeMethodOptions' => array(
                        'cropped' => 'Cropped (no white padding)',
                        'padded' => 'Fixed Size (padded white so image is always the size above)',
                    ),
                    'libraryOptions' => array(
                        'gd' => 'GD ' . ($gdAvailable == true ? '(available)' : '(not installed - request that your host enables this module)'),
                        'imagemagick' => 'Imagemagick ' . ($imagemagickAvailable == true ? '(available)' : '(not installed - request that your host enables this module)'),
                    ),
                        ), PLUGIN_DIRECTORY_ROOT . $folderName . '/views');
    }

}
