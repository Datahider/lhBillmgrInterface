<?php

spl_autoload_register(function ($class) {
    $suggested = [
        __LIB_ROOT__ . "lhSimpleMessage/classes/$class.php",
        __LIB_ROOT__ . "lhValidator/classes/$class.php",
        __DIR__ . "/classes/$class.php"
    ];
    
    foreach ($suggested as $file) {
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

