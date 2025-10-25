<?php
// Security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// CSRF Protection
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Input validation
function validateInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Function to get image dimensions
function getImageDimensions($filepath, $extension) {
    $dimensions = ['width' => 0, 'height' => 0];
    
    try {
        // For SVG files, we need special handling
        if ($extension === 'svg') {
            $svgContent = file_get_contents($filepath);
            if (preg_match('/<svg[^>]*width="([^"]*)"[^>]*height="([^"]*)"/', $svgContent, $matches)) {
                $dimensions['width'] = intval($matches[1]);
                $dimensions['height'] = intval($matches[2]);
            } elseif (preg_match('/<svg[^>]*viewBox="[^"]*[\s,]+([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)"/', $svgContent, $matches)) {
                $dimensions['width'] = intval($matches[3]);
                $dimensions['height'] = intval($matches[4]);
            }
        } else {
            // For other image types, use getimagesize
            $imageInfo = @getimagesize($filepath);
            if ($imageInfo !== false) {
                $dimensions['width'] = $imageInfo[0];
                $dimensions['height'] = $imageInfo[1];
            }
        }
    } catch (Exception $e) {
        // If we can't get dimensions, return 0
        error_log("Could not get dimensions for $filepath: " . $e->getMessage());
    }
    
    return $dimensions;
}

// Image gallery configuration - look in current directory only
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
$imageInfo = [];

// Scan current directory for images
$files = scandir('.');
foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    
    // Skip directories and the PHP file itself
    if (is_dir($file)) continue;
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') continue;
    
    $fileExtension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    
    if (in_array($fileExtension, $allowedExtensions) && is_file($file)) {
        $dimensions = getImageDimensions($file, $fileExtension);
        
        $imageInfo[] = [
            'filename' => $file,
            'filepath' => $file,
            'size' => filesize($file),
            'width' => $dimensions['width'],
            'height' => $dimensions['height']
        ];
    }
}

// Sort images by modification date (newest first)
usort($imageInfo, function($a, $b) {
    return filemtime($b['filepath']) - filemtime($a['filepath']);
});

// Theme and settings handling
$showInfo = isset($_GET['show_info']) && $_GET['show_info'] === 'true';
$currentTheme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';

if (isset($_GET['theme'])) {
    $currentTheme = $_GET['theme'] === 'dark' ? 'dark' : 'light';
    setcookie('theme', $currentTheme, time() + (86400 * 30), "/"); // 30 days
}

// Helper function to format file sizes
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return number_format($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instant Image Gallery</title>
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            100: '#1e293b',
                            200: '#172033',
                            300: '#0f172a',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out forwards',
                        'zoom-in': 'zoomIn 0.3s ease-out forwards',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        zoomIn: {
                            '0%': { transform: 'scale(0.9)', opacity: '0' },
                            '100%': { transform: 'scale(1)', opacity: '1' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            transition: background-color 0.3s, color 0.3s;
            min-height: 100vh;
        }
        
        .gallery-item {
            transition: all 0.3s ease;
            cursor: pointer;
            break-inside: avoid;
        }
        
        .gallery-item:hover {
            transform: translateY(-2px);
        }
        
        .image-container {
            position: relative;
            overflow: hidden;
        }
        
        .image-info {
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            transition: opacity 0.3s ease;
        }
        
        .modal-overlay {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            max-height: 95vh;
            max-width: 95vw;
            height: 95vh;
            width: auto;
        }
        
        .modal-image-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 0;
            overflow: hidden;
        }
        
        .modal-image {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
        }
        
        .modal-info {
            flex-shrink: 0;
        }
        
        .loading-spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Masonry layout for different aspect ratios */
        .masonry-grid {
            column-count: 1;
            column-gap: 1rem;
        }
        
        @media (min-width: 640px) {
            .masonry-grid {
                column-count: 2;
            }
        }
        
        @media (min-width: 1024px) {
            .masonry-grid {
                column-count: 3;
            }
        }
        
        @media (min-width: 1280px) {
            .masonry-grid {
                column-count: 4;
            }
        }
        
        .scroll-to-top {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.3s, transform 0.3s;
        }
        
        .scroll-to-top.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Lazy loading */
        .lazy-image {
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .lazy-image.loaded {
            opacity: 1;
        }
        
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
        
        /* Ensure images don't break layout */
        .gallery-item img {
            width: 100%;
            height: auto;
            display: block;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-dark-300 text-gray-800 dark:text-gray-200">
    <!-- Header -->
    <header class="bg-white dark:bg-dark-100 shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4">
            <div class="flex flex-col lg:flex-row justify-between items-center space-y-4 lg:space-y-0">
                <div class="flex items-center">

                    <div>
                        <h1 class="text-xl font-bold">Instant Image Gallery</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            <?php echo count($imageInfo); ?> images found in current directory
                        </p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Info Toggle -->
                    <button 
                        id="info-toggle" 
                        class="flex items-center px-4 py-2 rounded-lg bg-gray-200 dark:bg-dark-200 hover:bg-gray-300 dark:hover:bg-dark-100 transition-colors"
                        onclick="toggleInfo()"
                    >
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Info</span>
                        <span class="ml-2 px-2 py-1 text-xs bg-blue-500 text-white rounded-full">
                            <?php echo $showInfo ? 'ON' : 'OFF'; ?>
                        </span>
                    </button>
                    
                    <!-- Theme Toggle -->
                    <button 
                        id="theme-toggle" 
                        class="w-12 h-12 rounded-full bg-gray-200 dark:bg-dark-200 flex items-center justify-center hover:bg-gray-300 dark:hover:bg-dark-100 transition-colors"
                        onclick="toggleTheme()"
                        aria-label="Toggle theme"
                    >
                        <i class="fas <?php echo $currentTheme === 'dark' ? 'fa-sun text-yellow-400' : 'fa-moon text-gray-700'; ?>"></i>
                    </button>
                    
                    <!-- Refresh Button -->
                    <button 
                        onclick="location.reload()" 
                        class="w-12 h-12 rounded-full bg-gray-200 dark:bg-dark-200 flex items-center justify-center hover:bg-gray-300 dark:hover:bg-dark-100 transition-colors"
                        aria-label="Refresh gallery"
                    >
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <?php if (empty($imageInfo)): ?>
            <!-- Empty State -->
            <div class="text-center py-16">
                <i class="fas fa-folder-open text-6xl text-gray-400 mb-4"></i>
                <h2 class="text-2xl font-bold mb-4">No Images Found</h2>
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    Add some images to the current directory.
                </p>
                <div class="text-sm text-gray-500 space-y-2">
                    <p>Supported formats: JPG, PNG, GIF, SVG, WEBP</p>
                    <p>Current directory: <code class="bg-gray-200 dark:bg-dark-200 px-2 py-1 rounded"><?php echo __DIR__; ?></code></p>
                    <p class="text-orange-500">Note: Only files in the current directory are scanned (no subdirectories)</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Gallery Grid -->
            <div class="masonry-grid">
                <?php foreach ($imageInfo as $index => $image): ?>
                    <div class="gallery-item mb-4 bg-white dark:bg-dark-100 rounded-lg shadow-md overflow-hidden animate-fade-in">
                        <div class="image-container">
                            <img 
                                src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='300' viewBox='0 0 400 300'%3E%3Crect width='400' height='300' fill='%23f3f4f6'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='Arial' font-size='16' fill='%239ca3af'%3ELoading...%3C/text%3E%3C/svg%3E"
                                data-src="<?php echo htmlspecialchars($image['filepath']); ?>"
                                alt="<?php echo htmlspecialchars($image['filename']); ?>"
                                class="lazy-image w-full h-auto object-cover"
                                loading="lazy"
                                onclick="openModal(<?php echo $index; ?>)"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
                            >
                            
                            <!-- Fallback for broken images -->
                            <div class="hidden bg-gray-200 dark:bg-dark-200 w-full h-48 flex items-center justify-center">
                                <div class="text-center text-gray-500">
                                    <i class="fas fa-image text-3xl mb-2"></i>
                                    <p class="text-sm">Unable to load image</p>
                                    <p class="text-xs"><?php echo htmlspecialchars($image['filename']); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($showInfo): ?>
                                <!-- Hover info overlay only when show_info=true -->
                                <div class="image-info absolute bottom-0 left-0 right-0 p-3 text-white opacity-0 hover:opacity-100 transition-opacity">
                                    <div class="text-xs flex justify-between items-center">
                                        <span class="truncate flex-1"><?php echo htmlspecialchars($image['filename']); ?></span>
                                        <span class="ml-2 bg-black/50 px-2 py-1 rounded text-xs">
                                            <?php echo formatFileSize($image['size']); ?>
                                        </span>
                                    </div>
                                    <?php if ($image['width'] > 0 && $image['height'] > 0): ?>
                                    <div class="text-xs mt-1 text-center">
                                        <?php echo $image['width']; ?> × <?php echo $image['height']; ?> px
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <!-- No info overlay when show_info=false -->
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Image Modal -->
    <div id="image-modal" class="fixed inset-0 z-50 modal-overlay hidden items-center justify-center p-4">
        <div class="modal-content bg-white dark:bg-dark-100 rounded-lg shadow-2xl overflow-hidden flex flex-col">
            <!-- Close Button -->
            <button 
                onclick="closeModal()" 
                class="absolute top-4 right-4 z-10 w-12 h-12 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-colors shadow-lg"
                aria-label="Close modal"
            >
                <i class="fas fa-times text-xl"></i>
            </button>
            
            <!-- Navigation Buttons -->
            <button 
                id="prev-btn" 
                onclick="navigateModal(-1)" 
                class="absolute left-4 top-1/2 transform -translate-y-1/2 w-12 h-12 bg-white/20 text-white rounded-full flex items-center justify-center hover:bg-white/30 transition-colors backdrop-blur-sm"
                aria-label="Previous image"
            >
                <i class="fas fa-chevron-left text-xl"></i>
            </button>
            
            <button 
                id="next-btn" 
                onclick="navigateModal(1)" 
                class="absolute right-4 top-1/2 transform -translate-y-1/2 w-12 h-12 bg-white/20 text-white rounded-full flex items-center justify-center hover:bg-white/30 transition-colors backdrop-blur-sm"
                aria-label="Next image"
            >
                <i class="fas fa-chevron-right text-xl"></i>
            </button>
            
            <!-- Image Container -->
            <div class="modal-image-container p-4">
                <img 
                    id="modal-image" 
                    src="" 
                    alt="" 
                    class="modal-image"
                    onerror="this.style.display='none'; document.getElementById('modal-fallback').style.display='flex';"
                >
                
                <!-- Fallback for modal broken images -->
                <div id="modal-fallback" class="hidden bg-gray-200 dark:bg-dark-200 w-full h-full flex items-center justify-center">
                    <div class="text-center text-gray-500">
                        <i class="fas fa-exclamation-triangle text-4xl mb-4"></i>
                        <p class="text-lg">Unable to load image</p>
                    </div>
                </div>
            </div>
            
            <!-- Modal Info - Same as grid view when showInfo is enabled -->
            <?php if ($showInfo): ?>
            <div class="modal-info border-t border-gray-200 dark:border-gray-700">
                <div class="p-4">
                    <div class="text-sm space-y-2">
                        <div class="font-medium truncate text-center mb-2" id="modal-info-filename"></div>
                        <div class="text-gray-600 dark:text-gray-400 space-y-1 text-center">
                            <div>Size: <span id="modal-info-size"></span></div>
                            <div>Dimensions: <span id="modal-info-dimensions"></span></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scroll to top button -->
    <button 
        class="scroll-to-top fixed bottom-6 right-6 w-14 h-14 rounded-full bg-blue-500 text-white shadow-lg flex items-center justify-center hover:bg-blue-600 transition-colors"
        onclick="scrollToTop()"
        aria-label="Scroll to top"
    >
        <i class="fas fa-arrow-up text-xl"></i>
    </button>

    <script>
        // Image data from PHP
        const imageData = <?php echo json_encode($imageInfo); ?>;
        let currentModalIndex = -1;

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeGallery();
            setupEventListeners();
            
            // Debug info
            console.log('Total images found:', imageData.length);
            console.log('Images data:', imageData);
        });

        function initializeGallery() {
            lazyLoadImages();
            updateScrollButton();
        }

        function setupEventListeners() {
            // Scroll event for scroll-to-top button
            window.addEventListener('scroll', updateScrollButton);
            
            // Keyboard navigation for modal
            document.addEventListener('keydown', function(e) {
                if (currentModalIndex >= 0) {
                    switch(e.key) {
                        case 'Escape':
                            closeModal();
                            break;
                        case 'ArrowLeft':
                            navigateModal(-1);
                            break;
                        case 'ArrowRight':
                            navigateModal(1);
                            break;
                    }
                }
            });
            
            // Close modal when clicking outside image
            document.getElementById('image-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
        }

        function lazyLoadImages() {
            const lazyImages = document.querySelectorAll('.lazy-image');
            
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            console.log('Loading image:', img.dataset.src);
                            img.src = img.dataset.src;
                            img.classList.add('loaded');
                            imageObserver.unobserve(img);
                            
                            // Handle image load errors
                            img.onerror = function() {
                                console.error('Failed to load image:', this.dataset.src);
                                this.style.display = 'none';
                                const fallback = this.nextElementSibling;
                                if (fallback && fallback.classList.contains('hidden')) {
                                    fallback.classList.remove('hidden');
                                }
                            };
                        }
                    });
                });
                
                lazyImages.forEach(img => imageObserver.observe(img));
            } else {
                // Fallback for older browsers
                lazyImages.forEach(img => {
                    img.src = img.dataset.src;
                    img.classList.add('loaded');
                });
            }
        }

        function openModal(index) {
            currentModalIndex = index;
            const modal = document.getElementById('image-modal');
            const modalImage = document.getElementById('modal-image');
            const modalFallback = document.getElementById('modal-fallback');
            const image = imageData[index];
            
            // Reset modal state
            modalImage.style.display = 'block';
            modalFallback.style.display = 'none';
            
            // Set modal image
            modalImage.src = image.filepath;
            modalImage.alt = image.filename;
            
            // Update modal info if showInfo is enabled
            <?php if ($showInfo): ?>
            document.getElementById('modal-info-filename').textContent = image.filename;
            document.getElementById('modal-info-size').textContent = formatFileSize(image.size);
            
            // Set dimensions
            if (image.width > 0 && image.height > 0) {
                document.getElementById('modal-info-dimensions').textContent = `${image.width} × ${image.height} px`;
            } else {
                document.getElementById('modal-info-dimensions').textContent = 'Unknown';
            }
            <?php endif; ?>
            
            // Show modal
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
            
            // Update navigation buttons state
            updateModalNavigation();
        }

        function closeModal() {
            const modal = document.getElementById('image-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
            currentModalIndex = -1;
        }

        function navigateModal(direction) {
            const newIndex = currentModalIndex + direction;
            if (newIndex >= 0 && newIndex < imageData.length) {
                openModal(newIndex);
            }
        }

        function updateModalNavigation() {
            const prevBtn = document.getElementById('prev-btn');
            const nextBtn = document.getElementById('next-btn');
            
            prevBtn.style.visibility = currentModalIndex > 0 ? 'visible' : 'hidden';
            nextBtn.style.visibility = currentModalIndex < imageData.length - 1 ? 'visible' : 'hidden';
        }

        function toggleInfo() {
            const showInfo = <?php echo $showInfo ? 'false' : 'true'; ?>;
            const url = new URL(window.location);
            url.searchParams.set('show_info', showInfo);
            window.location.href = url.toString();
        }

        function toggleTheme() {
            const currentTheme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
            const url = new URL(window.location);
            url.searchParams.set('theme', currentTheme);
            window.location.href = url.toString();
        }

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function updateScrollButton() {
            const scrollBtn = document.querySelector('.scroll-to-top');
            if (window.scrollY > 300) {
                scrollBtn.classList.add('visible');
            } else {
                scrollBtn.classList.remove('visible');
            }
        }

        // Helper function to format file sizes
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>
</html>