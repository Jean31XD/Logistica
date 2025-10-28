<?php
// src/cache.php

function get_cached_data($key, $duration, callable $data_generator_fn) {
    $cache_dir = __DIR__ . '/../cache'; // Asume que la carpeta 'cache' está en la raíz del proyecto
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }

    $cache_file = $cache_dir . '/' . md5($key) . '.json';

    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $duration) {
        return json_decode(file_get_contents($cache_file), true);
    }

    $fresh_data = $data_generator_fn();
    if ($fresh_data !== null) {
        file_put_contents($cache_file, json_encode($fresh_data));
    }

    return $fresh_data;
}