<?php
namespace InterNations\Component\HttpMock\Request;

use InterNations\Component\HttpMock\File\SerializableUploadedFile;
use Serializable;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;

class SerializableRequest extends Request implements Serializable
{
    public function serialize(): string
    {
        $this->getContent();
        $files = [];

        if ($this->files) {
            foreach ($this->files as $key => $file) {
                // Move the file in another directory
                $path = sys_get_temp_dir() . '/' . md5($file->getPathname());
                $file->move(sys_get_temp_dir(), md5($file->getPathname()));
                $files[$key] = [
                    'originalName' => $file->getClientOriginalName(),
                    'pathName' => $path
                ];
            }
        }

        return serialize([
            'attributes' => $this->attributes,
            'request' => $this->request,
            'query' => $this->query,
            'server' => $this->server,
            'files' => $files,
            'cookies' => $this->cookies,
            'headers' => $this->headers,
            'content' => $this->content,
            'languages' => $this->languages,
            'charsets' => $this->charsets,
            'encodings' => $this->encodings,
            'acceptableContentTypes' => $this->acceptableContentTypes,
            'pathInfo' => $this->pathInfo,
            'requestUri' => $this->requestUri,
            'baseUrl' => $this->baseUrl,
            'basePath' => $this->basePath,
            'method' => $this->method,
            'format' => $this->format,
            'session' => $this->session,
            'locale' => $this->locale,
            'defaultLocale' => $this->defaultLocale,
        ]);
    }

    /** @param array<mixed> $data */
    public function unserialize($data): void // @codingStandardsIgnoreLine
    {
        $attributes = unserialize($data);

        $this->attributes = $attributes['attributes'];
        $this->request = $attributes['request'];
        $this->query = $attributes['query'];
        $this->server = $attributes['server'];
        $files = $attributes['files'];
        $this->files = [];

        if ($files) {
            foreach($files as $key => $file) {
                $this->files[$key] = new SerializableUploadedFile($file['pathName'], $file['originalName']);
            }
        }

        $this->files = new FileBag($this->files);
        $this->cookies = $attributes['cookies'];
        $this->headers = $attributes['headers'];
        $this->content = $attributes['content'];
        $this->languages = $attributes['languages'];
        $this->charsets = $attributes['charsets'];
        $this->encodings = $attributes['encodings'];
        $this->acceptableContentTypes = $attributes['acceptableContentTypes'];
        $this->pathInfo = $attributes['pathInfo'];
        $this->requestUri = $attributes['requestUri'];
        $this->baseUrl = $attributes['baseUrl'];
        $this->basePath = $attributes['basePath'];
        $this->method = $attributes['method'];
        $this->format = $attributes['format'];
        $this->session = $attributes['session'];
        $this->locale = $attributes['locale'];
        $this->defaultLocale = $attributes['defaultLocale'];
    }
}
