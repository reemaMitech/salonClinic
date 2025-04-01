<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Cross-Origin Resource Sharing (CORS) Configuration
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
 */
class Cors extends BaseConfig
{
    /**
     * The default CORS configuration.
     *
     * @var array{
     *      allowedOrigins: list<string>,
     *      allowedOriginsPatterns: list<string>,
     *      supportsCredentials: bool,
     *      allowedHeaders: list<string>,
     *      exposedHeaders: list<string>,
     *      allowedMethods: list<string>,
     *      maxAge: int,
     *  }
     */
    public array $default = [
        // 'allowedOrigins' => ['*'], // Allow requests from Angular app
        'allowedOriginsPatterns' => [],
        'supportsCredentials' => false, // Allow credentials if needed
        'allowedHeaders' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'], // Add necessary headers
        'exposedHeaders' => [],
        'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'], // Allow necessary methods
        'maxAge' => 7200,
    ];
}
