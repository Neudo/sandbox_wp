<?php
Timber\Timber::init();


add_filter('acf/settings/save_json', 'my_acf_json_save_point');
function my_acf_json_save_point( $path ) {
    $new_path = get_stylesheet_directory() . '/acf-json';
    if (!file_exists($new_path)) {
        wp_mkdir_p($new_path);
    }
    
    return $new_path;
}