<?php
/**
 * Plugin Name: WEM Inquiry
 * Description: Private WordPress-comment-backed inquiries with reusable forms and configurable fields.
 * Version: 1.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: wem-inquiry
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
define('WEM_INQUIRY_VERSION', '1.1.0');
define('WEM_INQUIRY_TYPE', 'wem_inquiry');
define('WEM_INQUIRY_PATH', plugin_dir_path(__FILE__));
require_once WEM_INQUIRY_PATH . 'includes/class-fields.php';
require_once WEM_INQUIRY_PATH . 'includes/class-storage.php';
require_once WEM_INQUIRY_PATH . 'includes/class-form.php';
require_once WEM_INQUIRY_PATH . 'includes/class-privacy.php';
require_once WEM_INQUIRY_PATH . 'includes/class-admin.php';
require_once WEM_INQUIRY_PATH . 'includes/class-legacy.php';
require_once WEM_INQUIRY_PATH . 'includes/class-diagnostics.php';
add_action('init', array('WEM_Inquiry_Form', 'register'));
add_action('init', array('WEM_Inquiry_Privacy', 'register'));
add_action('admin_init', array('WEM_Inquiry_Admin', 'register_settings'));
add_action('admin_menu', array('WEM_Inquiry_Admin', 'register_menu'));
add_action('admin_init', array('WEM_Inquiry_Admin', 'register_comment_screen'));
add_action('init', array('WEM_Inquiry_Legacy', 'register'));

add_action('init', array('WEM_Inquiry_Diagnostics', 'register'));
