<?php
/**
 * Plugin Name: Standard Chartered Replace Files
 * Description: Allow replacing files after they've been uploaded.
 * Author: Human Made
 * Author URI: https://hmn.md/
 */

namespace SC\Replace_Files;

const DIR = __DIR__;
const FILE = __FILE__;

require __DIR__ . '/inc/namespace.php';
require __DIR__ . '/inc/admin/namespace.php';

bootstrap();
