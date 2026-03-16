<?php
/**
 * Mobile Menu Helper Template
 
 */
?>

<!-- ============================================
     MOBILE MENU TOGGLE IMPLEMENTATION
     ============================================ -->

<!-- 1. Add this hidden checkbox at the very beginning of <body> tag -->
<input type="checkbox" id="sidebar-toggle" class="hidden">

<!-- 2. Add this label after the checkbox (before sidebar) -->
<label for="sidebar-toggle" class="mobile-menu-toggle" aria-label="Toggle navigation menu" title="Toggle Menu">
    <i class="fas fa-bars"></i>
</label>

<!-- 3. Then your existing sidebar structure stays the same -->
<aside class="sidebar">
    <!-- ... sidebar content ... -->
</aside>



<!-- ============================================
     COMPLETE PAGE STRUCTURE EXAMPLE
     ============================================ -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Title - Canteen System</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Mobile Menu Checkbox Toggle (hidden) -->
    <input type="checkbox" id="sidebar-toggle" class="hidden">
    
    <!-- Mobile Menu Toggle Button -->
    <label for="sidebar-toggle" class="mobile-menu-toggle" aria-label="Toggle navigation menu">
        <i class="fas fa-bars"></i>
    </label>

    <div class="dashboard">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-utensils"></i>
                </div>
                <div>
                    <h2>Canteen System</h2>
                    <span>Customer</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <!-- Navigation items -->
            </nav>
            
            <div class="sidebar-footer">
                <!-- User info and logout -->
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Page content goes here -->
        </main>
    </div>

</body>
</html>

<!-- ============================================
     CSS CLASSES FOR RESPONSIVE DESIGN
     ============================================ -->

<!-- Hidden on mobile, visible on desktop -->
<div class="hidden-mobile">Desktop only content</div>

<!-- Hidden on desktop, visible on mobile -->
<div class="hidden-desktop">Mobile only content</div>

<!-- Full width on mobile, auto on desktop -->
<button class="btn btn-primary">Full Width on Mobile</button>

<!-- Responsive grid automatically handles layout -->
<div class="menu-items-grid">
    <!-- Items automatically stack on mobile -->
    <div class="menu-item-card">Item 1</div>
    <div class="menu-item-card">Item 2</div>
    <div class="menu-item-card">Item 3</div>
</div>

<!-- ============================================
     TIPS FOR MOBILE OPTIMIZATION
     ============================================ -->

<!--
1. VIEWPORT META TAG (REQUIRED):
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   This tells mobile browsers how to render the page.

2. TOUCH-FRIENDLY SPACING:
   - Buttons should be at least 44px × 44px
   - Clickable areas should have space between them
   - Use appropriate padding for touch targets

3. RESPONSIVE IMAGES:
   - Use <img> without fixed width/height attributes
   - CSS will handle responsive sizing automatically
   - For background images, use background-size: cover

4. MOBILE-FIRST APPROACH:
   - Start with mobile-friendly base styles
   - Then enhance for larger screens
   - Don't hide content on mobile - make it accessible

5. AVOID HORIZONTAL SCROLLING:
   - Use responsive grids instead of fixed widths
   - Use flexbox for flexible layouts
   - Test on actual mobile devices

6. READABILITY:
   - Minimum 13px font size on mobile
   - Use 16px for form inputs to prevent iOS zoom
   - Maintain adequate line height (1.5-1.6)

7. PERFORMANCE:
   - Minimize CSS file size
   - Use hardware-accelerated scrolling
   - Optimize images for mobile

8. TESTING:
   - Test on multiple devices
   - Use Chrome DevTools device emulation
   - Check different orientations
   - Verify touch interactions
-->
