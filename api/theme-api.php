<?php
/**
 * sms/api/theme-api.php
 * 
 * Theme management API endpoint
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../src/ThemeManager.php';

$clientId = $_SESSION['CLAYON_CLIENT_ID'] ?? null;
$theme = new ThemeManager($clientId);
$action = $_GET['action'] ?? 'list';

if ($action !== 'css') {
    header('Content-Type: application/json');
}

switch ($action) {
    case 'list':
        echo json_encode(['themes' => $theme->getAvailableThemes()]);
        break;
        
    case 'switch':
        $themeName = $_POST['theme'] ?? $_GET['theme'] ?? null;
        if (!$themeName) {
            http_response_code(400);
            echo json_encode(['error' => 'Theme name required']);
            exit;
        }
        
        if ($theme->setTheme($themeName)) {
            echo json_encode(['success' => true, 'current' => $theme->getCurrentTheme()]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Theme not found']);
        }
        break;
        
    case 'current':
        echo json_encode(['current' => $theme->getCurrentTheme()]);
        break;
        
    case 'reset':
        if ($theme->resetToDefault()) {
            echo json_encode(['success' => true, 'current' => 'default']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to reset']);
        }
        break;
        
    case 'colors':
        echo json_encode(['colors' => $theme->getThemeColors()]);
        break;
        
    case 'save':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $overrides = $input['colors'] ?? [];
        
        if ($theme->saveCustomTheme($overrides)) {
            echo json_encode(['success' => true, 'current' => 'custom']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save custom theme']);
        }
        break;
        
    case 'css':
        header('Content-Type: text/css');
        echo $theme->generateCSSVariables();
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
