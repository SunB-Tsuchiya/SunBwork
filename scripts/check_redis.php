<?php
// Simple Redis connectivity check for local/deploy testing.
// Usage: php scripts/check_redis.php

require __DIR__ . '/../vendor/autoload.php';

$host = getenv('REDIS_HOST') ?: '127.0.0.1';
$port = getenv('REDIS_PORT') ?: 6379;
$password = getenv('REDIS_PASSWORD') ?: null;

echo "Checking Redis at {$host}:{$port}...\n";

try {
    if (class_exists('Redis')) {
        $redis = new Redis();
        $connected = @$redis->connect($host, $port, 2);
        if (! $connected) {
            throw new Exception('connect failed');
        }
        if ($password) {
            if (! $redis->auth($password)) {
                throw new Exception('auth failed');
            }
        }
        $pong = $redis->ping();
    } else {
        // fallback to predis if installed via composer
        if (! class_exists('\Predis\Client')) {
            throw new Exception('neither phpredis nor predis available');
        }
        $client = new \Predis\Client([
            'scheme' => 'tcp',
            'host' => $host,
            'port' => $port,
            'password' => $password ?: null,
        ], ['timeout' => 2]);
        $pong = $client->ping();
    }

    echo "PING response: " . var_export($pong, true) . "\n";
    echo "Redis appears reachable.\n";
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, "Redis check failed: " . $e->getMessage() . "\n");
    exit(2);
}
