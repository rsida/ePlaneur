<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 *
 * @return array<string, array{    // Import name as key, description of the imported file as value
 *     path: string,               // Logical, relative or absolute path to the file
 *     type?: 'js'|'css'|'json',   // Type of the file, defaults to 'js'
 *     entrypoint?: bool,          // Whether the file is an entrypoint, for 'js' only
 * }|array{
 *     version: string,            // Version of the remote package
 *     package_specifier?: string, // Remote "package-name/path" specifier, defaults to the import name
 *     type?: 'js'|'css'|'json',
 *     entrypoint?: bool,
 * }>
 */
return [
    'app' => ['path' => './assets/app.js', 'entrypoint' => true],
    'admin' => ['path' => './assets/admin.js', 'entrypoint' => true],
    '@hotwired/stimulus' => ['version' => '3.2.2'],
    '@symfony/stimulus-bundle' => ['path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js'],
    '@hotwired/turbo' => ['version' => '8.0.23'],
    '@deck.gl/core' => ['version' => '9.4.0'],
    '@deck.gl/layers' => ['version' => '9.4.0'],
    '@deck.gl/mapbox' => ['version' => '9.4.0'],
    'uplot' => ['version' => '1.6.32'],
    'uplot/dist/uPlot.min.css' => ['version' => '1.6.32', 'type' => 'css'],
    '@loaders.gl/core' => ['version' => '4.4.5'],
    '@loaders.gl/images' => ['version' => '4.4.5'],
    '@probe.gl/log' => ['version' => '4.1.2'],
    '@luma.gl/shadertools' => ['version' => '9.4.0'],
    '@math.gl/core' => ['version' => '4.1.0'],
    'mjolnir.js' => ['version' => '3.1.1'],
    '@math.gl/web-mercator' => ['version' => '4.1.0'],
    '@math.gl/sun' => ['version' => '4.1.0'],
    '@luma.gl/engine' => ['version' => '9.4.0'],
    '@probe.gl/stats' => ['version' => '4.1.2'],
    '@luma.gl/core' => ['version' => '9.4.0'],
    '@luma.gl/webgl' => ['version' => '9.4.0'],
    '@luma.gl/webgl/constants' => ['version' => '9.4.0'],
    '@luma.gl/gpgpu' => ['version' => '9.4.0'],
    '@luma.gl/gpgpu/webgpu' => ['version' => '9.4.0'],
    '@math.gl/polygon' => ['version' => '4.1.0'],
    'earcut' => ['version' => '2.2.4'],
    '@mapbox/tiny-sdf' => ['version' => '2.2.0'],
    '@loaders.gl/loader-utils' => ['version' => '4.4.5'],
    '@loaders.gl/worker-utils' => ['version' => '4.4.5'],
    '@loaders.gl/schema-utils' => ['version' => '4.4.5'],
    '@probe.gl/env' => ['version' => '4.1.2'],
    '@math.gl/types' => ['version' => '4.1.0'],
    '@luma.gl/webgl/dist/adapter/webgl-device.js' => ['version' => '9.4.0'],
    '@luma.gl/gpgpu/gpu-data' => ['version' => '9.4.0'],
    'apache-arrow' => ['version' => '21.2.0'],
    'tslib' => ['version' => '2.8.1'],
    'flatbuffers' => ['version' => '25.9.23'],
    'maplibre-gl' => ['version' => '5.24.0'],
    'maplibre-gl/dist/maplibre-gl.css' => ['version' => '5.24.0', 'type' => 'css'],
    'maplibre-gl/dist/maplibre-gl.min.css' => ['version' => '5.24.0', 'type' => 'css'],
];
