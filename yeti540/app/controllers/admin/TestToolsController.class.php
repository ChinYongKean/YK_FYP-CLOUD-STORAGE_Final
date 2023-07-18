<?php

namespace App\Controllers\admin;

use App\Helpers\CoreHelper;
use App\Helpers\FileServerHelper;
use App\Helpers\FileHelper;
use App\Helpers\StatsHelper;
use App\Helpers\ValidationHelper;
use App\Helpers\ServerResourceHelper;
use App\Services\Backup;
use App\Services\Password;
use Omnipay\Omnipay;

class TestToolsController extends AdminBaseController
{

    public function testApacheXsendfile() {
        // admin restrictions
        $this->restrictAdminAccess();

        // run test
        $str = "Checking if xsendfile module is enabled... ";
        if (FileServerHelper::apacheXSendFileEnabled(FileHelper::getCurrentServerId()) == false) {
            $str .= "Not Found!<br/><br/>";
            $str .= "Enable xsendfile within your Apache config.<br/><br/>";
            $str .= "Install on Ubuntu with the following the restart Apache:<br/><br/>";
            $str .= "apt-get install libapache2-mod-xsendfile<br/><br/>";
            $str .= "Some resources:<br/>";
            $str .= "<ul>";
            $str .= "<li><a href='https://tn123.org/mod_xsendfile/'>https://tn123.org/mod_xsendfile/</a></li>";
            $str .= "</ul>";

            return $this->render('admin/test_tool_output.html', array(
                    'html_content' => $str,
                                ));
        }

        // module found
        $str .= "Found!<br/><br/>";

        $str .= "Ensure you have set the following path in your servers Apache config file, then restarted Apache:<br/><br/>";

        $str .= "XSendFilePath " . DOC_ROOT . "/";

        $str .= "<br/><br/>";

        $str .= "So your Apache config file will look similar to this:<br/><br/>";

        $str .= ValidationHelper::safeOutputToScreen("<VirtualHost *:80>") . "<br/>";
        $str .= "&nbsp;&nbsp;&nbsp;&nbsp;" . ValidationHelper::safeOutputToScreen("XSendFilePath " . DOC_ROOT) . "/<br/>";
        $str .= "&nbsp;&nbsp;&nbsp;&nbsp;...<br/>";
        $str .= ValidationHelper::safeOutputToScreen("</VirtualHost>") . "<br/><br/>";

        $str .= "If the above is set, your server should be using Apache to download files rather than PHP. Note this will only work for non speed restricted files.";

        return $this->render('admin/test_tool_output.html', array(
                    'html_content' => $str,
                                ));
    }

    public function testCreateDatabaseBackup() {
        // admin restrictions
        $this->restrictAdminAccess();

        $backup = new Backup();
        $str = $backup->backupDatabase();
        if($str === 1) {
            $str = 'Database backup created.';
        }
        else {
            $str = 'Database backup failed.';
        }

        return $this->render('admin/test_tool_output.html', array(
                    'html_content' => $str,
                                ));
    }

    public function testCurl() {
        // admin restrictions
        $this->restrictAdminAccess();

        if (!function_exists('curl_init')) {
            return $this->render('admin/test_tool_output.html', array(
                    'html_content' => "cURL library cannot be found. Make sure it is installed.",
                                ));
        }

        $agent = "Mozilla/5.0 (Windows; U; Windows NT 5.0; en-US; rv:1.4) Gecko/20030624 Netscape/7.1 (ax)";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.google.com/adsense/");
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $returned = curl_exec($ch);
        curl_close($ch);

        if ($returned == null) {
            return $this->render('admin/test_tool_output.html', array(
                    'html_content' => "Your cURL does not allow https protocol. Make sure OpenSSL is installed.<br/>Details Error :<br/><b>" . curl_error($ch) . "</b>",
                                ));
        }

        return $this->render('admin/test_tool_output.html', array(
                    'html_content' => "Your cURL is working properly.",
                                ));
    }

    public function testEmail() {
        // admin restrictions
        $this->restrictAdminAccess();

        // prepare the variables
        $str = '';
        if (isset($_REQUEST['submitted'])) {
            $toEmail = trim($_REQUEST['email']);
            if(strlen($toEmail)) {
                $subject = "Test email from email_test";
                $plainMsg = "Test email content";

                // send the email
                CoreHelper::sendHtmlEmail($toEmail, $subject, $plainMsg, SITE_CONFIG_DEFAULT_EMAIL_ADDRESS_FROM, $plainMsg, true);

                $str .= '- Email requested to '.$toEmail.'. Ensure you check spam folders if you don\'t see it within your inbox.<br/><br/><br/>';
            }
        }
        
        $str .= 'Use the form below to test that emails are being sent from your server:<br/><br/>';
        $str .= '<form action="email" method="post">';
        $str .= '    Email Address: ';
        $str .= '    <input type="text" name="email"/>';
        $str .= '    <input type="hidden" value="1" name="submitted"/>';
        $str .= '    <input type="submit" value="Send Test Email" name="submit"/>';
        $str .= '</form>';
        
        return $this->render('admin/test_tool_output.html', array(
                    'html_content' => $str,
                                ));
    }

    public function testEncryption() {
        // admin restrictions
        $this->restrictAdminAccess();

        if (!function_exists('openssl_encrypt')) {
            return $this->render('admin/test_tool_output.html', array(
                    'html_content' => "openssl_encrypt() cannot be found. Make sure it is installed.",
                                ));
        }

        $rawValue = _CONFIG_SITE_HOST_URL;
        $str = "Value to be encrypted: " . $rawValue . "<br/><br/>";

        $encrypted = CoreHelper::encryptValue($rawValue);
        $str .= "Encrypted: " . $encrypted . "<br/><br/>";

        $decrypted = CoreHelper::decryptValue($encrypted);
        $str .= "Decrypted: " . $decrypted . "<br/><br/>";

        return $this->render('admin/test_tool_output.html', array(
                    'html_content' => $str,
                                ));
    }

    public function testFlysystem() {
        // admin restrictions
        $this->restrictAdminAccess();

        // sample structure for the file_server_container.expected_config_json column
        $arr = array(
            'username' => array('label' => 'Rackspace Username', 'type' => 'text', 'default' => ''),
            'apiKey' => array('label' => 'API Key', 'type' => 'text', 'default' => ''),
            'container' => array('label' => 'Cloud Files Container', 'type' => 'text', 'default' => ''),
            'region' => array('label' => 'Container Region', 'type' => 'select', 'default' => 'IAD', 'option_values' => array(
                    'IAD' => 'Nothern Virginia (IAD)',
                    'DFW' => 'Dallas (DFW)',
                    'HKG' => 'Hong Kong (HKG)',
                    'SYD' => 'Sydney (SYD)',
                    'LON' => 'London (LON)',
                )),
        );

        $str = json_encode($arr);
        $str .= "<br/><br/>Done.";

        return $this->render('admin/test_tool_output.html', array(
                    'html_content' => $str,
                                ));
    }

    public function testGeneratePasswordHash() {
        // admin restrictions
        $this->restrictAdminAccess();

        // prepare the variables
        $rawPassword = "newpassword";
        
        $str = 'Raw Password:<br/>'.$rawPassword.'<br/><br/>';
        $str .= 'Encoded Password:<br/>'.Password::createHash($rawPassword).'<br/><br/>';
        
        return $this->render('admin/test_tool_output.html', array(
                    'html_content' => $str,
                                ));
    }

    public function testGeocodeIp() {
        // admin restrictions
        $this->restrictAdminAccess();

        // the IP address to check
        $ipAddress = isset($_REQUEST['ip']) ? $_REQUEST['ip'] : StatsHelper::getIP();

        $str = "IP Address: " . $ipAddress . "<br/>";
        $str .= "Country: " . StatsHelper::getCountry($ipAddress);

        return $this->render('admin/test_tool_output.html', array(
                    'html_content' => $str,
                                ));
    }

    public function testIp() {
        // admin restrictions
        $this->restrictAdminAccess();

        $str = "The script sees your IP address as: " . StatsHelper::getIP() . "<br/>";
        $str .= "Your country is: " . StatsHelper::getCountry(StatsHelper::getIP()) . "<br/><br/>";

        $str .= "Using core PHP functions, your IP is being reported as: " . $_SERVER['REMOTE_ADDR'];

        return $this->render('admin/test_tool_output.html', array(
                    'html_content' => $str,
                                ));
    }

    public function testOmnipayPayment() {
        // admin restrictions
        $this->restrictAdminAccess();
        
        return $this->render('admin/test_tool_output.html', array(
                    'html_content' => 'Test disabled. Please see /app/controllers/admin/TestToolController.class.php to set credentials and enable.',
                                ));

        $gateway = Omnipay::create('Mollie');
        $gateway->setApiKey('DISABLED');
        $response = $gateway->purchase(array('amount' => '10.00', 'currency' => 'USD', 'description' => 'test', 'returnUrl' => 'test'))->send();

        if ($response->isRedirect()) {
            // redirect to offsite payment gateway
            $response->redirect();
        }
        elseif ($response->isSuccessful()) {
            // payment was successful: update database
            return $this->render('admin/test_tool_output.html', array(
                    'html_content' => print_r($response, true),
                                ));
        }
        else {
            // payment failed: display message to customer
            return $this->render('admin/test_tool_output.html', array(
                    'html_content' => $response->getMessage(),
                                ));
        }
    }

    public function testOutputBuffering() {
        // admin restrictions
        $this->restrictAdminAccess();

        // mod security may also cause this to fail
        // php.ini - output_buffering = Off zlib.output_compression = Off
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);
        @ob_end_clean();
        ob_implicit_flush(1);
        header('Content-type: text/html; charset=utf-8');
        // 1KB of initial data, required by Webkit browsers
        echo "<span>" . str_repeat(" ", 1000) . "</span>";
        echo 'Begin ...<br />';
        for ($i = 0; $i < 10; $i++) {
            echo $i . '<br />';
            ob_end_flush();
            ob_flush();
            flush();
            ob_start();
            sleep(1);
        }
        echo 'End ...<br />';
        die();
    }

    public function testPermissions() {
        // admin restrictions
        $this->restrictAdminAccess();

        // list of files/folders to check
        $files = array('files/', 'files/_tmp', 'plugins/', 'cache', 'logs');

        $str = "This script tests for the required permissions within the script:<br/><br/>";

        foreach ($files AS $file) {
            $realPath = DOC_ROOT . '/' . $file;
            $color = 'green';
            $msg = 'Writable';
            if (!is_writable($realPath)) {
                $color = 'red';
                $msg = 'Not Writable';
            }

            $str .= '<span style="color: ' . $color . ';">' . $realPath . ' - ' . $msg . '</span>';

            $str .= "<br/>";
        }

        return $this->render('admin/test_tool_output.html', array(
                    'html_content' => $str,
                                ));
    }

    public function testPorts() {
        // admin restrictions
        $this->restrictAdminAccess();

        // test access to ports
        $host = _CONFIG_SITE_HOST_URL;
        $ports = array(21, 22, 80, 443);

        $str = '';
        foreach ($ports as $port) {
            $connection = @fsockopen($host, $port, $errno, $errstr, 15);
            if (is_resource($connection)) {
                $str .= $host . ':' . $port . ' is open.' . "<br/>";
                fclose($connection);
            }
            else {
                $str .= $host . ':' . $port . ' is not responding.' . "<br/>";
            }
        }

        return $this->render('admin/test_tool_output.html', array(
                    'html_content' => $str,
                                ));
    }

    public function testUpload() {
        // admin restrictions
        $this->restrictAdminAccess();

        // handle submission
        $str = '';
        if (isset($_REQUEST['submitted'])) {
            $str .= "<strong>Submitted file:</strong><br/><br/>";
            $str .= "If the file size is zero of the path does not exist below, there is an issue with your server configuration for file uploads. Please contact your host or server admin for further information.<br/>";
            $str .= "<pre>";
            $str .= print_r($_FILES, true);
            $str .= "</pre>";
            $str .= "<br/>";

            if (((int) $_FILES['fileToUpload']['size'] > 0) && (strlen($_FILES['fileToUpload']['tmp_name']))) {
                $str .= "<span style='color: #ffffff; padding: 10px; background-color: green; width: 97%; display: block;'>SUCCESS! We found the tmp file and a filesize, it looks like uploads are working fine on your server.</span>";
            }
            else {
                $str .= "<span style='color: #ffffff; padding: 10px; background-color: red; font-weight: bold; width: 97%; display: block;'>ERROR! We could not find the uploaded file. Please contact your server admin to investigate what may be the cause.</span>";
            }
            $str .= "<br/><br/><br/>";
        }

        $str .= 'Use the form below to test that file uploads are working on your server:<br/><br/>';
        $str .= '<form action="upload" method="post" enctype="multipart/form-data">';
        $str .= '    Select file to upload: (< 2MB)';
        $str .= '    <input type="file" name="fileToUpload" id="fileToUpload"/>';
        $str .= '    <input type="hidden" value="1" name="submitted"/>';
        $str .= '    <input type="submit" value="Upload File" name="submit"/>';
        $str .= '</form>';

        return $this->render('admin/test_tool_output.html', array(
                    'html_content' => $str,
                                ));
    }
    
    public function testFileEncryption() {
        // admin restrictions
        $this->restrictAdminAccess();

        if (!function_exists('openssl_encrypt')) {
            return $this->render('admin/test_tool_output.html', array(
                    'html_content' => "openssl_encrypt() cannot be found. Make sure it is installed.",
                                ));
        }

        $str = '';

        $filePathSource = DOC_ROOT.'/cache/test_encryption_source.txt';
        $filePathDest = DOC_ROOT.'/cache/test_encryption_dest.txt';
        $str = "File to be encrypted: " . $filePathSource . "<br/><br/>";

        if(!file_exists($filePathSource)) {
            $str .= "Error: File to encrypt not found, please upload it to the above path.<br/><br/>";
        }
        else {
            $str .= "Filesize: " . CoreHelper::formatSize(filesize($filePathSource)) . "<br/><br/>";
            
            $started = time();
            $str .= "Started encryption: " . CoreHelper::formatDate($started) . "<br/>";
            CoreHelper::encryptFile($filePathSource, $filePathDest);
            $finished = time();
            $str .= "Finished encryption: " . CoreHelper::formatDate($finished) . "<br/>";
            $str .= "Total time: " . ($finished-$started) . " seconds.<br/>";
        }

        return $this->render('admin/test_tool_output.html', array(
                    'html_content' => $str,
                                ));
    }

    public function testServerResourceUsage() {
        // admin restrictions
        $this->restrictAdminAccess();

        return $this->render('admin/test_tool_output.html', array(
            'html_content' => '<pre>'.print_r(ServerResourceHelper::getAllResourceStats(), true).'</pre>',
        ));
    }

    public function testServerVariables() {
        // admin restrictions
        $this->restrictAdminAccess();

        // get server variables
        $str = '<pre>' . print_r($_SERVER, true) . '</pre>';

        return $this->render('admin/test_tool_output.html', array(
            'html_content' => $str,
        ));
    }

}
