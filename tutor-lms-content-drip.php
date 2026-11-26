<?php
/**
 * Plugin Name: Tutor LMS Content Drip
 * Description: Scheduled topic release and protected course progress for Tutor LMS Free.
 * Version: 0.1.3
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: tutor
 * License: GPL-2.0-or-later
 * Text Domain: tutor-lms-content-drip
 */
defined('ABSPATH') || exit;
require_once __DIR__.'/src/Policy.php';
require_once __DIR__.'/src/Plugin.php';
require_once __DIR__.'/src/Admin.php';
require_once __DIR__.'/src/Abilities.php';
add_action('plugins_loaded', static function () {
    if (!function_exists('tutor_utils')) return;
    \TutorLMS\ContentDrip\Plugin::register();
    \TutorLMS\ContentDrip\Admin::register();
    \TutorLMS\ContentDrip\Abilities::register();
}, 30);
