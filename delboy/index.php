<?php
/**
 * Delivery Boy Portal Entry Point (/delboy)
 * N.A Fresh Fruits & Coconuts
 */
if (empty($_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '/delboy/index.php') {
    $_SERVER['REQUEST_URI'] = '/delboy';
}
require_once __DIR__ . '/../public/index.php';
