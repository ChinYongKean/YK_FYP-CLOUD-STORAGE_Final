<?php

namespace App\Services;

class FileSystem
{
    protected $base = null;

    protected function real($path)
    {
        $temp = realpath($path);
        if(!$temp)
        {
            throw new Exception('Path does not exist: ' . $path);
        }
        if($this->base && strlen($this->base))
        {
            if(strpos($temp, $this->base) !== 0)
            {
                throw new Exception('Path is not inside base (' . $this->base . '): ' . $temp);
            }
        }
        return $temp;
    }

    protected function path($id)
    {
        $id = str_replace('/', DIRECTORY_SEPARATOR, $id);
        $id = trim($id, DIRECTORY_SEPARATOR);
        $id = $this->real($this->base . DIRECTORY_SEPARATOR . $id);
        return $id;
    }

    protected function id($path)
    {
        $path = $this->real($path);
        $path = substr($path, strlen($this->base));
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        $path = trim($path, '/');
        return strlen($path) ? $path : '/';
    }
    
    public function __construct($base)
    {
        $this->base = $this->real($base);
        if(!$this->base)
        {
            throw new Exception('Base directory does not exist');
        }
    }

    public function lst($id, $with_root = false)
    {
        $dir = $this->path($id);
        $lst = @scandir($dir);
        if(!$lst)
        {
            throw new Exception('Could not list path: ' . $dir);
        }
        $res = [];
        foreach($lst as $item)
        {
            if($item == '.' || $item == '..' || $item === null)
            {
                continue;
            }
            
            // skip any which aren't readable
            if(!is_readable($dir . DIRECTORY_SEPARATOR . $item))
            {
                continue;
            }
            if(is_dir($dir . DIRECTORY_SEPARATOR . $item))
            {
                $res[] = array('text' => $item, 'children' => true, 'id' => $this->id($dir . DIRECTORY_SEPARATOR . $item), 'icon' => 'folder');
            }
            else
            {
                // skip files
                continue;
                //$res[] = array('text' => $item, 'children' => false, 'id' => $this->id($dir . DIRECTORY_SEPARATOR . $item), 'type' => 'file', 'icon' => 'file file-' . substr($item, strrpos($item, '.') + 1));
            }
        }
        if($with_root && $this->id($dir) === '/')
        {
            $res = array(array('text' => basename($this->base), 'children' => $res, 'id' => '/', 'icon' => 'folder', 'state' => array('opened' => true, 'disabled' => true)));
        }
        return $res;
    }

}
