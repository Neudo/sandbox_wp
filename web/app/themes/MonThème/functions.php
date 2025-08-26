<?php
Timber\Timber::init();

// Export automatique des champs vers acf-json/
add_filter('acf/settings/save_json', 'my_acf_json_save_point');
function my_acf_json_save_point( $path ) {
    $new_path = get_stylesheet_directory() . '/acf-json';
    if (!file_exists($new_path)) {
        wp_mkdir_p($new_path);
    }
    return $new_path;
}

// Import automatique depuis acf-json/ (CETTE FONCTION MANQUAIT)
add_filter('acf/settings/load_json', 'my_acf_json_load_point');
function my_acf_json_load_point( $paths ) {
    // Supprime le chemin par défaut
    unset($paths[0]);
    
    // Ajoute notre chemin personnalisé
    $paths[] = get_stylesheet_directory() . '/acf-json';
    
    return $paths;
}