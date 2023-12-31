<?php

/**
 * LICENSE: The MIT License (the "License")
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * https://github.com/azure/azure-storage-php/LICENSE
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * PHP version 5
 *
 * @category  Microsoft
 * @package   MicrosoftAzure\Storage\Blob\Models
 * @author    Azure Storage PHP SDK <dmsh@microsoft.com>
 * @copyright 2016 Microsoft Corporation
 * @license   https://github.com/azure/azure-storage-php/LICENSE
 * @link      https://github.com/azure/azure-storage-php
 */
 
namespace MicrosoftAzure\Storage\Blob\Models;
use MicrosoftAzure\Storage\Blob\Models\AccessPolicy;

/**
 * Holds container signed identifiers.
 * 
 * @category  Microsoft
 * @package   MicrosoftAzure\Storage\Blob\Models
 * @author    Azure Storage PHP SDK <dmsh@microsoft.com>
 * @copyright 2016 Microsoft Corporation
 * @license   https://github.com/azure/azure-storage-php/LICENSE
 * @version   Release: 0.10.2
 * @link      https://github.com/azure/azure-storage-php
 */
class SignedIdentifier
{
    private $_id;
    private $_accessPolicy;
    
    /**
     * Gets id.
     *
     * @return string.
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * Sets id.
     *
     * @param string $id value.
     * 
     * @return none.
     */
    public function setId($id)
    {
        $this->_id = $id;
    }
    
    /**
     * Gets accessPolicy.
     *
     * @return string.
     */
    public function getAccessPolicy()
    {
        return $this->_accessPolicy;
    }

    /**
     * Sets accessPolicy.
     *
     * @param string $accessPolicy value.
     * 
     * @return none.
     */
    public function setAccessPolicy($accessPolicy)
    {
        $this->_accessPolicy = $accessPolicy;
    }
    
    /**
     * Converts this current object to XML representation.
     * 
     * @return array.
     */
    public function toArray()
    {
        $array = [];
        
        $array['SignedIdentifier']['Id']           = $this->_id;
        $array['SignedIdentifier']['AccessPolicy'] = $this->_accessPolicy->toArray();
        
        return $array;
    }
}


