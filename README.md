🌟 Instant Image Gallery 🌟

just copy and paste a single file and it will instantly create an image gallery from images in the folder on your server where you've put the file.

📋 FEATURES

🔒 Security & Performance
• Security Headers: X-Frame-Options, XSS Protection, Content-Type Options, Referrer Policy
• CSRF Protection: Automatic token generation
• Input Validation: Sanitizes all user inputs
• Error Handling: Graceful error management

🖼️ Image Support
• Multiple Formats: JPG, JPEG, PNG, GIF, SVG, WEBP
• SVG Special Handling: Proper dimension extraction
• Automatic Dimension Detection: Width and height for all images
• File Size Display: Human-readable formatting

🎨 User Interface
• Dark/Light Theme: Toggle with persistent storage
• Responsive Masonry Layout: Adaptive grid for all screens
• Modal Viewer: Full-screen image viewing with navigation
• Lazy Loading: Images load on scroll
• Hover Information: File details (optional)
• Keyboard Navigation: Arrow keys and Escape

⚡ Advanced Functionality
• Sorting: By modification date (newest first)
• Scroll-to-Top: Smooth navigation
• Image Fallbacks: Graceful error handling
• Mobile Optimized: Touch-friendly interface

⚙️ CONFIGURATION OPTIONS

👀 Display Settings
• Show/Hide Info: Toggle file information
• Theme Persistence: 30-day cookie storage
• Grid Columns: Responsive (1-4 columns)

⚡ Performance Options
• Lazy Loading: Images load on scroll
• Caching: Can add browser caching headers
• Image Optimization: Consider compression for large images

📋 REQUIREMENTS

🖥️ Server Requirements
• PHP 7.4+ (recommended 8.0+)
• Web Server: Apache, Nginx, or any PHP-compatible server
• File Permissions: Read access to image files

🔧 PHP Extensions
• gd (for image processing)
• fileinfo (for MIME type detection)
• mbstring (for string handling)

🌐 Browser Support
• Modern browsers (Chrome, Firefox, Safari, Edge)
• Mobile browsers (iOS Safari, Chrome Mobile)
• Fallbacks for older browsers

🚀 HOW TO USE

📁 Basic Setup

Upload the PHP file to any directory containing images

Ensure images are in the same directory (no subdirectory scanning)

Access via browser - the gallery auto-generates!

🖼️ Supported Image Formats
• .jpg, .jpeg - JPEG images
• .png - Portable Network Graphics
• .gif - Animated and static GIFs
• .svg - Scalable Vector Graphics
• .webp - Modern WebP format

🎮 User Controls
• Info Toggle: Show/hide file information overlays
• Theme Switch: Toggle between light and dark modes
• Refresh: Reload to detect new images
• Modal Navigation: Use arrows or keyboard to browse
• Scroll-to-Top: Quick navigation to gallery top

🔧 CUSTOMIZATION GUIDE
Edit the file and change the code required:

📝 Modifying Allowed Extensions

$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp', 'tiff'];

🔄 Changing Sort Order

// Sort by filename
usort($imageInfo, function($a, $b) {
    return strcmp($a['filename'], $b['filename']);
});

// Sort by file size
usort($imageInfo, function($a, $b) {
    return $b['size'] - $a['size'];
});

🎨 Theme Customization
Modify in the <script> section:

colors: {
    dark: {
        100: '#1e293b', // Header
        200: '#172033', // Cards
        300: '#0f172a', // Background
    }

📂 Adding Subdirectory Support

function scanDirectory($dir) {
    $files = [];
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        
        if (is_dir($path)) {
            $files = array_merge($files, scanDirectory($path));
        } else {
            $files[] = $path;
        }
    }
    return $files;
}

$files = scanDirectory('.');

🔒 Security Enhancements

// Basic HTTP Authentication
if (!isset($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] !== 'username' || 
    $_SERVER['PHP_AUTH_PW'] !== 'password') {
    header('WWW-Authenticate: Basic realm="Gallery"');
    header('HTTP/1.0 401 Unauthorized');
    die('Access denied');
}

🛠️ TROUBLESHOOTING

❓ Common Issues

No Images Displaying
• Check file permissions
• Verify supported formats
• Ensure images in same directory

SVG Dimensions Not Showing
• SVG needs width/height or viewBox
• Check SVG file structure

Performance Issues
• Add pagination for large collections
• Implement image caching
• Optimize image sizes

📝 Error Logging
• Script logs to PHP error log
• Check server error log for debugging

🔒 SECURITY NOTES
• CSRF protection for form submissions
• All user inputs validated and sanitized
• Security headers prevent common vulnerabilities
• File paths properly handled

📱 MOBILE CONSIDERATIONS
• Touch-friendly interface
• Responsive design adapts to screen size
• Optimized loading for mobile networks

🎯 Perfect for: Portfolios, photo galleries, quick image browsing!

Just copy and paste a single file and your new instant gallery is ready! 📋✨
