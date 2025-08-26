<?php
/**
 * ACF Agency Workflow Configuration
 * 
 * Configuration optimisée pour le workflow agence :
 * - DEV : Mode JSON modifiable avec warning de synchronisation
 * - PROD/PREPROD : Mode PHP automatique via ACF Extended
 * 
 * @author HectorAnalytics.com
 * @version 1.0
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

class ACF_Agency_Workflow {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialise les hooks selon l'environnement
     */
    private function init_hooks() {
        // Détection de l'environnement
        $is_dev = $this->is_development_environment();
        
        if ($is_dev) {
            $this->setup_development_mode();
        } else {
            $this->setup_production_mode();
        }
        
        // Warning de synchronisation (tous environnements)
        add_action('admin_notices', array($this, 'sync_warning_notice'));
    }
    
    /**
     * Détecte si on est en environnement de développement
     */
    private function is_development_environment() {
        // Plusieurs méthodes de détection
        if (defined('WP_ENV') && WP_ENV === 'development') {
            return true;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            return true;
        }
        
        // Détection par domaine local
        $local_domains = array('localhost', '127.0.0.1', '.local', '.test', '.dev');
        $current_domain = $_SERVER['HTTP_HOST'] ?? '';
        
        foreach ($local_domains as $local_domain) {
            if (strpos($current_domain, $local_domain) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Configuration pour l'environnement de développement
     */
    private function setup_development_mode() {
        // Export automatique vers JSON
        add_filter('acf/settings/save_json', array($this, 'acf_json_save_point'));
        
        // Import automatique depuis JSON
        add_filter('acf/settings/load_json', array($this, 'acf_json_load_point'));
        
        // Debug info (optionnel)
        add_action('wp_footer', array($this, 'dev_debug_info'));
    }
    
    /**
     * Configuration pour l'environnement de production
     */
    private function setup_production_mode() {
        // Active l'Auto Sync PHP d'ACF Extended (si disponible)
        if (class_exists('ACFE')) {
            add_filter('acfe/modules/force_sync', '__return_true');
            
            // Créer le dossier ACFE PHP s'il n'existe pas
            $acfe_php_path = get_stylesheet_directory() . '/acfe-php';
            if (!file_exists($acfe_php_path)) {
                wp_mkdir_p($acfe_php_path);
            }
        }
        
        // Masque l'interface ACF pour les non-administrateurs
        if (!current_user_can('administrator')) {
            add_filter('acf/settings/show_admin', '__return_false');
        }
        
        // Performance : désactive l'interface de debug
        add_filter('acf/settings/show_updates', '__return_false');
    }
    
    /**
     * Point de sauvegarde JSON
     */
    public function acf_json_save_point($path) {
        $new_path = get_stylesheet_directory() . '/acf-json';
        
        // Crée le dossier s'il n'existe pas avec gestion d'erreur
        if (!file_exists($new_path)) {
            if (!wp_mkdir_p($new_path)) {
                error_log('ACF Agency Workflow: Impossible de créer le dossier acf-json');
                return $path; // Retourne le chemin par défaut en cas d'échec
            }
        }
        
        return $new_path;
    }
    
    /**
     * Point de chargement JSON
     */
    public function acf_json_load_point($paths) {
        // Supprime le chemin par défaut
        unset($paths[0]);
        
        // Ajoute notre chemin personnalisé
        $paths[] = get_stylesheet_directory() . '/acf-json';
        
        return $paths;
    }
    
    /**
     * Vérifie s'il y a des champs à synchroniser
     */
    private function check_acf_sync_needed() {
        $url = $_SERVER['REQUEST_URI'];
        if (!function_exists('acf_get_field_groups') || strpos($url, 'acf-field-group') !== false) {
            return false;
        }
        
        // Récupère les groupes qui ont besoin d'être synchronisés
        $sync_groups = acf_get_field_groups();
        $sync_needed = array();
        
        foreach ($sync_groups as $group) {
            // Vérifie si le groupe a besoin d'être synchronisé
            if (acf_maybe_get($group, 'local') === 'json') {
                // Compare les timestamps
                $local = acf_get_local_field_group($group['key']);
                $db = acf_get_field_group($group['key']);
                
                // Vérification sécurisée des timestamps
                $local_modified = isset($local['modified']) ? $local['modified'] : 0;
                $db_modified = isset($db['modified']) ? $db['modified'] : 0;
                
                if (!$db || $local_modified > $db_modified) {
                    $sync_needed[] = $group;
                }
            }
        }
        
        return count($sync_needed);
    }
    
    /**
     * Affiche le warning de synchronisation
     */
    public function sync_warning_notice() {
        $sync_count = $this->check_acf_sync_needed();
        
        if ($sync_count > 0) {
            echo '<div style="background: #d63384; color: white; padding: 20px; text-align: center; font-size: 18px; font-weight: bold; margin: 20px 0;">';
            echo '🚨 ATTENTION : ' . $sync_count . ' CHAMP(S) ACF À SYNCHRONISER 🚨<br>';
            echo '<a href="' . admin_url('edit.php?post_type=acf-field-group&post_status=sync') . '" style="color: white; text-decoration: underline;">CLIQUEZ ICI POUR SYNCHRONISER</a>';
            echo '</div>';
        }
    }
    
    /**
     * Info de debug en développement (footer)
     */
    public function dev_debug_info() {
        if (current_user_can('administrator') && WP_DEBUG) {
            $env = $this->is_development_environment() ? 'DEV (JSON)' : 'PROD (PHP)';
            echo '<div style="position: fixed; bottom: 10px; right: 10px; background: #333; color: #fff; padding: 8px 12px; font-size: 11px; z-index: 9999; border-radius: 3px;">';
            echo 'ACF Mode: ' . esc_html($env);
            echo '</div>';
        }
    }
}

// Initialisation automatique
new ACF_Agency_Workflow();

/**
 * Fonctions utilitaires pour l'agence
 */

/**
 * Force la synchronisation ACF (utile pour les scripts de déploiement)
 */
function force_acf_sync() {
    if (!function_exists('acf_get_field_groups')) {
        return false;
    }
    
    $sync_groups = acf_get_field_groups();
    $synced = 0;
    
    foreach ($sync_groups as $group) {
        if (acf_maybe_get($group, 'local') === 'json') {
            $local = acf_get_local_field_group($group['key']);
            if ($local) {
                acf_import_field_group($local);
                $synced++;
            }
        }
    }
    
    return $synced;
}

/**
 * Nettoie les anciens exports JSON (garde les 5 derniers)
 */
function cleanup_old_acf_json() {
    $json_dir = get_stylesheet_directory() . '/acf-json';
    
    if (!is_dir($json_dir)) {
        return;
    }
    
    $files = glob($json_dir . '/*.json');
    
    if (count($files) > 5) {
        // Trie par date de modification
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Supprime les anciens (garde les 5 plus récents)
        $to_delete = array_slice($files, 5);
        foreach ($to_delete as $file) {
            unlink($file);
        }
    }
}

// Hook pour nettoyer automatiquement (optionnel)
add_action('acf/save_post', 'cleanup_old_acf_json');