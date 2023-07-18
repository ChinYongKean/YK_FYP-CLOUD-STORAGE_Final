<?php

define('CRON_PATH', realpath(dirname(__FILE__).'/../../app/tasks'));
?>
<a name="top"></a>
<h2>Manual Installation: (New Install)</h2>
<table border=0 width="100%">
    <tbody>
    <tr>
        <td>
            <ul>
                <li><a href="#Step_1"><span>Step 1. Uncompress Downloaded Zip File.</span></a>
                <li><a href="#Step_2"><span>Step 2. Create Database.</span></a>
                <li><a href="#Step_3"><span>Step 3. Import Database Structure.</span></a>
                <li><a href="#Step_4"><span>Step 4. Update Config File.</span></a>
                <li><a href="#Step_5"><span>Step 5. Upload Files.</span></a>
                <li><a href="#Step_6"><span>Step 6. Set Folder Permissions.</span></a>
                <li><a href="#Step_7"><span>Step 7. Setup Cron Tasks.</span></a>
                <li><a href="#Step_8"><span>Step 8. Admin Area Access Details.</span></a>
            </ul>
        </td>
    </tr>
    </tbody>
</table>

<br/>

<div style="float:right;">[<a href="#top">top</a>]</div>
<a name="Step_1"></a>
<h3><b>Step 1. Uncompress Downloaded Zip File.</b></h3>
<hr>
<p>Open the downloaded zip file on your computer and extract the files into a new folder on your desktop. If you can't
    open zip files you may need to download a zip client such as <a href='http://www.winzip.com'
                                                                    target='_blank'>WinZip</a>.</p>
<p>In addition, make sure that the .htaccess file in the root of the zip file is also extracted. Some operating systems
    hide files starting a dot by default. This file is needed for the mod_rewrite rules in the script and it wont work
    without it. You may need to amend your OS settings to show these files if you can't see it in the zip archive.</p>
<p><br/></p>

<div style="float:right;">[<a href="#top">top</a>]</div>
<a name="Step_2"></a>
<h3><b>Step 2. Create Database.</b></h3>
<hr>
<p>Using your hosting control panel, login and create a new MySQL database. Then create a database user and assign full
    privileges for the user on the database. Note the details for the next stage.</p>
<p><br/></p>

<div style="float:right;">[<a href="#top">top</a>]</div>
<a name="Step_3"></a>
<h3><b>Step 3. Import Database Structure.</b></h3>
<hr>
<p>Within your hosting control panel, load phpMyAdmin and select your new database. In the right-hand section click on
    'import'. Attach the .sql file located at /install/resources/database.sql and submit the form. Your database should
    now be ready.</p>
<p><br/></p>

<div style="float:right;">[<a href="#top">top</a>]</div>
<a name="Step_4"></a>
<h3><b>Step 4. Update Config File.</b></h3>
<hr>
<p>Update "_config.inc.php" in your extract script code with your site url and the full path to the root of the script.
    In most instances, this will be your domain name (for _CONFIG_SITE_HOST_URL & _CONFIG_SITE_FULL_URL) in the format
    www.mydomain.com. Don't include the http:// or the trailing forward slash.</p>
<p>Set your database connection details in the same file. (host, user, password &amp; db name)</p>
<p><br/></p>

<div style="float:right;">[<a href="#top">top</a>]</div>
<a name="Step_5"></a>
<h3><b>Step 5. Upload Files.</b></h3>
<hr>
<p>Using an FTP client such as <a href="https://filezilla-project.org/" target="_blank">FileZilla</a>, upload all the
    files to your webroot (normally public_html folder) apart from the 'install' folder.</p>
<p><br/></p>

<div style="float:right;">[<a href="#top">top</a>]</div>
<a name="Step_6"></a>
<h3><b>Step 6. Set Folder Permissions.</b></h3>
<hr>
<p>Using your FTP client, set permissions to CHMOD 755 on the following folders: '/files', '/logs', '/cache' &amp;
    '/plugins'.</p>
<p><br/></p>

<div style="float:right;">[<a href="#top">top</a>]</div>
<a name="Step_7"></a>
<h3><b>Step 7. Setup Cron Tasks.</b></h3>
<hr>
<p><?php
    echo INSTALLER_APPLICATION_NAME; ?> uses a number of cron (background) tasks to ensure redundant files are deleted,
    accounts are auto downgraded etc. Details of these are below. You can leave these until later if you want to test
    the installation first. See <a
            href="http://www.cyberciti.biz/faq/how-do-i-add-jobs-to-cron-under-linux-or-unix-oses/" target="_blank">here
        for more information</a> on cron tasks.</p>
<ul style="font-family: courier,Consolas,monospace;">
    <li>0 0 * * * php <?php
        echo CRON_PATH; ?>/auto_prune.cron.php >> /dev/null 2>&amp;1
    </li>
    <li>0 0 * * * php <?php
        echo CRON_PATH; ?>/create_internal_notifications.cron.php >> /dev/null 2>&amp;1
    </li>
    <li>0 * * * * php <?php
        echo CRON_PATH; ?>/delete_redundant_files.cron.php >> /dev/null 2>&amp;1
    </li>
    <li>0 0 * * * php <?php
        echo CRON_PATH; ?>/downgrade_accounts.cron.php >> /dev/null 2>&amp;1
    </li>
    <li>*/5 * * * * php <?php
        echo CRON_PATH; ?>/process_file_queue.cron.php >> /dev/null 2>&amp;1
    </li>
    <li>0 1 * * * php <?php
        echo CRON_PATH; ?>/create_email_notifications.cron.php >> /dev/null 2>&amp;1
    </li>
    <li>* * * * * php <?php
        echo CRON_PATH; ?>/process_server_resources.cron.php >> /dev/null 2>&amp;1
    </li>
    <li>* * * * * php <?php
        echo CRON_PATH; ?>/process_server_monitoring.cron.php >> /dev/null 2>&amp;1
    </li>
</ul>
<p><br/></p>

<div style="float:right;">[<a href="#top">top</a>]</div>
<a name="Step_7"></a>
<h3><b>Step 8. Admin Area Access Details.</b></h3>
<hr>
<p>The admin area can be accessed by adding "/admin/" onto the domain - i.e. yourdomain.com/admin/</p>
<p>Admin area access details:<br/>
    - user: admin<br/>
    - pass: password</p>
<p>We'd recommend that you change the admin password to something more secure on first login.</p>
<p><br/></p>

Congratulations, you now completed the installation. Feel free to contact us or post of our <a
        href="http://mfscripts.com" target="_blank">forum</a> if you have any problems.
