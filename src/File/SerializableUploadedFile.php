<?php

namespace InterNations\Component\HttpMock\File;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class SerializableUploadedFile extends UploadedFile
{

    public function __construct(string $path, string $originalName, string $mimeType = null, int $error = null)
    {
        parent::__construct($path, $originalName, $mimeType, $error, true);
    }

}