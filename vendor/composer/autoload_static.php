<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit80ef579773072e52af7084be60e1f95a
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'PHPMailer\\PHPMailer\\' => 20,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'PHPMailer\\PHPMailer\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpmailer/phpmailer/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit80ef579773072e52af7084be60e1f95a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit80ef579773072e52af7084be60e1f95a::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}