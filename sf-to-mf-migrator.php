<?php
/**
 * Plugin Name: Simple Favorites to My Favorites Migrator
 * Plugin URI: https://github.com/agenciarse/sf-to-mf-migrator
 * Description: Complete migration tool from Simple Favorites to My Favorites plugin. Migrates user favorites, replaces shortcodes, and cleans up old data safely.
 * Version: 1.0.1
 * Author: AGENCIA RSE
 * Author URI: https://agenciarse.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sf-to-mf-migrator
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SF_To_MF_Migrator {
    
    private $version = '1.0.0';
    private $option_prefix = 'sfmf_';
    
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
        
        // Enqueue admin styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        
        // AJAX handlers
        add_action('wp_ajax_sfmf_analyze', array($this, 'ajax_analyze'));
        add_action('wp_ajax_sfmf_migrate', array($this, 'ajax_migrate'));
        add_action('wp_ajax_sfmf_verify', array($this, 'ajax_verify'));
        add_action('wp_ajax_sfmf_replace_shortcodes', array($this, 'ajax_replace_shortcodes'));
        add_action('wp_ajax_sfmf_cleanup', array($this, 'ajax_cleanup'));
    }
    
    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Favorites Migration', 'sf-to-mf-migrator'),
            __('Favorites Migration', 'sf-to-mf-migrator'),
            'manage_options',
            'sf-to-mf-migrator',
            array($this, 'render_admin_page'),
            'dashicons-star-filled',
            80
        );
    }
    
    /**
     * Add action links to plugin page
     */
    public function add_action_links($links) {
        $migration_link = '<a href="' . admin_url('admin.php?page=sf-to-mf-migrator') . '" style="color: #f39c12; font-weight: bold;">' . 
                         __('Start Migration', 'sf-to-mf-migrator') . '</a>';
        array_unshift($links, $migration_link);
        return $links;
    }
    
    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        if ('toplevel_page_sf-to-mf-migrator' !== $hook) {
            return;
        }
        
        // Inline CSS since we're creating a single-file plugin
        wp_add_inline_style('wp-admin', $this->get_admin_css());
    }
    
    /**
     * Get admin CSS
     */
    private function get_admin_css() {
        return '
        /* Simple Favorites to My Favorites Migrator - Admin Styles */
        .sfmf-wrap { max-width: 1200px; margin: 20px 0; }
        .sfmf-wrap h1 { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .sfmf-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px 30px; margin: 20px 0; box-shadow: 0 1px 1px rgba(0,0,0,0.04); }
        .sfmf-warning-banner { background: #fff3cd; border: 1px solid #ffc107; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0; display: flex; align-items: flex-start; gap: 15px; }
        .sfmf-warning-banner .dashicons { color: #ffc107; font-size: 24px; }
        .sfmf-warning-banner strong { color: #856404; }
        .sfmf-checklist { list-style: none; padding: 0; }
        .sfmf-checklist li { padding: 10px 0; display: flex; align-items: center; gap: 10px; }
        .sfmf-checklist .dashicons { color: #28a745; }
        .sfmf-step { margin: 30px 0; border-left: 4px solid #e0e0e0; transition: border-color 0.3s; }
        .sfmf-step:hover { border-left-color: #2271b1; }
        .sfmf-step-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .sfmf-step-number { background: #2271b1; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: bold; }
        .sfmf-action-btn { display: inline-flex; align-items: center; gap: 8px; margin-top: 20px; padding: 10px 30px !important; height: auto !important; }
        .sfmf-action-btn .dashicons { font-size: 20px; width: 20px; height: 20px; }
        .button-danger { background: #dc3232 !important; border-color: #dc3232 !important; color: white !important; }
        .button-danger:hover { background: #c62828 !important; }
        .sfmf-info-box, .sfmf-warning-box, .sfmf-success-box, .sfmf-danger-box, .sfmf-error { padding: 15px 20px; margin: 20px 0; border-left: 4px solid; display: flex; align-items: flex-start; gap: 12px; }
        .sfmf-info-box { background: #e7f3ff; border-color: #2271b1; }
        .sfmf-warning-box { background: #fff3cd; border-color: #ffc107; }
        .sfmf-success-box { background: #d4edda; border-color: #28a745; }
        .sfmf-danger-box { background: #f8d7da; border-color: #dc3232; }
        .sfmf-error { background: #f8d7da; border-color: #dc3232; }
        .sfmf-info-box .dashicons { color: #2271b1; font-size: 20px; }
        .sfmf-warning-box .dashicons { color: #ffc107; font-size: 20px; }
        .sfmf-success-box .dashicons { color: #28a745; font-size: 20px; }
        .sfmf-danger-box .dashicons { color: #dc3232; font-size: 20px; }
        .sfmf-stats { display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap; }
        .sfmf-stat { background: #f0f0f1; padding: 20px; border-radius: 4px; text-align: center; min-width: 150px; }
        .sfmf-stat-number { display: block; font-size: 36px; font-weight: bold; color: #2271b1; line-height: 1; margin-bottom: 5px; }
        .sfmf-stat-error .sfmf-stat-number { color: #dc3232; }
        .sfmf-stat-label { display: block; font-size: 14px; color: #646970; }
        .sfmf-shortcode-mapping { margin: 20px 0; }
        .sfmf-shortcode-mapping table { margin: 0; }
        .sfmf-shortcode-mapping code { background: #f0f0f1; padding: 4px 8px; border-radius: 3px; }
        .sfmf-confirm-checkbox { display: flex; align-items: center; gap: 10px; margin: 20px 0; padding: 15px; background: #f0f0f1; border-radius: 4px; cursor: pointer; }
        .sfmf-confirm-checkbox input { margin: 0; }
        .sfmf-loading { display: flex; align-items: center; gap: 10px; padding: 20px; background: #f0f0f1; border-radius: 4px; }
        .sfmf-migration-log, .sfmf-replacement-log { max-height: 300px; overflow-y: auto; background: #f9f9f9; padding: 15px; border-radius: 4px; margin: 15px 0; font-family: monospace; font-size: 13px; }
        .sfmf-log-success, .sfmf-log-error { padding: 5px 0; }
        .sfmf-log-success { color: #28a745; }
        .sfmf-log-error { color: #dc3232; }
        details { margin: 15px 0; }
        summary { cursor: pointer; padding: 10px; background: #f0f0f1; border-radius: 4px; font-weight: 600; }
        summary:hover { background: #e0e0e1; }
        .sfmf-support { background: #e7f3ff; border-left: 4px solid #2271b1; }
        .sfmf-support ul { list-style: none; padding: 0; }
        .sfmf-support li { padding: 8px 0; }
        .sfmf-support a { text-decoration: none; color: #2271b1; }
        .sfmf-support a:hover { text-decoration: underline; }
        .sfmf-branding { text-align: center; padding: 20px; background: #f9f9f9; border-radius: 4px; margin-top: 30px; }
        .sfmf-branding img { max-width: 200px; margin-bottom: 10px; }
        @media (max-width: 768px) {
            .sfmf-stats { flex-direction: column; }
            .sfmf-stat { width: 100%; }
        }
        ';
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap sfmf-wrap">
            <h1>
                <span class="dashicons dashicons-star-filled" style="color: #f39c12;"></span>
                <?php _e('Simple Favorites to My Favorites Migration Tool', 'sf-to-mf-migrator'); ?>
            </h1>
            
            <p class="description">
                <?php _e('This tool will help you migrate all your user favorites from Simple Favorites plugin to My Favorites plugin safely and efficiently.', 'sf-to-mf-migrator'); ?>
            </p>
            
            <!-- Warning Banner -->
            <div class="sfmf-warning-banner">
                <span class="dashicons dashicons-warning"></span>
                <div>
                    <strong><?php _e('Important: Backup Your Database First!', 'sf-to-mf-migrator'); ?></strong>
                    <p><?php _e('Before proceeding, please create a complete backup of your database. This migration will modify user metadata.', 'sf-to-mf-migrator'); ?></p>
                </div>
            </div>
            
            <!-- Prerequisites -->
            <div class="sfmf-card sfmf-prerequisites">
                <h2><?php _e('📋 Prerequisites', 'sf-to-mf-migrator'); ?></h2>
                <ul class="sfmf-checklist">
                    <li>
                        <span class="dashicons dashicons-yes"></span>
                        <?php _e('<strong>Simple Favorites</strong> plugin is installed and has user data', 'sf-to-mf-migrator'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-yes"></span>
                        <?php _e('<strong>My Favorites</strong> plugin By Takashi Matsuyama is installed and activated', 'sf-to-mf-migrator'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-yes"></span>
                        <?php _e('You have created a <strong>database backup</strong>', 'sf-to-mf-migrator'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-yes"></span>
                        <?php _e('You have <strong>admin permissions</strong> on this WordPress installation', 'sf-to-mf-migrator'); ?>
                    </li>
                </ul>
            </div>
            
            <!-- Migration Steps -->
            <div class="sfmf-steps">
                
                <!-- Step 1: Analyze -->
                <div class="sfmf-card sfmf-step" id="step-analyze">
                    <div class="sfmf-step-header">
                        <span class="sfmf-step-number">1</span>
                        <h2><?php _e('Analyze Data', 'sf-to-mf-migrator'); ?></h2>
                    </div>
                    <p><?php _e('First, let\'s analyze your current data to ensure everything is ready for migration. This step will check:', 'sf-to-mf-migrator'); ?></p>
                    <ul>
                        <li><?php _e('Number of users with favorites', 'sf-to-mf-migrator'); ?></li>
                        <li><?php _e('Total favorite posts to migrate', 'sf-to-mf-migrator'); ?></li>
                        <li><?php _e('Data format compatibility', 'sf-to-mf-migrator'); ?></li>
                        <li><?php _e('Potential issues or conflicts', 'sf-to-mf-migrator'); ?></li>
                    </ul>
                    
                    <button type="button" class="button button-primary button-hero sfmf-action-btn" data-action="analyze">
                        <span class="dashicons dashicons-search"></span>
                        <?php _e('Analyze Data', 'sf-to-mf-migrator'); ?>
                    </button>
                    
                    <div class="sfmf-result" id="analyze-result"></div>
                </div>
                
                <!-- Step 2: Migrate -->
                <div class="sfmf-card sfmf-step" id="step-migrate">
                    <div class="sfmf-step-header">
                        <span class="sfmf-step-number">2</span>
                        <h2><?php _e('Migrate Favorites', 'sf-to-mf-migrator'); ?></h2>
                    </div>
                    <p><?php _e('This will migrate all user favorites from Simple Favorites to My Favorites format. The process will:', 'sf-to-mf-migrator'); ?></p>
                    <ul>
                        <li><?php _e('Extract all post IDs from Simple Favorites data', 'sf-to-mf-migrator'); ?></li>
                        <li><?php _e('Convert to My Favorites format (comma-separated string)', 'sf-to-mf-migrator'); ?></li>
                        <li><?php _e('Create new user meta entries for each user', 'sf-to-mf-migrator'); ?></li>
                        <li><?php _e('Preserve all existing favorites', 'sf-to-mf-migrator'); ?></li>
                    </ul>
                    
                    <div class="sfmf-info-box">
                        <span class="dashicons dashicons-info"></span>
                        <p><?php _e('<strong>Note:</strong> This step does NOT delete any Simple Favorites data. Your original data remains intact until you explicitly clean it up in Step 5.', 'sf-to-mf-migrator'); ?></p>
                    </div>
                    
                    <button type="button" class="button button-primary button-hero sfmf-action-btn" data-action="migrate" disabled>
                        <span class="dashicons dashicons-migrate"></span>
                        <?php _e('Start Migration', 'sf-to-mf-migrator'); ?>
                    </button>
                    
                    <div class="sfmf-result" id="migrate-result"></div>
                </div>
                
                <!-- Step 3: Verify -->
                <div class="sfmf-card sfmf-step" id="step-verify">
                    <div class="sfmf-step-header">
                        <span class="sfmf-step-number">3</span>
                        <h2><?php _e('Verify Migration', 'sf-to-mf-migrator'); ?></h2>
                    </div>
                    <p><?php _e('Verify that the migration was successful by comparing data between both systems. This will:', 'sf-to-mf-migrator'); ?></p>
                    <ul>
                        <li><?php _e('Compare Simple Favorites and My Favorites data side by side', 'sf-to-mf-migrator'); ?></li>
                        <li><?php _e('Check if all post IDs were migrated correctly', 'sf-to-mf-migrator'); ?></li>
                        <li><?php _e('Identify any discrepancies or missing data', 'sf-to-mf-migrator'); ?></li>
                        <li><?php _e('Show a sample of migrated users for manual verification', 'sf-to-mf-migrator'); ?></li>
                    </ul>
                    
                    <button type="button" class="button button-primary button-hero sfmf-action-btn" data-action="verify" disabled>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('Verify Migration', 'sf-to-mf-migrator'); ?>
                    </button>
                    
                    <div class="sfmf-result" id="verify-result"></div>
                </div>
                
                <!-- Step 4: Replace Shortcodes -->
                <div class="sfmf-card sfmf-step" id="step-shortcodes">
                    <div class="sfmf-step-header">
                        <span class="sfmf-step-number">4</span>
                        <h2><?php _e('Replace Shortcodes', 'sf-to-mf-migrator'); ?></h2>
                    </div>
                    <p><?php _e('Automatically replace Simple Favorites shortcodes with My Favorites equivalents throughout your site. This will scan and replace:', 'sf-to-mf-migrator'); ?></p>
                    
                    <div class="sfmf-shortcode-mapping">
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php _e('Simple Favorites', 'sf-to-mf-migrator'); ?></th>
                                    <th><?php _e('→', 'sf-to-mf-migrator'); ?></th>
                                    <th><?php _e('My Favorites', 'sf-to-mf-migrator'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>[favorite_button]</code></td>
                                    <td>→</td>
                                    <td><code>[ccc_my_favorite_select_button]</code></td>
                                </tr>
                                <tr>
                                    <td><code>[user_favorites]</code></td>
                                    <td>→</td>
                                    <td><code>[ccc_my_favorite_list_menu]</code></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <p><?php _e('The tool will search in:', 'sf-to-mf-migrator'); ?></p>
                    <ul>
                        <li><?php _e('Post content (posts, pages, custom post types)', 'sf-to-mf-migrator'); ?></li>
                        <li><?php _e('Post meta fields (for page builders like Elementor, Divi, etc.)', 'sf-to-mf-migrator'); ?></li>
                        <li><?php _e('Widget content', 'sf-to-mf-migrator'); ?></li>
                        <li><?php _e('Options table (for theme/plugin settings)', 'sf-to-mf-migrator'); ?></li>
                    </ul>
                    
                    <div class="sfmf-warning-box">
                        <span class="dashicons dashicons-warning"></span>
                        <p><?php _e('<strong>Warning:</strong> This step modifies your content. Make sure you have a backup before proceeding.', 'sf-to-mf-migrator'); ?></p>
                    </div>
                    
                    <button type="button" class="button button-primary button-hero sfmf-action-btn" data-action="replace-shortcodes" disabled>
                        <span class="dashicons dashicons-editor-code"></span>
                        <?php _e('Replace Shortcodes', 'sf-to-mf-migrator'); ?>
                    </button>
                    
                    <div class="sfmf-result" id="shortcodes-result"></div>
                </div>
                
                <!-- Step 5: Cleanup -->
                <div class="sfmf-card sfmf-step" id="step-cleanup">
                    <div class="sfmf-step-header">
                        <span class="sfmf-step-number">5</span>
                        <h2><?php _e('Clean Up Old Data', 'sf-to-mf-migrator'); ?></h2>
                    </div>
                    <p><?php _e('Once you\'ve verified everything is working correctly with My Favorites, you can safely remove the old Simple Favorites data. This will:', 'sf-to-mf-migrator'); ?></p>
                    <ul>
                        <li><?php _e('Delete all <code>simplefavorites</code> user meta entries', 'sf-to-mf-migrator'); ?></li>
                        <li><?php _e('Free up database space', 'sf-to-mf-migrator'); ?></li>
                        <li><?php _e('Complete the migration process', 'sf-to-mf-migrator'); ?></li>
                    </ul>
                    
                    <div class="sfmf-danger-box">
                        <span class="dashicons dashicons-dismiss"></span>
                        <p><?php _e('<strong>⚠️ DANGER ZONE:</strong> This action is <strong>IRREVERSIBLE</strong>. Only proceed after confirming everything works correctly with My Favorites. You should have a database backup before proceeding.', 'sf-to-mf-migrator'); ?></p>
                    </div>
                    
                    <label class="sfmf-confirm-checkbox">
                        <input type="checkbox" id="confirm-cleanup">
                        <?php _e('I confirm that I have verified the migration and have a database backup', 'sf-to-mf-migrator'); ?>
                    </label>
                    
                    <button type="button" class="button button-danger button-hero sfmf-action-btn" data-action="cleanup" disabled>
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Delete Simple Favorites Data', 'sf-to-mf-migrator'); ?>
                    </button>
                    
                    <div class="sfmf-result" id="cleanup-result"></div>
                </div>
                
            </div>
            
            <!-- Support Section -->
            <div class="sfmf-card sfmf-support">
                <h2><?php _e('💡 Need Help?', 'sf-to-mf-migrator'); ?></h2>
                <p><?php _e('If you encounter any issues during migration:', 'sf-to-mf-migrator'); ?></p>
                <ul>
                    <li>
                        <a href="https://github.com/agenciarse/sf-to-mf-migrator/issues" target="_blank">
                            <?php _e('Report an issue on GitHub', 'sf-to-mf-migrator'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://github.com/agenciarse/sf-to-mf-migrator/wiki" target="_blank">
                            <?php _e('Read the documentation', 'sf-to-mf-migrator'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="mailto:dev@agenciarse.com">
                            <?php _e('Contact support: dev@agenciarse.com', 'sf-to-mf-migrator'); ?>
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Branding -->
            <div class="sfmf-branding">
                <p style="color: #646970; font-size: 14px;">
                    <?php _e('Developed with ❤️ by', 'sf-to-mf-migrator'); ?>
                </p>
                <p>
                    <strong style="font-size: 18px; color: #2271b1;">AGENCIA RSE</strong>
                </p>
                <p>
                    <a href="https://agenciarse.com" target="_blank" style="color: #2271b1;">agenciarse.com</a>
                </p>
            </div>
            
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            
            var completedSteps = [];
            
            // Action button click handler
            $('.sfmf-action-btn').on('click', function() {
                var $btn = $(this);
                var action = $btn.data('action');
                var $result = $('#' + action.replace('_', '-') + '-result');
                
                // Special confirmation for cleanup
                if (action === 'cleanup') {
                    if (!$('#confirm-cleanup').is(':checked')) {
                        alert('<?php _e('Please confirm that you have verified the migration and have a backup.', 'sf-to-mf-migrator'); ?>');
                        return;
                    }
                    
                    if (!confirm('<?php _e('Are you absolutely sure? This will permanently delete all Simple Favorites data. This action cannot be undone!', 'sf-to-mf-migrator'); ?>')) {
                        return;
                    }
                }
                
                // Disable button and show loading
                $btn.prop('disabled', true);
                $result.html('<div class="sfmf-loading"><span class="spinner is-active"></span> <?php _e('Processing...', 'sf-to-mf-migrator'); ?></div>');
                
                // AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sfmf_' + action,
                        nonce: '<?php echo wp_create_nonce('sfmf_action'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html(response.data.html);
                            completedSteps.push(action);
                            
                            // Enable next step
                            enableNextStep(action);
                            
                            // Re-enable current button
                            $btn.prop('disabled', false);
                        } else {
                            $result.html('<div class="sfmf-error">' + response.data.message + '</div>');
                            $btn.prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        $result.html('<div class="sfmf-error"><?php _e('Error:', 'sf-to-mf-migrator'); ?> ' + error + '</div>');
                        $btn.prop('disabled', false);
                    }
                });
            });
            
            // Enable next step after completion
            function enableNextStep(currentAction) {
                var nextActions = {
                    'analyze': 'migrate',
                    'migrate': 'verify',
                    'verify': 'replace-shortcodes',
                    'replace-shortcodes': 'cleanup'
                };
                
                var nextAction = nextActions[currentAction];
                if (nextAction) {
                    $('[data-action="' + nextAction + '"]').prop('disabled', false);
                }
            }
            
            // Cleanup confirmation checkbox
            $('#confirm-cleanup').on('change', function() {
                $('[data-action="cleanup"]').prop('disabled', !$(this).is(':checked'));
            });
            
        });
        </script>
        
        <?php
    }
    
    /**
     * AJAX: Analyze data
     */
    public function ajax_analyze() {
        check_ajax_referer('sfmf_action', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'sf-to-mf-migrator')));
        }
        
        global $wpdb;
        $table_usermeta = $wpdb->prefix . 'usermeta';
        
        // Get Simple Favorites data
        $sf_users = $wpdb->get_results("
            SELECT user_id, meta_value 
            FROM {$table_usermeta} 
            WHERE meta_key = 'simplefavorites'
        ");
        
        if (empty($sf_users)) {
            wp_send_json_error(array('message' => __('No Simple Favorites data found. Make sure the plugin has been used before migration.', 'sf-to-mf-migrator')));
        }
        
        // Get My Favorites data (if any exists)
        $mf_users = $wpdb->get_results("
            SELECT user_id, meta_value 
            FROM {$table_usermeta} 
            WHERE meta_key = 'ccc_my_favorite_post_ids'
        ");
        
        $total_users = count($sf_users);
        $total_favorites = 0;
        $already_migrated = count($mf_users);
        
        // Count total favorites
        foreach ($sf_users as $user) {
            $data = maybe_unserialize($user->meta_value);
            if (is_array($data)) {
                foreach ($data as $site_data) {
                    if (isset($site_data['posts']) && is_array($site_data['posts'])) {
                        $total_favorites += count($site_data['posts']);
                    }
                }
            }
        }
        
        // Sample users preview
        $sample_users = array_slice($sf_users, 0, 5);
        $preview = '<div style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin: 15px 0;"><h4>' . __('Sample Users Preview:', 'sf-to-mf-migrator') . '</h4><ul style="list-style: none; padding: 0;">';
        
        foreach ($sample_users as $user) {
            $data = maybe_unserialize($user->meta_value);
            $count = 0;
            if (is_array($data)) {
                foreach ($data as $site_data) {
                    if (isset($site_data['posts']) && is_array($site_data['posts'])) {
                        $count += count($site_data['posts']);
                    }
                }
            }
            $preview .= '<li style="padding: 5px 0;">👤 User ID <strong>' . $user->user_id . '</strong>: ' . $count . ' favorites</li>';
        }
        
        $preview .= '</ul></div>';
        
        $html = '<div class="sfmf-success-box">';
        $html .= '<h3>✅ ' . __('Analysis Complete', 'sf-to-mf-migrator') . '</h3>';
        $html .= '<div class="sfmf-stats">';
        $html .= '<div class="sfmf-stat"><span class="sfmf-stat-number">' . $total_users . '</span><span class="sfmf-stat-label">' . __('Users with favorites', 'sf-to-mf-migrator') . '</span></div>';
        $html .= '<div class="sfmf-stat"><span class="sfmf-stat-number">' . $total_favorites . '</span><span class="sfmf-stat-label">' . __('Total favorites', 'sf-to-mf-migrator') . '</span></div>';
        $html .= '<div class="sfmf-stat"><span class="sfmf-stat-number">' . $already_migrated . '</span><span class="sfmf-stat-label">' . __('Already in My Favorites', 'sf-to-mf-migrator') . '</span></div>';
        $html .= '</div>';
        $html .= $preview;
        
        if ($already_migrated > 0) {
            $html .= '<div class="sfmf-warning-box"><span class="dashicons dashicons-warning"></span><p>' . __('Some users already have My Favorites data. The migration will overwrite this data with Simple Favorites information.', 'sf-to-mf-migrator') . '</p></div>';
        }
        
        $html .= '<p class="description">' . __('Everything looks good! You can proceed to Step 2 to start the migration.', 'sf-to-mf-migrator') . '</p>';
        $html .= '</div>';
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX: Migrate data
     */
    public function ajax_migrate() {
        check_ajax_referer('sfmf_action', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'sf-to-mf-migrator')));
        }
        
        global $wpdb;
        $table_usermeta = $wpdb->prefix . 'usermeta';
        
        // Get all Simple Favorites users
        $sf_users = $wpdb->get_results("
            SELECT user_id, meta_value 
            FROM {$table_usermeta} 
            WHERE meta_key = 'simplefavorites'
        ");
        
        $migrated = 0;
        $errors = 0;
        $details = '<div class="sfmf-migration-log">';
        
        foreach ($sf_users as $user) {
            $user_id = $user->user_id;
            $sf_data = maybe_unserialize($user->meta_value);
            
            // Extract post IDs
            $post_ids = array();
            if (is_array($sf_data)) {
                foreach ($sf_data as $site_data) {
                    if (isset($site_data['posts']) && is_array($site_data['posts'])) {
                        $post_ids = array_merge($post_ids, $site_data['posts']);
                    }
                }
            }
            
            // Remove duplicates and convert to comma-separated string
            $post_ids = array_unique($post_ids);
            $favorites_string = implode(',', $post_ids);
            
            // Save to database
            $result = update_user_meta($user_id, 'ccc_my_favorite_post_ids', $favorites_string);
            
            if ($result !== false || get_user_meta($user_id, 'ccc_my_favorite_post_ids', true) === $favorites_string) {
                $migrated++;
                $details .= '<div class="sfmf-log-success">✅ User ' . $user_id . ': ' . count($post_ids) . ' favorites migrated</div>';
            } else {
                $errors++;
                $details .= '<div class="sfmf-log-error">❌ User ' . $user_id . ': Migration failed</div>';
            }
        }
        
        $details .= '</div>';
        
        $html = '<div class="sfmf-success-box">';
        $html .= '<h3>✅ ' . __('Migration Complete', 'sf-to-mf-migrator') . '</h3>';
        $html .= '<div class="sfmf-stats">';
        $html .= '<div class="sfmf-stat"><span class="sfmf-stat-number">' . $migrated . '</span><span class="sfmf-stat-label">' . __('Users migrated', 'sf-to-mf-migrator') . '</span></div>';
        if ($errors > 0) {
            $html .= '<div class="sfmf-stat sfmf-stat-error"><span class="sfmf-stat-number">' . $errors . '</span><span class="sfmf-stat-label">' . __('Errors', 'sf-to-mf-migrator') . '</span></div>';
        }
        $html .= '</div>';
        $html .= '<details><summary>' . __('View detailed log', 'sf-to-mf-migrator') . '</summary>' . $details . '</details>';
        $html .= '<p class="description">' . __('Please proceed to verify the migration in Step 3.', 'sf-to-mf-migrator') . '</p>';
        $html .= '</div>';
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX: Verify migration
     */
    public function ajax_verify() {
        check_ajax_referer('sfmf_action', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'sf-to-mf-migrator')));
        }
        
        global $wpdb;
        $table_usermeta = $wpdb->prefix . 'usermeta';
        
        // Compare both systems
        $comparison = $wpdb->get_results("
            SELECT 
                sf.user_id,
                sf.meta_value as simple_favorites,
                mf.meta_value as my_favorites
            FROM {$table_usermeta} sf
            LEFT JOIN {$table_usermeta} mf 
                ON sf.user_id = mf.user_id 
                AND mf.meta_key = 'ccc_my_favorite_post_ids'
            WHERE sf.meta_key = 'simplefavorites'
            LIMIT 20
        ");
        
        $correct = 0;
        $incorrect = 0;
        
        $table = '<table class="widefat"><thead><tr><th>' . __('User ID', 'sf-to-mf-migrator') . '</th><th>' . __('Simple Favorites', 'sf-to-mf-migrator') . '</th><th>' . __('My Favorites', 'sf-to-mf-migrator') . '</th><th>' . __('Status', 'sf-to-mf-migrator') . '</th></tr></thead><tbody>';
        
        foreach ($comparison as $row) {
            $sf_data = maybe_unserialize($row->simple_favorites);
            $sf_posts = array();
            
            if (is_array($sf_data)) {
                foreach ($sf_data as $site_data) {
                    if (isset($site_data['posts']) && is_array($site_data['posts'])) {
                        $sf_posts = array_merge($sf_posts, $site_data['posts']);
                    }
                }
            }
            
            $sf_posts = array_unique($sf_posts);
            sort($sf_posts);
            
            $mf_posts = $row->my_favorites ? explode(',', $row->my_favorites) : array();
            sort($mf_posts);
            
            $match = ($sf_posts == $mf_posts);
            
            if ($match) {
                $correct++;
                $status = '<span style="color: #28a745;">✅ ' . __('Match', 'sf-to-mf-migrator') . '</span>';
            } else {
                $incorrect++;
                $status = '<span style="color: #dc3232;">❌ ' . __('Mismatch', 'sf-to-mf-migrator') . '</span>';
            }
            
            $table .= '<tr>';
            $table .= '<td><strong>' . $row->user_id . '</strong></td>';
            $table .= '<td><small>[' . implode(', ', $sf_posts) . ']</small></td>';
            $table .= '<td><code>' . $row->my_favorites . '</code></td>';
            $table .= '<td>' . $status . '</td>';
            $table .= '</tr>';
        }
        
        $table .= '</tbody></table>';
        
        if ($incorrect === 0) {
            $html = '<div class="sfmf-success-box">';
            $html .= '<h3>🎉 ' . __('Perfect Match!', 'sf-to-mf-migrator') . '</h3>';
            $html .= '<p>' . __('All favorites have been migrated correctly. Data integrity verified.', 'sf-to-mf-migrator') . '</p>';
            $html .= $table;
            $html .= '<div class="sfmf-info-box"><p>' . __('You can now proceed to Step 4 to replace shortcodes, or skip directly to Step 5 to clean up old data.', 'sf-to-mf-migrator') . '</p></div>';
            $html .= '</div>';
        } else {
            $html = '<div class="sfmf-warning-box">';
            $html .= '<h3>⚠️ ' . __('Verification Issues Found', 'sf-to-mf-migrator') . '</h3>';
            $html .= '<p>' . sprintf(__('%d users have mismatched data. Please review before proceeding.', 'sf-to-mf-migrator'), $incorrect) . '</p>';
            $html .= $table;
            $html .= '</div>';
        }
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX: Replace shortcodes
     */
    public function ajax_replace_shortcodes() {
        check_ajax_referer('sfmf_action', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'sf-to-mf-migrator')));
        }
        
        global $wpdb;
        
        $replacements = array(
            '[favorite_button]' => '[ccc_my_favorite_select_button]',
            '[user_favorites]' => '[ccc_my_favorite_list_menu]'
        );
        
        $total_replaced = 0;
        $details = '<div class="sfmf-replacement-log">';
        
        // 1. Replace in post content
        $posts = $wpdb->get_results("
            SELECT ID, post_content 
            FROM {$wpdb->posts} 
            WHERE post_content LIKE '%[favorite_button%' 
               OR post_content LIKE '%[user_favorites%'
        ");
        
        $posts_updated = 0;
        foreach ($posts as $post) {
            $new_content = str_replace(
                array_keys($replacements),
                array_values($replacements),
                $post->post_content
            );
            
            if ($new_content !== $post->post_content) {
                $wpdb->update(
                    $wpdb->posts,
                    array('post_content' => $new_content),
                    array('ID' => $post->ID)
                );
                $posts_updated++;
                $details .= '<div class="sfmf-log-success">✅ Post ID ' . $post->ID . ' updated</div>';
            }
        }
        
        $total_replaced += $posts_updated;
        
        // 2. Replace in post meta (for page builders)
        $meta_keys_to_check = array(
            '_elementor_data',
            '_et_pb_post_content',
            '_wpb_shortcodes_custom_css',
            'divi_content'
        );
        
        $meta_updated = 0;
        foreach ($meta_keys_to_check as $meta_key) {
            $metas = $wpdb->get_results($wpdb->prepare("
                SELECT post_id, meta_value 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = %s 
                AND (meta_value LIKE '%%favorite_button%%' OR meta_value LIKE '%%user_favorites%%')
            ", $meta_key));
            
            foreach ($metas as $meta) {
                $new_value = str_replace(
                    array_keys($replacements),
                    array_values($replacements),
                    $meta->meta_value
                );
                
                if ($new_value !== $meta->meta_value) {
                    update_post_meta($meta->post_id, $meta_key, $new_value);
                    $meta_updated++;
                    $details .= '<div class="sfmf-log-success">✅ Post meta (ID ' . $meta->post_id . ', key: ' . $meta_key . ') updated</div>';
                }
            }
        }
        
        $total_replaced += $meta_updated;
        
        // 3. Replace in widgets
        $widgets = get_option('widget_text');
        $widgets_updated = 0;
        
        if (is_array($widgets)) {
            foreach ($widgets as $key => $widget) {
                if (isset($widget['text'])) {
                    $new_text = str_replace(
                        array_keys($replacements),
                        array_values($replacements),
                        $widget['text']
                    );
                    
                    if ($new_text !== $widget['text']) {
                        $widgets[$key]['text'] = $new_text;
                        $widgets_updated++;
                    }
                }
            }
            
            if ($widgets_updated > 0) {
                update_option('widget_text', $widgets);
                $details .= '<div class="sfmf-log-success">✅ ' . $widgets_updated . ' text widgets updated</div>';
            }
        }
        
        $total_replaced += $widgets_updated;
        
        $details .= '</div>';
        
        $html = '<div class="sfmf-success-box">';
        $html .= '<h3>✅ ' . __('Shortcode Replacement Complete', 'sf-to-mf-migrator') . '</h3>';
        $html .= '<div class="sfmf-stats">';
        $html .= '<div class="sfmf-stat"><span class="sfmf-stat-number">' . $posts_updated . '</span><span class="sfmf-stat-label">' . __('Posts updated', 'sf-to-mf-migrator') . '</span></div>';
        $html .= '<div class="sfmf-stat"><span class="sfmf-stat-number">' . $meta_updated . '</span><span class="sfmf-stat-label">' . __('Meta fields updated', 'sf-to-mf-migrator') . '</span></div>';
        $html .= '<div class="sfmf-stat"><span class="sfmf-stat-number">' . $widgets_updated . '</span><span class="sfmf-stat-label">' . __('Widgets updated', 'sf-to-mf-migrator') . '</span></div>';
        $html .= '</div>';
        
        if ($total_replaced > 0) {
            $html .= '<details><summary>' . __('View detailed log', 'sf-to-mf-migrator') . '</summary>' . $details . '</details>';
            $html .= '<div class="sfmf-info-box"><p>' . __('Please test your pages to ensure shortcodes are working correctly with My Favorites.', 'sf-to-mf-migrator') . '</p></div>';
        } else {
            $html .= '<p>' . __('No shortcodes found to replace.', 'sf-to-mf-migrator') . '</p>';
        }
        
        $html .= '</div>';
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX: Cleanup old data
     */
    public function ajax_cleanup() {
        check_ajax_referer('sfmf_action', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'sf-to-mf-migrator')));
        }
        
        global $wpdb;
        $table_usermeta = $wpdb->prefix . 'usermeta';
        
        // Delete Simple Favorites data
        $deleted = $wpdb->delete(
            $table_usermeta,
            array('meta_key' => 'simplefavorites'),
            array('%s')
        );
        
        $html = '<div class="sfmf-success-box">';
        $html .= '<h3>✅ ' . __('Cleanup Complete', 'sf-to-mf-migrator') . '</h3>';
        $html .= '<p>' . sprintf(__('Successfully deleted %d Simple Favorites entries from the database.', 'sf-to-mf-migrator'), $deleted) . '</p>';
        $html .= '<div class="sfmf-info-box">';
        $html .= '<h4>' . __('🎉 Migration Finished!', 'sf-to-mf-migrator') . '</h4>';
        $html .= '<p>' . __('Your migration is now complete. You can:', 'sf-to-mf-migrator') . '</p>';
        $html .= '<ul>';
        $html .= '<li>' . __('Deactivate and delete the Simple Favorites plugin', 'sf-to-mf-migrator') . '</li>';
        $html .= '<li>' . __('Deactivate this migration tool', 'sf-to-mf-migrator') . '</li>';
        $html .= '<li>' . __('Start using My Favorites exclusively', 'sf-to-mf-migrator') . '</li>';
        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</div>';
        
        wp_send_json_success(array('html' => $html));
    }
}

// Initialize plugin
new SF_To_MF_Migrator();
