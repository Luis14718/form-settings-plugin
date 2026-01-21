<?php
/**
 * Plugin Updater
 * 
 * Checks GitHub for plugin updates and enables automatic updates from WordPress admin
 * 
 * @package Form_Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class Form_Settings_Plugin_Updater
{
    private $plugin_slug;
    private $plugin_basename;
    private $github_username;
    private $github_repo;
    private $version;
    private $cache_key;
    private $cache_allowed;

    /**
     * Constructor
     */
    public function __construct($plugin_basename, $github_username, $github_repo, $version)
    {
        $this->plugin_basename = $plugin_basename;
        $this->plugin_slug = dirname($plugin_basename);
        $this->github_username = $github_username;
        $this->github_repo = $github_repo;
        $this->version = $version;
        $this->cache_key = 'form_settings_update_' . md5($this->github_repo);
        $this->cache_allowed = true;

        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('site_transient_update_plugins', array($this, 'check_update'));
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);

        // Clear cache on plugin activation
        add_action('admin_init', array($this, 'clear_cache_on_activation'));
    }

    /**
     * Get information from GitHub
     */
    private function get_github_release_info()
    {
        // Check cache first
        if ($this->cache_allowed) {
            $cache = get_transient($this->cache_key);
            if ($cache !== false) {
                return $cache;
            }
        }

        // Get latest release from GitHub API
        $url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest";

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($release)) {
            return false;
        }

        // Cache for 12 hours
        set_transient($this->cache_key, $release, 12 * HOUR_IN_SECONDS);

        return $release;
    }

    /**
     * Check for plugin updates
     */
    public function check_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_github_release_info();

        if ($release === false) {
            return $transient;
        }

        // Get version from tag (remove 'v' prefix if present)
        $new_version = ltrim($release['tag_name'], 'v');

        // Compare versions
        if (version_compare($this->version, $new_version, '<')) {
            $plugin_data = array(
                'slug' => $this->plugin_slug,
                'new_version' => $new_version,
                'url' => $release['html_url'],
                'package' => $release['zipball_url'],
                'tested' => '6.4',
                'requires_php' => '7.0',
            );

            $transient->response[$this->plugin_basename] = (object) $plugin_data;
        }

        return $transient;
    }

    /**
     * Get plugin information for the update screen
     */
    public function plugin_info($false, $action, $response)
    {
        if ($action !== 'plugin_information') {
            return $false;
        }

        if ($response->slug !== $this->plugin_slug) {
            return $false;
        }

        $release = $this->get_github_release_info();

        if ($release === false) {
            return $false;
        }

        $new_version = ltrim($release['tag_name'], 'v');

        $plugin_info = array(
            'name' => 'Form Settings',
            'slug' => $this->plugin_slug,
            'version' => $new_version,
            'author' => '<a href="https://github.com/' . $this->github_username . '">Developer</a>',
            'homepage' => $release['html_url'],
            'requires' => '5.0',
            'tested' => '6.4',
            'requires_php' => '7.0',
            'download_link' => $release['zipball_url'],
            'sections' => array(
                'description' => 'WordPress plugin for managing Contact Form 7 recipients, validation rules, email templates, and error logging.',
                'changelog' => $this->parse_changelog($release['body']),
            ),
        );

        return (object) $plugin_info;
    }

    /**
     * Parse changelog from release notes
     */
    private function parse_changelog($body)
    {
        if (empty($body)) {
            return 'No changelog available.';
        }

        return wpautop($body);
    }

    /**
     * After installation, rename the folder to match plugin slug
     */
    public function after_install($response, $hook_extra, $result)
    {
        global $wp_filesystem;

        $install_directory = plugin_dir_path($result['destination']);
        $wp_filesystem->move($result['destination'], $install_directory . $this->plugin_slug);
        $result['destination'] = $install_directory . $this->plugin_slug;

        if ($this->plugin_basename == $hook_extra['plugin']) {
            activate_plugin($this->plugin_basename);
        }

        return $result;
    }

    /**
     * Clear cache on plugin activation
     */
    public function clear_cache_on_activation()
    {
        delete_transient($this->cache_key);
    }

    /**
     * Clear update cache manually
     */
    public function clear_cache()
    {
        delete_transient($this->cache_key);
    }
}
