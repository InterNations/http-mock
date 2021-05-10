<?php
namespace InterNations\Component\HttpMock\PHPUnit;

use function trigger_error;
use const E_USER_DEPRECATED;

trigger_error(E_USER_DEPRECATED, sprintf('%s is deprecated. Use %s instead', HttpMockTrait::class, HttpMock::class));

trait HttpMockTrait
{
    use HttpMock;
}
