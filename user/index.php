<?php
/**
 * Customer User Account & Profile Entry Point
 * N.A Fresh Fruits & Coconuts
 */
if (empty($_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '/user/index.php') {
    $_SERVER['REQUEST_URI'] = '/account';
}
require_once __DIR__ . '/../public/index.php';
