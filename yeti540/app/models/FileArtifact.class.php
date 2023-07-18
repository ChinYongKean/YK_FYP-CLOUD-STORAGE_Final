<?php

namespace App\Models;

use App\Core\Model;
use App\Helpers\CoreHelper;
use App\Core\Database;

class FileArtifact extends Model
{
    public function getFormattedFilesize(): string {
        return CoreHelper::formatSize($this->file_size);
    }

    public function getStorageServers(): array {
        return FileServer::loadByClause('id IN (SELECT file_server_id FROM file_artifact_storage WHERE file_artifact_id = :file_artifact_id)', [
            'file_artifact_id' => $this->id,
        ], 'serverLabel ASC');
    }

    public function getStorageServersLabel(): string {
        $storageServers = $this->getStorageServers();

        $serverLabels = [];
        foreach($storageServers as $storageServer) {
            $serverLabels[] = $storageServer->serverLabel;
        }

        return implode(', ', $serverLabels);
    }
}
