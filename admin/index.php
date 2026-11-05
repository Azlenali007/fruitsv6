<?php
/**
 * Admin Panel Entry Point
 * N.A Fresh Fruits & Coconuts
 */
if (empty($_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '/admin/index.php') {
    $_SERVER['REQUEST_URI'] = '/admin';
}
require_once __DIR__ . '/../public/index.php';
