<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit8bfbc8e0804b5520d8371bc3a1a8df32
{
    public static $prefixLengthsPsr4 = array (
        'K' => 
        array (
            'Kevin\\VirtueMart\\' => 17,
            'Kevin\\' => 6,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Kevin\\VirtueMart\\' => 
        array (
            0 => __DIR__ . '/../..' . '/kevin',
        ),
        'Kevin\\' => 
        array (
            0 => __DIR__ . '/..' . '/getkevin/kevin-php/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit8bfbc8e0804b5520d8371bc3a1a8df32::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit8bfbc8e0804b5520d8371bc3a1a8df32::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit8bfbc8e0804b5520d8371bc3a1a8df32::$classMap;

        }, null, ClassLoader::class);
    }
}
