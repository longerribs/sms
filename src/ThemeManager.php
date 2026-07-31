<?php
/**
 * sms/src/ThemeManager.php
 * 
 * Advanced Theme Manager for Clayon SMS SaaS.
 * Manages preset theme loading, custom color overrides, system theme defaults,
 * and dynamic CSS variable injection.
 */

class ThemeManager {
    private $themesDir;
    private $currentTheme;
    private $themeData;
    private $clientId;
    
    public function __construct($clientId = null) {
        $this->themesDir = __DIR__ . '/../themes';
        $this->clientId = $clientId;
        $this->currentTheme = $this->loadCurrentTheme();
        $this->themeData = $this->loadThemeData();
    }
    
    /**
     * Get list of available themes
     */
    public function getAvailableThemes() {
        if (!is_dir($this->themesDir)) {
            return [];
        }
        
        $themes = [];
        $files = glob($this->themesDir . '/*.json');
        
        // Defined sort order for UI
        $order = ['default', 'light', 'dark', 'primary', 'secondary', 'success', 'info', 'warning', 'danger', 'custom'];
        
        foreach ($files as $file) {
            $name = basename($file, '.json');
            $data = json_decode(file_get_contents($file), true) ?: [];
            
            $themes[$name] = [
                'id' => $name,
                'label' => $data['name'] ?? ucfirst($name),
                'description' => $data['description'] ?? 'Preset theme',
                'is_light' => $data['is_light'] ?? false,
                'path' => $file
            ];
        }
        
        // Sort according to defined order
        uksort($themes, function($a, $b) use ($order) {
            $posA = array_search($a, $order);
            $posB = array_search($b, $order);
            if ($posA === false) $posA = 99;
            if ($posB === false) $posB = 99;
            return $posA <=> $posB;
        });

        return $themes;
    }
    
    /**
     * Load current theme from session
     */
    private function loadCurrentTheme() {
        return $_SESSION['CLAYON_THEME'] ?? 'default';
    }
    
    /**
     * Load theme JSON
     */
    private function loadThemeData() {
        $themePath = $this->themesDir . '/' . $this->currentTheme . '.json';
        
        if (!file_exists($themePath)) {
            $themePath = $this->themesDir . '/default.json';
        }
        
        if (!file_exists($themePath)) {
            return $this->getDefaultTheme();
        }
        
        $data = json_decode(file_get_contents($themePath), true);
        return $data ?: $this->getDefaultTheme();
    }
    
    /**
     * Fallback default theme (Cyberpunk Dark)
     */
    public function getDefaultTheme() {
        return [
            'name' => 'Default Dark',
            'description' => 'Cyberpunk dark aesthetic with vivid contrast',
            'is_light' => false,
            'colors' => [
                'primary' => '#6366f1',
                'secondary' => '#a855f7',
                'primary-glow' => 'rgba(99, 102, 241, 0.4)',
                'bg-main' => '#030712',
                'card-bg' => 'rgba(255, 255, 255, 0.04)',
                'card-hover' => 'rgba(255, 255, 255, 0.08)',
                'text-primary' => '#f8fafc',
                'text-muted' => '#94a3b8',
                'text-number' => '#f0f9ff',
                'border' => '#334155',
                'border-light' => 'rgba(255, 255, 255, 0.1)',
                'divider' => 'rgba(255, 255, 255, 0.05)',
                'glass-border' => 'rgba(255, 255, 255, 0.1)',
                'accent-success' => '#10b981',
                'accent-warning' => '#f59e0b',
                'accent-error' => '#ef4444',
                'accent-info' => '#3b82f6',
                'accent-danger' => '#dc2626',
                'chart-1' => '#6366f1',
                'chart-2' => '#a855f7',
                'chart-3' => '#06b6d4',
            ]
        ];
    }
    
    /**
     * Switch active theme
     */
    public function setTheme($themeName) {
        $available = $this->getAvailableThemes();
        if (!isset($available[$themeName])) {
            return false;
        }
        
        $_SESSION['CLAYON_THEME'] = $themeName;
        $this->currentTheme = $themeName;
        $this->themeData = $this->loadThemeData();
        return true;
    }
    
    /**
     * Get theme colors
     */
    public function getThemeColors() {
        return $this->themeData['colors'] ?? $this->getDefaultTheme()['colors'];
    }
    
    /**
     * Get current theme name
     */
    public function getCurrentTheme() {
        return $this->currentTheme;
    }
    
    /**
     * Generate CSS variable declarations
     */
    public function generateCSSVariables() {
        $css = ':root {' . PHP_EOL;
        $colors = $this->getThemeColors();
        
        foreach ($colors as $key => $value) {
            $css .= "    --" . $key . ": " . $value . ";" . PHP_EOL;
        }
        
        // Deprecated aliases
        $css .= "    --glass-bg: var(--card-bg);" . PHP_EOL;
        $css .= "    --text-main: var(--text-primary);" . PHP_EOL;
        
        $css .= '}' . PHP_EOL;
        
        return $css;
    }
    
    /**
     * Save custom theme
     */
    public function saveCustomTheme($colorOverrides = []) {
        $customTheme = [
            'name' => 'Custom Theme',
            'description' => 'User configured color palette',
            'is_light' => false,
            'colors' => $this->getDefaultTheme()['colors']
        ];
        
        if (is_array($colorOverrides)) {
            foreach ($colorOverrides as $key => $value) {
                $cleanKey = ltrim($key, '-');
                $customTheme['colors'][$cleanKey] = $value;
            }
        }
        
        $customPath = $this->themesDir . '/custom.json';
        file_put_contents($customPath, json_encode($customTheme, JSON_PRETTY_PRINT));
        
        return $this->setTheme('custom');
    }
    
    /**
     * Reset to default
     */
    public function resetToDefault() {
        return $this->setTheme('default');
    }
}
