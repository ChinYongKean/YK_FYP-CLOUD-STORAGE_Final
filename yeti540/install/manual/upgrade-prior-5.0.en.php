<?php

define('CRON_PATH', realpath(dirname(__FILE__).'/../../app/tasks'));
?>
<a name="top"></a>
<h2>Upgrade Existing Install - Your Current Version 4.x</h2>
<p>Use this method if you are currently using Yetishare and on a version in the v4.x range.</p>
<table border=0 width="100%">
    <tbody>
    <tr>
        <td>
            <ul>
                <li><a href="#Step_10"><span>Step 1. Backup Existing Code &amp; Database.</span></a>
                <li><a href="#Step_20"><span>Step 2. Uncompress Downloaded Zip File.</span></a>
                <li><a href="#Step_30"><span>Step 3. Import Database Patches.</span></a>
                <li><a href="#Step_40"><span>Step 4. Upload Files.</span></a>
                <li><a href="#Step_41"><span>Step 5. Restore Existing Cache, Logs and Files folders.</span></a>
                <li><a href="#Step_50"><span>Step 6. Set Folder Permissions.</span></a>
                <li><a href="#Step_60"><span>Step 7. Setup Cron Tasks.</span></a>
                <li><a href="#Step_80"><span>Step 8. Update The Config File.</span></a>
                <li><a href="#Step_90"><span>Step 9. Nginx Users - Update Rewrite Rules</span></a>
            </ul>
        </td>
    </tr>
    </tbody>
</table>

<br/>

<div style="float:right;">[<a href="#top">top</a>]</div>
<a name="Step_10"></a>
<h3><b>Step 1. Backup Existing Code &amp; Database.</b></h3>
<hr>
<p><strong style="color: red;">VERY IMPORTANT!</strong> - Before you do any changes to the code at all, ensure you've
    taken a backup copy of the site, files &amp; database.</p>
<p>Move all your current code and files into a temporary folder on your hosting. We create a new folder called
    "_OLD_SITE" and move the files into there.</p>
<p><br/></p>

<div style="float:right;">[<a href="#top">top</a>]</div>
<a name="Step_20"></a>
<h3><b>Step 2. Uncompress Downloaded Zip File.</b></h3>
<hr>
<p>Open the downloaded zip file on your computer and extract the files into a new folder on your desktop. If you can't
    open zip files you may need to download a zip client such as <a href='http://www.winzip.com'
                                                                    target='_blank'>WinZip</a>.</p>
<p><br/></p>

<div style="float:right;">[<a href="#top">top</a>]</div>
<a name="Step_30"></a>
<h3><b>Step 3. Import Database Patches.</b></h3>
<hr>
<p>Within your hosting control panel, load phpMyAdmin and select your new database. In the right-hand section click on
    'import'. Attach the the relevant sql patches from the directory `/install/resources/upgrade_sql_statements/` and
    submit the form. Choose the patches between your current script version number and the latest, ensuring you do them
    in version number order.</p>
<p><br/></p>

<div style="float:right;">[<a href="#top">top</a>]</div>
<a name="Step_40"></a>
<h3><b>Step 4. Upload Files.</b></h3>
<hr>
<p>Using an FTP client such as <a href="https://filezilla-project.org/" target="_blank">FileZilla</a>, upload all the
    files to your webroot (normally public_html folder) apart from the '/install' folder. <strong>Important</strong>
    This should NOT be over the top of your existing code as it was moved in step 1. There are significant changes in
    this release and the code structure has completely changed.</p>
<p><br/></p>

<div style="float:right;">[<a href="#top">top</a>]</div>
<a name="Step_50"></a>
<h3><b>Step 5. Restore Existing Cache, Logs and Files folders.</b></h3>
<hr>
<p>From your "_OLD_SITE" backup, copy the following folders into their new location - these have changed in the new
    release.</p>
<ul>
    <li>/_OLD_SITE/files/ => /files/</li>
    <li>/_OLD_SITE/core/cache/ => /cache/</li>
    <li>/_OLD_SITE/core/logs/ => /logs/</li>
</ul>
<p><br/></p>

<div style="float:right;">[<a href="#top">top</a>]</div>
<a name="Step_50"></a>
<h3><b>Step 6. Set Folder Permissions.</b></h3>
<hr>
<p>Using your FTP client, set permissions to CHMOD 755 on the following folders: '/files', '/logs', '/cache' &amp;
    '/plugins'.</p>
<p><br/></p>

<div style="float:right;">[<a href="#top">top</a>]</div>
<a name="Step_60"></a>
<h3><b>Step 7. Setup Cron Tasks.</b></h3>
<hr>
<p>The path to cron files has been amended in this release, details of these are below. You can leave these until later
    if you want to test the upgrade first. See <a
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
<a name="Step_80"></a>
<h3><b>Step 8. Update The Config File.</b></h3>
<hr>
<p>Populate the credentials in the _config.inc.php file. You can find these in /_OLD_SITE/_config.inc.php.</p>
<p><br/></p>

<div style="float:right;">[<a href="#top">top</a>]</div>
<a name="Step_90"></a>
<h3><b>Step 9. Nginx Users - Update Rewrite Rules.</b></h3>
<hr>
<p>- If you're using Nginx, ensure you remove any rewrite rules starting with: (alternatively see ___NGINX_RULES.txt in
    the code root for the full rules)</p>
<ul>
    <li>rewrite ^/app/</li>
    <li>rewrite ^/api/</li>
</ul>
<p><br/></p>

Congratulations, you now completed the upgrade. Feel free to contact us or post of our <a
        href="https://mfscripts.com" target="_blank">forum</a> if you have any problems.
