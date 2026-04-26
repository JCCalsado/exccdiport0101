<?php
/**
 * Vercel Serverless Entry Point for Laravel
 *
 * This file is the entry point for all requests to the Laravel application
 * when running on Vercel's serverless PHP runtime. It bootstraps the Laravel
 * application and handles the incoming HTTP request.
 *
 * Vercel routes all requests (except static assets) through this file.
 */

// Require the Laravel public/index.php which bootstraps the entire application
require __DIR__ . '/../public/index.php';
