<?php
// Check Redis connection and session
try {
    $r = new Redis();
    $r->connect('redis', 6379);
    echo "Redis: OK (" . $r->ping() . ")\n";
    
    // Check session keys
    $keys = $r->keys('*session*');
    echo "Session keys in Redis: " . count($keys) . "\n";
    foreach (array_slice($keys, 0, 5) as $k) {
        echo "  - $k\n";
    }
} catch (Exception $e) {
    echo "Redis FAIL: " . $e->getMessage() . "\n";
}
