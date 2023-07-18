<?php

namespace App\Controllers;

use App\Models\File;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class AccountDownloadController extends AccountController
{
    /**
     * Called from JS in the file manager. Will only allow account owner to download the file
     *
     * @param int $fileId
     * @param string $fileHash
     * @param string $filename
     * @return RedirectResponse|Response
     */
    public function directDownload(int $fileId, string $fileHash, string $filename)
    {
        // get params for later
        $Auth = $this->getAuth();

        // load the file and make sure user owns it
        $file = File::loadOneByClause('id = :file_id AND unique_hash = :unique_hash', [
            'file_id' => $fileId,
            'unique_hash' => $fileHash,
        ]);
        if (!$file) {
            return $this->render404();
        }

        // check file permissions, allow owners and admin/mods
        if ((($file->userId != $Auth->id) && ($Auth->level_id < 10))) {
            // account owner only
            return $this->render404();
        }

        // if we've got this far, the user can access the file
        return $this->redirect($file->generateDirectDownloadUrl());
    }
}
