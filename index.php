<?php
// Main ASF Surveillance System Dashboard Page
require_once 'config/database.php';

// Fetch homepage content from database
$page_header = null;
$carousel_slides = [];
$feature_cards = [];
$about_section = null;
$news_articles = [];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Fetch page header
    $stmt = $pdo->prepare("SELECT * FROM homepage_content WHERE content_type = 'page_header' AND is_active = 1 ORDER BY content_order, id LIMIT 1");
    $stmt->execute();
    $page_header = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fetch carousel slides
    $stmt = $pdo->prepare("SELECT * FROM homepage_content WHERE content_type = 'carousel_slide' AND is_active = 1 ORDER BY content_order, id");
    $stmt->execute();
    $carousel_slides = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch feature cards
    $stmt = $pdo->prepare("SELECT * FROM homepage_content WHERE content_type = 'feature_card' AND is_active = 1 ORDER BY content_order, id");
    $stmt->execute();
    $feature_cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch about section
    $stmt = $pdo->prepare("SELECT * FROM homepage_content WHERE content_type = 'about_section' AND is_active = 1 ORDER BY content_order, id LIMIT 1");
    $stmt->execute();
    $about_section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fetch published news articles and announcements
    $stmt = $pdo->prepare("
        SELECT n.*, 
               CONCAT(u.first_name, ' ', u.last_name) as author_name
        FROM news_articles n
        LEFT JOIN user_accounts u ON n.author_id = u.id
        WHERE n.status = 'published'
        AND (n.published_at IS NULL OR n.published_at <= NOW())
        ORDER BY n.published_at DESC, n.created_at DESC
        LIMIT 6
    ");
    $stmt->execute();
    $news_articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $database->closeConnection();
} catch (Exception $e) {
    // If table doesn't exist yet, use default values
    error_log("Error loading homepage content: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Include Head Section -->
  <?php include 'includes/head.php'; ?>
  <!-- Leaflet CSS for GIS Mapping -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <style>
    /* Smooth scrolling for anchor links */
    html {
      scroll-behavior: smooth;
    }

#asfMap {
      height: 600px;
      width: 100%;
      border-radius: 10px;
      background: #0d1b2a;
      z-index: 1;
    }
    
    #simulationMap {
      height: 600px;
      width: 100%;
      border-radius: 10px;
      background: #0d1b2a;
      z-index: 1;
    }
    
    .simulation-controls {
      padding: 10px;
      background: #f8f9fa;
      border-radius: 5px;
      margin-top: 10px;
    }
    
    .control-group {
      display: flex;
      flex-direction: column;
    }
    
    .simulation-status {
      padding: 8px 10px;
      background: #e9ecef;
      border-radius: 5px;
      font-size: 0.875rem;
    }
    
    /* Breathing fade in/out animation for circles */
    @keyframes breathing {
      0%, 100% {
        opacity: 0.5;
      }
      50% {
        opacity: 0.85;
      }
    }
    
    /* Animate SVG path elements (Leaflet circles) with breathing effect */
    .leaflet-interactive[fill] {
      animation: breathing 2.5s ease-in-out infinite;
    }
  </style>
</head>

<body>

  <!-- Include Header Section -->
  <?php include 'includes/header.php'; ?>

  <!-- Include Sidebar Section -->
  <?php include 'includes/sidebar.php'; ?>

  <main id="main" class="main">

        <!-- ASF Carousel Styles -->
        <style>
          .asf-carousel {
            margin-bottom: 2rem;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
          }
          
          .carousel-item img {
            filter: brightness(0.75);
            transition: filter 0.3s ease;
          }
          
          .carousel-item:hover img {
            filter: brightness(0.85);
          }
          
          .carousel-caption {
            background: linear-gradient(to top, rgba(0, 0, 0, 0.85) 0%, rgba(0, 0, 0, 0.5) 70%, transparent 100%);
            border-radius: 15px 15px 0 0;
            padding: 30px 40px;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: left;
          }
          
          .carousel-caption h3 {
            color: #fff;
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
          }
          
          .carousel-caption p {
            color: #f8f9fa;
            font-size: 1.1rem;
            line-height: 1.7;
            margin-bottom: 0;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
          }
          
          .carousel-caption .carousel-badge {
            display: inline-block;
            background: rgba(220, 53, 69, 0.9);
            color: #fff;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
          }
          
          .carousel-indicators {
            bottom: 20px;
            z-index: 15;
          }
          
          .carousel-indicators button {
            width: 12px;
            height: 12px;
            background-color: rgba(255, 255, 255, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            margin: 0 6px;
            padding: 0;
            transition: all 0.3s ease;
          }
          
          .carousel-indicators button.active {
            background-color: #fff;
            border-color: #fff;
            transform: scale(1.2);
          }
          
          .carousel-control-prev,
          .carousel-control-next {
            width: 50px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
          }
          
          .carousel-control-prev:hover,
          .carousel-control-next:hover {
            opacity: 1;
          }
          
          .carousel-control-prev-icon,
          .carousel-control-next-icon {
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            width: 40px;
            height: 40px;
          }
          
          @media (max-width: 768px) {
            .carousel-caption {
              padding: 20px 25px;
            }
            
            .carousel-caption h3 {
              font-size: 1.5rem;
              margin-bottom: 10px;
            }
            
            .carousel-caption p {
              font-size: 0.95rem;
            }
            
            .carousel-caption .carousel-badge {
              font-size: 0.75rem;
              padding: 4px 12px;
            }
          }
          
          /* Map Container Styles */
          .map-container {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
          }
          
          .map-controls {
            background: #fff;
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
          }
          
          .date-filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
          }
          
          .zone-legend, .data-layers {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
          }
          
          .zone-legend strong, .data-layers strong {
            margin-right: 5px;
            color: #495057;
          }
          
          .layer-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
          }
          
          .layer-toggle:hover {
            background: #e9ecef;
            border-color: #0d6efd;
          }
          
          .layer-toggle.active {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
          }
          
          /* Feature Card Styles */
          .feature-card {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 30px;
            height: 280px;
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid rgba(13, 110, 253, 0.1);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
          }
          
          .feature-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(13, 110, 253, 0.15);
            border-color: rgba(13, 110, 253, 0.3);
          }
          
          .feature-icon-wrapper {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
          }
          
          .feature-card:hover .feature-icon-wrapper {
            transform: rotate(5deg) scale(1.1);
            box-shadow: 0 10px 25px rgba(13, 110, 253, 0.4);
          }
          
          .feature-icon-wrapper i {
            font-size: 32px;
            color: #fff;
            z-index: 2;
            position: relative;
          }
          
          .feature-content h5 {
            color: #2c3e50;
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 15px;
            line-height: 1.3;
            transition: color 0.3s ease;
          }
          
          .feature-card:hover .feature-content h5 {
            color: #0d6efd;
          }
          
          .feature-content p {
            color: #6c757d;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 0;
          }
          
          /* News & Announcements Styles */
          .news-card {
            background: #fff;
            border-radius: 15px;
            padding: 20px;
            height: 100%;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
          }
          
          .news-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border-color: #0d6efd;
          }
          
          .news-card .news-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
          }
          
          .news-card .news-category {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
          }
          
          .news-card .news-category.news {
            background-color: #e3f2fd;
            color: #1976d2;
          }
          
          .news-card .news-category.announcement {
            background-color: #fff3e0;
            color: #f57c00;
          }
          
          .news-card .news-category.alert {
            background-color: #ffebee;
            color: #c62828;
          }
          
          .news-card .news-category.guideline {
            background-color: #e8f5e9;
            color: #388e3c;
          }
          
          .news-card .news-category.update {
            background-color: #f3e5f5;
            color: #7b1fa2;
          }
          
          .news-card .news-title {
            color: #2c3e50;
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 10px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
          }
          
          .news-card:hover .news-title {
            color: #0d6efd;
          }
          
          .news-card .news-excerpt {
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
          }
          
          .news-card .news-meta {
            color: #adb5bd;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 15px;
            border-top: 1px solid #e9ecef;
            padding-top: 12px;
          }
          
          .news-card .news-meta i {
            margin-right: 5px;
          }
          
          /* Alert Styles */
          .alert-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
          }
          
          .alert-card.alert-danger {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%);
            border-left: 4px solid #dc3545;
          }
          
          .alert-card.alert-warning {
            background: linear-gradient(135deg, #fffbf0 0%, #fff5e0 100%);
            border-left: 4px solid #ffc107;
          }
          
          .alert-card.alert-info {
            background: linear-gradient(135deg, #f0f8ff 0%, #e0f0ff 100%);
            border-left: 4px solid #0d6efd;
          }
          
          /* Animation for card appearance */
          @keyframes fadeInUp {
            from {
              opacity: 0;
              transform: translateY(30px);
            }
            to {
              opacity: 1;
              transform: translateY(0);
            }
          }
          
          .stat-card, .feature-card {
            animation: fadeInUp 0.6s ease-out;
          }
          
          .stat-card:nth-child(1) { animation-delay: 0.1s; }
          .stat-card:nth-child(2) { animation-delay: 0.2s; }
          .stat-card:nth-child(3) { animation-delay: 0.3s; }
          .stat-card:nth-child(4) { animation-delay: 0.4s; }
          
          /* Responsive adjustments */
          @media (max-width: 768px) {
            .feature-card {
              height: 250px;
              padding: 25px;
            }
            
            #asfMap {
              height: 400px;
            }
            
            .map-controls {
              flex-direction: column;
              align-items: stretch;
            }
            
            .layer-toggle {
              width: 100%;
              justify-content: center;
            }
          }
        </style>
    <section class="section dashboard">
      <div class="row">
        <div class="col-lg-12">
          <!-- Page Header -->
          <div class="text-center mb-4">
            <h2><?php echo htmlspecialchars($page_header['title'] ?? 'ASF Surveillance Dashboard'); ?></h2>
            <p><?php echo htmlspecialchars($page_header['subtitle'] ?? 'Real-time monitoring and predictive analysis for African Swine Fever in CALABARZON'); ?></p>
          </div>

          <!-- ASF Information Carousel -->
          <?php if (!empty($carousel_slides)): ?>
          <div id="asfCarousel" class="carousel slide asf-carousel" data-bs-ride="carousel" data-bs-interval="5000">
            <!-- Carousel Indicators -->
            <div class="carousel-indicators">
              <?php foreach ($carousel_slides as $index => $slide): ?>
              <button type="button" data-bs-target="#asfCarousel" data-bs-slide-to="<?php echo $index; ?>" class="<?php echo $index === 0 ? 'active' : ''; ?>" aria-current="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-label="Slide <?php echo $index + 1; ?>"></button>
              <?php endforeach; ?>
            </div>

            <!-- Carousel Items -->
            <div class="carousel-inner">
              <?php foreach ($carousel_slides as $index => $slide): ?>
              <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                <img src="<?php echo htmlspecialchars($slide['image_path'] ?? 'uploads/contents/pigs_' . ($index + 1) . '.png'); ?>" class="d-block w-100" alt="<?php echo htmlspecialchars($slide['title'] ?? 'ASF Information'); ?>" style="height: 500px; object-fit: cover;">
                <div class="carousel-caption">
                  <?php if (!empty($slide['badge_text'])): ?>
                  <span class="carousel-badge"><?php echo htmlspecialchars($slide['badge_text']); ?></span>
                  <?php endif; ?>
                  <?php if (!empty($slide['title'])): ?>
                  <h3><?php echo htmlspecialchars($slide['title']); ?></h3>
                  <?php endif; ?>
                  <?php if (!empty($slide['description'])): ?>
                  <p><?php echo htmlspecialchars($slide['description']); ?></p>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>

            <!-- Carousel Controls -->
            <button class="carousel-control-prev" type="button" data-bs-target="#asfCarousel" data-bs-slide="prev">
              <span class="carousel-control-prev-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#asfCarousel" data-bs-slide="next">
              <span class="carousel-control-next-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Next</span>
            </button>
          </div>
          <?php endif; ?>

          <!-- Recent Alerts Section -->
          <div class="row mb-4" id="alertsSection" style="display: none;">
            <div class="col-12">
              <h5 class="mb-3"><i class="bi bi-bell-fill me-2"></i>Recent Alerts</h5>
              <div id="alertsContainer">
                <!-- Alerts will be loaded dynamically -->
              </div>
            </div>
          </div>

          <!-- GIS Map Section -->
          <div class="row mb-4" id="gis-map-section">
            <div class="col-12">
              <div class="card map-container">
                <div class="map-controls">
                  <h5 class="mb-0 me-auto"><i class="bi bi-geo-alt-fill me-2"></i>ASF Zoning Status - CALABARZON</h5>
                  
                  <!-- Date Range Filter -->
                  <div class="date-filter-group">
                    <input type="date" id="mapDateFrom" class="form-control form-control-sm" style="width: 150px; display: inline-block;">
                    <span class="mx-2">to</span>
                    <input type="date" id="mapDateTo" class="form-control form-control-sm" style="width: 150px; display: inline-block;">
                    <button class="btn btn-sm btn-primary ms-2" onclick="applyDateFilter()">
                      <i class="bi bi-funnel"></i> Filter
                    </button>
                    <button class="btn btn-sm btn-secondary ms-1" onclick="clearDateFilter()">
                      <i class="bi bi-x-circle"></i> Clear
                    </button>
                  </div>
                  
                  <!-- Zone Layers -->
                  <div class="zone-legend">
                    <strong>Zones:</strong>
                    <div class="layer-toggle active" onclick="toggleZoneLayer('infected', event)">
                      <input type="checkbox" checked id="zoneInfected">
                      <label for="zoneInfected" style="color: #dc3545;"><i class="bi bi-square-fill me-1"></i>Infected</label>
                    </div>
                    <div class="layer-toggle active" onclick="toggleZoneLayer('buffer', event)">
                      <input type="checkbox" checked id="zoneBuffer">
                      <label for="zoneBuffer" style="color: #ff69b4;"><i class="bi bi-square-fill me-1"></i>Buffer</label>
                    </div>
                    <div class="layer-toggle active" onclick="toggleZoneLayer('surveillance', event)">
                      <input type="checkbox" checked id="zoneSurveillance">
                      <label for="zoneSurveillance" style="color: #ffc107;"><i class="bi bi-square-fill me-1"></i>Surveillance</label>
                    </div>
                    <div class="layer-toggle active" onclick="toggleZoneLayer('protected', event)">
                      <input type="checkbox" checked id="zoneProtected">
                      <label for="zoneProtected" style="color: #f5f5dc;"><i class="bi bi-square-fill me-1"></i>Protected</label>
                    </div>
                    <div class="layer-toggle active" onclick="toggleZoneLayer('free', event)">
                      <input type="checkbox" checked id="zoneFree">
                      <label for="zoneFree" style="color: #228b22;"><i class="bi bi-square-fill me-1"></i>Free</label>
                    </div>
                  </div>
                  
                </div>
                <div id="asfMap"></div>
              </div>
            </div>
          </div>

          <!-- GIS Map Simulation Section -->
          <div class="row mb-4" id="gis-simulation-section">
            <div class="col-12">
              <div class="card map-container">
                <div class="map-controls">
                  <h5 class="mb-0 me-auto"><i class="bi bi-play-circle-fill me-2"></i>GIS Map Simulation</h5>
                  
                  <!-- Simulation Controls -->
                  <div class="simulation-controls d-flex flex-wrap align-items-center gap-3">
                    <div class="control-group">
                      <label for="simStartLocation" class="form-label mb-1 small">Starting Location:</label>
                      <select id="simStartLocation" class="form-select form-select-sm" style="width: 250px;">
                        <option value="">Select Barangay...</option>
                      </select>
                    </div>
                    <div class="control-group">
                      <label for="simMonthRange" class="form-label mb-1 small">Duration (months):</label>
                      <input type="number" id="simMonthRange" class="form-control form-control-sm" min="1" max="24" value="6" style="width: 100px;">
                    </div>
                    <div class="control-group">
                      <button class="btn btn-sm btn-success" onclick="startSimulation()">
                        <i class="bi bi-play-fill me-1"></i>Start Simulation
                      </button>
                      <button class="btn btn-sm btn-secondary" onclick="stopSimulation()" id="simStopBtn" disabled>
                        <i class="bi bi-stop-fill me-1"></i>Stop
                      </button>
                      <button class="btn btn-sm btn-warning" onclick="resetSimulation()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                      </button>
                    </div>
                    <div class="control-group ms-auto">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="simAutoPlay" checked>
                        <label class="form-check-label small" for="simAutoPlay">Auto-play</label>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Simulation Status -->
                  <div class="simulation-status mt-2" id="simStatus" style="display: none;">
                    <small class="text-muted">
                      <span id="simCurrentMonth">Month 0</span> / <span id="simTotalMonths">0</span> months
                      <span class="ms-3">Affected Areas: <strong id="simAffectedCount">0</strong></span>
                    </small>
                  </div>
                  
                </div>
                <div id="simulationMap" style="height: 600px;"></div>
              </div>
            </div>
          </div>

          <!-- System Features Section -->
          <?php if (!empty($feature_cards)): ?>
          <div class="row mb-4" id="features-section">
            <div class="col-12">
              <div class="text-center mb-4">
                <h3>System Features</h3>
                <p>Comprehensive tools for ASF surveillance and outbreak management</p>
              </div>
            </div>

            <?php foreach ($feature_cards as $feature): ?>
            <div class="col-lg-4 col-md-6 mb-4">
              <div class="feature-card">
                <?php if (!empty($feature['icon_class'])): ?>
                <div class="feature-icon-wrapper">
                  <i class="<?php echo htmlspecialchars($feature['icon_class']); ?>"></i>
                </div>
                <?php endif; ?>
                <div class="feature-content">
                  <?php if (!empty($feature['title'])): ?>
                  <h5><?php echo htmlspecialchars($feature['title']); ?></h5>
                  <?php endif; ?>
                  <?php if (!empty($feature['description'])): ?>
                  <p><?php echo htmlspecialchars($feature['description']); ?></p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- News & Announcements Section -->
          <div class="row mb-4" id="news-section">
            <div class="col-12">
              <div class="text-center mb-4">
                <h3>News & Announcements</h3>
                <p>Stay updated with the latest news, announcements, and updates about ASF surveillance in CALABARZON</p>
              </div>
            </div>

            <?php if (empty($news_articles)): ?>
              <div class="col-12">
                <div class="card">
                  <div class="card-body text-center py-5">
                    <i class="bi bi-newspaper" style="font-size: 3rem; color: #ddd;"></i>
                    <p class="text-muted mt-3">No news articles available at this time.</p>
                    <small class="text-muted">Check back soon for updates and announcements.</small>
                  </div>
                </div>
              </div>
            <?php else: ?>
              <?php foreach ($news_articles as $article): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                  <div class="news-card">
                    <?php if (!empty($article['featured_image'])): ?>
                      <img src="<?php echo htmlspecialchars($article['featured_image']); ?>" 
                           alt="<?php echo htmlspecialchars($article['title']); ?>" 
                           class="news-image"
                           onerror="this.src='uploads/contents/pigs_1.png';">
                    <?php else: ?>
                      <img src="uploads/contents/pigs_1.png" 
                           alt="<?php echo htmlspecialchars($article['title']); ?>" 
                           class="news-image">
                    <?php endif; ?>
                    
                    <div>
                      <span class="news-category <?php echo htmlspecialchars($article['category']); ?>">
                        <?php echo ucfirst(htmlspecialchars($article['category'])); ?>
                      </span>
                      
                      <h5 class="news-title">
                        <a href="news.php?slug=<?php echo htmlspecialchars($article['slug']); ?>" 
                           style="text-decoration: none; color: inherit;">
                          <?php echo htmlspecialchars($article['title']); ?>
                        </a>
                      </h5>
                      
                      <?php if (!empty($article['excerpt'])): ?>
                        <p class="news-excerpt"><?php echo htmlspecialchars($article['excerpt']); ?></p>
                      <?php elseif (!empty($article['content'])): ?>
                        <p class="news-excerpt">
                          <?php 
                            $excerpt = strip_tags($article['content']);
                            echo htmlspecialchars(mb_substr($excerpt, 0, 150) . (mb_strlen($excerpt) > 150 ? '...' : ''));
                          ?>
                        </p>
                      <?php endif; ?>
                      
                      <div class="news-meta">
                        <?php if (!empty($article['published_at'])): ?>
                          <span>
                            <i class="bi bi-calendar3"></i>
                            <?php echo date('M d, Y', strtotime($article['published_at'])); ?>
                          </span>
                        <?php else: ?>
                          <span>
                            <i class="bi bi-calendar3"></i>
                            <?php echo date('M d, Y', strtotime($article['created_at'])); ?>
                          </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($article['author_name'])): ?>
                          <span>
                            <i class="bi bi-person"></i>
                            <?php echo htmlspecialchars($article['author_name']); ?>
                          </span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
              
              <div class="col-12 text-center mt-3">
                <a href="news.php" class="btn btn-primary">
                  <i class="bi bi-arrow-right me-2"></i>View All News & Announcements
                </a>
              </div>
            <?php endif; ?>
          </div>
          <!-- End News & Announcements Section -->

          <!-- About System Section -->
          <?php if ($about_section): ?>
          <div class="row mb-4" id="about-section">
            <div class="col-12">
              <div class="card" style="border-radius: 25px; border: none; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-left: 5px solid #0d6efd;">
                <div class="card-body text-center p-4">
                  <?php if (!empty($about_section['title'])): ?>
                  <h5 class="card-title mb-3" style="color: #2c3e50; font-weight: 700; font-size: 1.8rem;"><?php echo htmlspecialchars($about_section['title']); ?></h5>
                  <?php endif; ?>
                  <?php if (!empty($about_section['description'])): ?>
                  <p class="card-text" style="color: #6c757d; font-size: 1.1rem; line-height: 1.8; max-width: 800px; margin: 0 auto;">
                    <?php echo htmlspecialchars($about_section['description']); ?>
                  </p>
                  <?php endif; ?>
                  <div class="mt-4">
                    <p class="mb-2" style="color: #495057; font-size: 0.95rem;">
                      <strong>Official Resources:</strong>
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                      <a href="https://www.bai.gov.ph/Report/African" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-link-45deg me-1"></i>BAI ASF Reports
                      </a>
                      <a href="https://www.bai.gov.ph/" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-building me-1"></i>BAI Official Website
                      </a>
                      <a href="https://www.woah.org/en/disease/african-swine-fever/" target="_blank" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-globe me-1"></i>WOAH Guidelines
                      </a>
                      <a href="https://www.fao.org/animal-health/animal-diseases/african-swine-fever/en" target="_blank" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-file-earmark-text me-1"></i>FAO Resources
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </section>

  </main><!-- End #main -->

  <!-- Include Footer Section -->
  <?php include 'includes/footer.php'; ?>

  <!-- Leaflet JS for GIS Mapping -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <!-- Dashboard JavaScript -->
  <script>
    let asfMap;
    let mapLayers = {
      cartogram: null  // Cartogram layer for risk zones
    };
    let zonePolygons = {}; // Store polygons by zone type for filtering
    let currentGeoLayer = null; // Active GeoJSON layer on the map
    let calabarzonGeoJSON = null; // Store GeoJSON data

    document.addEventListener('DOMContentLoaded', function() {
      // Initialize dashboard
      initializeMap();
      loadAlerts();
      
      // Sidebar navigation
      initializeSidebarNavigation();
      
      // Back to top functionality
      initializeBackToTop();
    });

    /**
     * Initialize GIS Map for CALABARZON Region
     */
    function initializeMap() {
      // CALABARZON center coordinates (approximately Batangas City area)
      const calabarzonCenter = [14.0, 121.0];
      
      // CALABARZON region boundaries (restrict map to CALABARZON only)
      // Northern: 14.6°N, Southern: 13.3°N, Eastern: 122.0°E, Western: 120.3°E
      const calabarzonBounds = [
        [13.3, 120.3], // Southwest corner
        [14.6, 122.0]  // Northeast corner
      ];
      
      // Initialize map with bounds restriction
      asfMap = L.map('asfMap', {
        maxBounds: calabarzonBounds,
        maxBoundsViscosity: 1.0, // Prevent panning outside bounds
        minZoom: 8,
        maxZoom: 18
      }).setView(calabarzonCenter, 9);
      
      // Add ESRI World Imagery satellite tiles
      L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
        maxZoom: 18
      }).addTo(asfMap);
      
      // Set map bounds to CALABARZON region
      asfMap.setMaxBounds(calabarzonBounds);
      
      // Create a dedicated pane for the mask below the vector overlay pane (z:400)
      asfMap.createPane('asfMaskPane');
      asfMap.getPane('asfMaskPane').style.zIndex = 350;
      asfMap.getPane('asfMaskPane').style.pointerEvents = 'none';

      // Initialize cartogram layer for animated circles
      mapLayers.cartogram = L.layerGroup().addTo(asfMap);
      
      // Set default date range (last 90 days)
      const today = new Date();
      const ninetyDaysAgo = new Date(today);
      ninetyDaysAgo.setDate(today.getDate() - 365);
      document.getElementById('mapDateFrom').value = ninetyDaysAgo.toISOString().split('T')[0];
      document.getElementById('mapDateTo').value = today.toISOString().split('T')[0];
      
      // Load GeoJSON boundaries first, then load the map data from API
      fetch('assets/data/calabarzon-municipalities.geojson')
        .then(response => response.json())
        .then(data => {
          calabarzonGeoJSON = data;
          addCalabarzonMask(data);
          loadMapData();
        })
        .catch(err => {
          console.error('Error loading GeoJSON boundaries:', err);
          loadMapData(); // Fallback
        });
    }

    /**
     * Add a dark mask over everything outside CALABARZON using an inverted polygon.
     * World bounds → CALABARZON municipality polygons as holes.
     */
    function isCalabarzonFeature(feature) {
      if (!feature.geometry || !feature.geometry.coordinates) return false;
      const geoName = (feature.properties.shapeName || feature.properties.ADM3_EN || feature.properties.name || '').trim().toUpperCase();
      let coords = feature.geometry.coordinates;
      while (coords.length > 0 && Array.isArray(coords[0])) coords = coords[0];
      if (coords.length < 2) return true;
      const lng = coords[0], lat = coords[1];
      if (lat < 13.1 || lat > 15.2 || lng < 120.3 || lng > 123.0) return false;
      if (geoName === 'SAN JOSE' && (lat > 14.5 || lat < 13.5 || lng < 120.8)) return false;
      if (geoName === 'SAN ANTONIO' && (lat > 14.5 || lng < 121.0)) return false;
      if (geoName === 'ROSARIO' && (lat > 14.6 || lat < 13.6)) return false;
      if (geoName === 'SAN NICOLAS' && lat > 14.5) return false;
      if (geoName === 'SAN PASCUAL' && (lat > 14.0 || lat < 13.5)) return false;
      if (geoName === 'PLARIDEL' && lng < 121.5) return false;
      if (geoName === 'MABINI' && (lat > 14.0 || lat < 13.5)) return false;
      if (geoName === 'RIZAL' && (lat > 14.5 || lat < 13.8 || lng < 121.0)) return false;
      if (geoName === 'QUEZON CITY' || geoName === 'MANILA' || geoName === 'CITY OF MANILA') return false;
      if (geoName === 'BUENAVISTA' && lat < 13.6) return false;
      if (geoName === 'SANTA CRUZ' && lat < 13.8) return false;
      if (geoName === 'MORONG' && lng < 120.6) return false;
      if (geoName === 'SAN LUIS' && lat > 14.5) return false;
      if (geoName === 'SANTO TOMAS' && lat > 14.5) return false;
      if (geoName === 'SANTA MARIA' && lat > 14.5) return false;
      if (geoName === 'VICTORIA' && lat > 14.5) return false;
      return true;
    }

    function addCalabarzonMask(geoJsonData) {
      const worldBounds = [[-90, -180], [-90, 180], [90, 180], [90, -180], [-90, -180]];
      const rings = [worldBounds];

      geoJsonData.features.forEach(function(feature) {
        if (!isCalabarzonFeature(feature)) return;
        const geomType = feature.geometry.type;
        if (geomType === 'Polygon') {
          rings.push(feature.geometry.coordinates[0].map(function(c) { return [c[1], c[0]]; }));
        } else if (geomType === 'MultiPolygon') {
          feature.geometry.coordinates.forEach(function(poly) {
            rings.push(poly[0].map(function(c) { return [c[1], c[0]]; }));
          });
        }
      });

      L.polygon(rings, {
        pane: 'asfMaskPane',
        color: 'none',
        weight: 0,
        fillColor: '#0d1b2a',
        fillOpacity: 1,
        interactive: false
      }).addTo(asfMap);
    }

    /**
     * Load map markers and data from API
     */
    function loadMapData() {
      // Load map data for animated circles (date filter handled in loadMapDataForCartogram)
      loadMapDataForCartogram();
    }
    
    /**
     * Apply date filter to map
     */
    function applyDateFilter() {
      loadMapData();
    }
    
    /**
     * Clear date filter
     */
    function clearDateFilter() {
      const today = new Date();
      const ninetyDaysAgo = new Date(today);
      ninetyDaysAgo.setDate(today.getDate() - 365);
      document.getElementById('mapDateFrom').value = ninetyDaysAgo.toISOString().split('T')[0];
      document.getElementById('mapDateTo').value = today.toISOString().split('T')[0];
      loadMapData();
    }
    
    /**
     * Toggle cartogram layer visibility for specific zone type
     */
    function toggleZoneLayer(zoneType, event) {
      const checkboxId = `zone${zoneType.charAt(0).toUpperCase() + zoneType.slice(1)}`;
      const checkbox = document.getElementById(checkboxId);
      
      if (checkbox) {
        checkbox.checked = !checkbox.checked;
        
        // Update toggle visual state - find the parent layer-toggle element
        if (event && event.currentTarget) {
          const toggle = event.currentTarget;
          if (checkbox.checked) {
            toggle.classList.add('active');
          } else {
            toggle.classList.remove('active');
          }
        } else {
          // Fallback: find the toggle element manually
          const toggle = checkbox.closest('.layer-toggle');
          if (toggle) {
            if (checkbox.checked) {
              toggle.classList.add('active');
            } else {
              toggle.classList.remove('active');
            }
          }
        }
        
        // Apply filter to show/hide zones
        applyZoneFilter();
      }
    }
    
    /**
     * Load map data for animated circles cartogram (now Polygon based)
     */
    function loadMapDataForCartogram() {
      const dateFrom = document.getElementById('mapDateFrom').value;
      const dateTo = document.getElementById('mapDateTo').value;
      
      let url = 'api/get_map_data.php';
      const params = new URLSearchParams();
      if (dateFrom) params.append('date_from', dateFrom);
      if (dateTo) params.append('date_to', dateTo);
      if (params.toString()) url += '?' + params.toString();
      
      fetch(url)
        .then(response => response.json())
        .then(data => {

            console.log("FULL API RESPONSE:", data);

            if (data.success && data.data && data.data.cities) {

                console.log("MAP CITIES DATA:", data.data.cities);

                // Print every prediction/historical entry
                data.data.cities.forEach(city => {

                    console.log(
                        "CITY:",
                        city.city,
                        "| BARANGAY:",
                        city.barangay,
                        "| ZONE:",
                        city.zone_type,
                        "| DATE:",
                        city.outbreak_date
                    );

                });

                updateCartogramWithPolygons(data.data);

            } else {

                console.warn('No city data found');

            }
        })
        .catch(error => {
          console.error('Error loading map data:', error);
        });
    }
     /**
     * Update cartogram map using GeoJSON polygons instead of circles
     */
    function updateCartogramWithPolygons(data) {
      // Clear existing layer
      mapLayers.cartogram.clearLayers();
      if (currentGeoLayer) { currentGeoLayer = null; }
      zonePolygons = {};
      
      // Zone colors matching ASF zoning standards
      const zoneColors = {
        infected: '#dc3545',      // Red
        buffer: '#ff69b4',        // Pink (Hot Pink)
        surveillance: '#ffc107',   // Yellow
        protected: '#f5f5dc',     // Light Cream (Beige)
        free: '#228b22'            // Green
      };
      
      if (calabarzonGeoJSON && data.cities && Array.isArray(data.cities)) {
        // Allowed provinces in CALABARZON
        const calabarzonProvinces = ['CAVITE', 'LAGUNA', 'BATANGAS', 'RIZAL', 'QUEZON'];
          
        // Create lookup dictionary for API data by city name
        const outbreakLookup = {};
        data.cities.forEach(outbreak => {
            const prov = (outbreak.province || '').trim().toUpperCase();
            
            // Exclude data if it is explicitly outside Cavite, Laguna, Batangas, Rizal, Quezon
            if (prov && prov !== 'CALABARZON' && !calabarzonProvinces.some(p => prov.includes(p))) {
                return; // Skip data from outside CALABARZON
            }
            
            const matchName = (outbreak.location_name || outbreak.barangay || outbreak.city || '').replace('CITY OF ', '').replace(' CITY', '').trim().toUpperCase();
            if (matchName) outbreakLookup[matchName] = outbreak;
            
            // Allow exact match fallback
            if (outbreak.city) outbreakLookup[outbreak.city.trim().toUpperCase()] = outbreak;
        });
        
        // Build and add GeoJSON layer directly to the cartogram group
        const geoLayer = L.geoJSON(calabarzonGeoJSON, {
          filter: function(feature) {
              const geoName = (feature.properties.shapeName || feature.properties.ADM3_EN || feature.properties.name || '').trim().toUpperCase();
              
              if (!feature.geometry || !feature.geometry.coordinates) return false;
              
              // Extract approximate geographical coordinate to verify CALABARZON boundaries
              let coords = feature.geometry.coordinates;
              while (coords.length > 0 && Array.isArray(coords[0])) {
                  coords = coords[0];
              }
              if (coords.length < 2) return true;
              
              const lng = coords[0];
              const lat = coords[1];
              
              // 1. Generic CALABARZON strict bounding box (Lat 13.1 to 15.2, Lng 120.3 to 123.0)
              if (lat < 13.1 || lat > 15.2 || lng < 120.3 || lng > 123.0) return false;
              
              // 2. Explicitly exclude identically-named municipalities located in other provinces that slip into the rough bounding box
              if (geoName === 'SAN JOSE' && (lat > 14.5 || lat < 13.5 || lng < 120.8)) return false; // Batangas only
              if (geoName === 'SAN ANTONIO' && (lat > 14.5 || lng < 121.0)) return false; // Quezon only
              if (geoName === 'ROSARIO' && (lat > 14.6 || lat < 13.6)) return false; // Batangas & Cavite only
              if (geoName === 'SAN NICOLAS' && lat > 14.5) return false; // Batangas only
              if (geoName === 'SAN PASCUAL' && (lat > 14.0 || lat < 13.5)) return false; // Batangas only
              if (geoName === 'PLARIDEL' && lng < 121.5) return false; // Quezon only (drops Bulacan Plaridel)
              if (geoName === 'MABINI' && (lat > 14.0 || lat < 13.5)) return false; // Batangas only
              if (geoName === 'RIZAL' && (lat > 14.5 || lat < 13.8 || lng < 121.0)) return false; // Laguna only

              // 3. Drop specific anomalies spotted outside of bounds (NCR, Marinduque, Bataan)
              if (geoName === 'QUEZON CITY') return false; // Dropping NCR
              if (geoName === 'MANILA' || geoName === 'CITY OF MANILA') return false; // Dropping NCR
              if (geoName === 'BUENAVISTA' && lat < 13.6) return false; // Dropping Marinduque Buenavista
              if (geoName === 'SANTA CRUZ' && lat < 13.8) return false; // Dropping Marinduque Santa Cruz
              if (geoName === 'MORONG' && lng < 120.6) return false; // Dropping Bataan Morong

              // 4. Drop identically-named municipalities in Pampanga, Bulacan, and Tarlac
              if (geoName === 'SAN LUIS' && lat > 14.5) return false; // Pampanga San Luis
              if (geoName === 'SANTO TOMAS' && lat > 14.5) return false; // Pampanga Santo Tomas
              if (geoName === 'SANTA MARIA' && lat > 14.5) return false; // Bulacan Santa Maria
              if (geoName === 'VICTORIA' && lat > 14.5) return false; // Tarlac Victoria

              return true; // Polygon is validated as exclusively inside Region IV-A
          },
          style: function(feature) {
            const geoName = (feature.properties.shapeName || feature.properties.ADM3_EN || feature.properties.name || '').trim().toUpperCase();
            const outbreakInfo = outbreakLookup[geoName];
            
            const zoneType = outbreakInfo ? (outbreakInfo.zone_type || 'free') : 'free';
            
            return {
              fillColor: zoneColors[zoneType] || zoneColors.free,
              weight: 2,
              opacity: 0.9,
              color: '#ffffff',
              fillOpacity: zoneType === 'protected' ? 0.7 : 0.65
            };
          },
          onEachFeature: function(feature, layer) {
            const geoName = (feature.properties.shapeName || feature.properties.ADM3_EN || feature.properties.name || '').trim().toUpperCase();
            const outbreakInfo = outbreakLookup[geoName];
            
            const zoneType = outbreakInfo ? (outbreakInfo.zone_type || 'free') : 'free';
            
            const zoneTypeName = zoneType.charAt(0).toUpperCase() + zoneType.slice(1) + ' Zone';
            const statusLabels = {
              'infected': 'Confirmed',
              'buffer': 'Contained',
              'surveillance': 'Suspected',
              'protected': 'Resolved',
              'free': 'False Alarm'
            };
            const statusLabel = statusLabels[zoneType] || 'Unknown';
            
            // Tag the layer with its zone type so applyZoneFilter can read it
            layer._zoneType = zoneType;
            if (!zonePolygons[zoneType]) zonePolygons[zoneType] = [];
            zonePolygons[zoneType].push(layer);
            
            // Define popup tooltips based on outbreak info
            const displayName = feature.properties.shapeName || feature.properties.ADM3_EN || feature.properties.name || geoName;
            let popupContent = `<strong>${zoneTypeName}</strong><br><strong>${displayName}</strong><br>`;
            
            if (outbreakInfo) {
              popupContent += `Status: ${statusLabel}<br>
              ${outbreakInfo.total_outbreaks > 1 ? `Total Outbreaks: ${outbreakInfo.total_outbreaks}<br>` : `Outbreak Code: ${outbreakInfo.outbreak_code || 'N/A'}<br>`}
              Date: ${outbreakInfo.outbreak_date || 'N/A'}<br>
              ${outbreakInfo.total_pigs_affected > 0 ? `Affected: ${outbreakInfo.total_pigs_affected}<br>` : ''}
              ${outbreakInfo.severity_level ? `Severity: ${outbreakInfo.severity_level}<br>` : ''}`;
            } else {
              popupContent += `Status: No active outbreaks recorded (Free/Clean)`;
            }
            
            layer.bindPopup(popupContent);

            // Hover styling interaction for polygons
            layer.on({
                mouseover: function(e) {
                    const l = e.target;
                    l.setStyle({
                        weight: 4,
                        color: '#666',
                        fillOpacity: 0.9
                    });
                    if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                        l.bringToFront();
                    }
                },
                mouseout: function(e) {
                    if (currentGeoLayer) currentGeoLayer.resetStyle(e.target);
                    applyZoneFilter();
                }
            });
          }
        });
        
        // Add the layer to the map and store globally
        currentGeoLayer = geoLayer;
        geoLayer.addTo(mapLayers.cartogram);

        // Apply zone visibility filter
        applyZoneFilter();

      } else {
        console.warn('GeoJSON boundaries or cities data is not available.', data);
      }
    }

    /**
     * Apply zone filter — show/hide zone types using setStyle (opacity toggle).
     * Using setStyle avoids Leaflet's restriction on adding a layer to multiple groups.
     */
    function applyZoneFilter() {
      if (!currentGeoLayer) return;

      const zoneTypes = ['infected', 'buffer', 'surveillance', 'protected', 'free'];
      const activeZones = new Set();
      zoneTypes.forEach(zoneType => {
        const checkboxId = `zone${zoneType.charAt(0).toUpperCase() + zoneType.slice(1)}`;
        const checkbox = document.getElementById(checkboxId);
        if (checkbox && checkbox.checked) activeZones.add(zoneType);
      });

      currentGeoLayer.eachLayer(function(layer) {
        const zt = layer._zoneType || 'free';
        if (activeZones.has(zt)) {
          layer.setStyle({ fillOpacity: zt === 'protected' ? 0.7 : 0.65, opacity: 0.9 });
        } else {
          layer.setStyle({ fillOpacity: 0, opacity: 0 });
        }
      });
    }

    /**
     * GIS Map Simulation Variables and Functions
     */
    let simulationMap = null;
    let simulationLayers = {
      base: null,
      simulation: L.layerGroup()
    };
    let simulationData = {
      locations: [],
      spreadStats: null,
      currentSimulation: null,
      animationFrame: null,
      isRunning: false
    };
    
    function addSimulationMask(map, geoJsonData) {
      const worldBounds = [[-90, -180], [-90, 180], [90, 180], [90, -180], [-90, -180]];
      const rings = [worldBounds];
      geoJsonData.features.forEach(function(feature) {
        if (!isCalabarzonFeature(feature)) return;
        const geomType = feature.geometry.type;
        if (geomType === 'Polygon') {
          rings.push(feature.geometry.coordinates[0].map(function(c) { return [c[1], c[0]]; }));
        } else if (geomType === 'MultiPolygon') {
          feature.geometry.coordinates.forEach(function(poly) {
            rings.push(poly[0].map(function(c) { return [c[1], c[0]]; }));
          });
        }
      });
      L.polygon(rings, {
        pane: 'simMaskPane',
        color: 'none',
        weight: 0,
        fillColor: '#0d1b2a',
        fillOpacity: 1,
        interactive: false
      }).addTo(map);
    }

    /**
     * Initialize Simulation Map
     */
    function initSimulationMap() {
      if (simulationMap) return;
      
      // CALABARZON region boundaries (restrict map to CALABARZON only)
      // Northern: 14.6°N, Southern: 13.3°N, Eastern: 122.0°E, Western: 120.3°E
      const calabarzonBounds = [
        [13.3, 120.3], // Southwest corner
        [14.6, 122.0]  // Northeast corner
      ];
      
      simulationMap = L.map('simulationMap', {
        maxBounds: calabarzonBounds,
        maxBoundsViscosity: 1.0, // Prevent panning outside bounds
        minZoom: 8,
        maxZoom: 18
      }).setView([14.0, 121.0], 9);
      
      L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
        maxZoom: 18
      }).addTo(simulationMap);

      // Set map bounds to CALABARZON region
      simulationMap.setMaxBounds(calabarzonBounds);

      // Create a dedicated pane for the simulation mask
      simulationMap.createPane('simMaskPane');
      simulationMap.getPane('simMaskPane').style.zIndex = 350;
      simulationMap.getPane('simMaskPane').style.pointerEvents = 'none';

      simulationLayers.simulation.addTo(simulationMap);

      // Add CALABARZON-only mask (hide everything outside region)
      if (calabarzonGeoJSON) {
        addSimulationMask(simulationMap, calabarzonGeoJSON);
      } else {
        fetch('assets/data/calabarzon-municipalities.geojson')
          .then(r => r.json())
          .then(data => addSimulationMask(simulationMap, data));
      }

      // Load simulation locations
      loadSimulationLocations();
      loadSimulationSpreadData();
    }
    
    /**
     * Load locations for simulation dropdown
     */
    function loadSimulationLocations() {
      fetch('api/get_simulation_locations.php')
        .then(response => response.json())
        .then(data => {
          if (data.success && data.data) {
            simulationData.locations = data.data;
            const select = document.getElementById('simStartLocation');
            select.innerHTML = '<option value="">Select Barangay...</option>';
            
            data.data.forEach(location => {
              const option = document.createElement('option');
              option.value = JSON.stringify({
                barangay: location.barangay,
                city: location.city,
                province: location.province,
                latitude: location.latitude,
                longitude: location.longitude
              });
              option.textContent = location.display_name;
              select.appendChild(option);
            });
          }
        })
        .catch(error => {
          console.error('Error loading simulation locations:', error);
        });
    }
    
    /**
     * Load spread statistics for simulation
     */
    function loadSimulationSpreadData() {
      fetch('api/get_simulation_spread_data.php')
        .then(response => response.json())
        .then(data => {
          if (data.success && data.data) {
            simulationData.spreadStats = data.data;
          }
        })
        .catch(error => {
          console.error('Error loading simulation spread data:', error);
        });
    }
    
    /**
     * Start simulation
     */
    function startSimulation() {
      const startLocationValue = document.getElementById('simStartLocation').value;
      const monthRange = parseInt(document.getElementById('simMonthRange').value) || 6;
      
      if (!startLocationValue) {
        alert('Please select a starting location');
        return;
      }
      
      if (!simulationData.spreadStats) {
        alert('Spread data not loaded yet. Please wait...');
        return;
      }
      
      const startLocation = JSON.parse(startLocationValue);
      
      // Clear previous simulation
      simulationLayers.simulation.clearLayers();
      
      // Calculate simulation data
      const simulation = calculateSimulationSpread(startLocation, monthRange);
      simulationData.currentSimulation = simulation;
      
      // Show status
      document.getElementById('simStatus').style.display = 'block';
      document.getElementById('simTotalMonths').textContent = monthRange;
      document.getElementById('simStopBtn').disabled = false;
      
      // Start animation
      if (document.getElementById('simAutoPlay').checked) {
        runSimulationAnimation();
      } else {
        displaySimulationFrame(0);
      }
    }
    
    /**
     * Calculate simulation spread based on real data
     */
    function calculateSimulationSpread(startLocation, months) {
      const stats = simulationData.spreadStats;
      const avgDailySpread = stats.avg_daily_spread || 30; // Default 30 days
      const avgDistance = stats.avg_distance_between_outbreaks || 10; // Default 10 km
      
      const simulationFrames = [];
      const affectedAreas = new Set();
      const startKey = `${startLocation.barangay}|${startLocation.city}`;
      affectedAreas.add(startKey);
      
      // Initialize frame 0 with starting location
      simulationFrames.push({
        month: 0,
        areas: [{
          ...startLocation,
          month: 0,
          intensity: 1.0,
          distanceFromStart: 0
        }]
      });
      
      // Generate spread for each month
      for (let month = 1; month <= months; month++) {
        const previousAreas = simulationFrames[month - 1].areas;
        const newAreas = [];
        const currentAreas = [...previousAreas];
        
        // Spread to nearby locations
        simulationData.locations.forEach(location => {
          const locationKey = `${location.barangay}|${location.city}`;
          
          if (!affectedAreas.has(locationKey)) {
            // Check distance from any affected area and from starting location
            let minDistance = Infinity;
            for (const area of previousAreas) {
              const distance = calculateDistance(
                area.latitude, area.longitude,
                location.latitude, location.longitude
              );
              minDistance = Math.min(minDistance, distance);
            }
            
            // Calculate distance from starting location
            const distanceFromStart = calculateDistance(
              startLocation.latitude, startLocation.longitude,
              location.latitude, location.longitude
            );
            
            // Probability of spread based on distance and time
            const spreadProbability = calculateSpreadProbability(minDistance, avgDistance, month);
            const randomValue = Math.random();
            
            if (randomValue < spreadProbability) {
              affectedAreas.add(locationKey);
              newAreas.push({
                ...location,
                month: month,
                intensity: Math.max(0.3, 1.0 - (minDistance / (avgDistance * 3))),
                distanceFromStart: distanceFromStart
              });
            }
          }
        });
        
        // Add new areas and update existing areas intensity
        currentAreas.forEach(area => {
          area.intensity = Math.min(1.0, area.intensity + 0.05);
          // Ensure distanceFromStart is set for all areas
          if (!area.distanceFromStart) {
            area.distanceFromStart = calculateDistance(
              startLocation.latitude, startLocation.longitude,
              area.latitude, area.longitude
            );
          }
        });
        
        currentAreas.push(...newAreas);
        
        simulationFrames.push({
          month: month,
          areas: currentAreas
        });
      }
      
      return {
        frames: simulationFrames,
        startLocation: startLocation,
        totalMonths: months
      };
    }
    
    /**
     * Calculate distance between two coordinates (Haversine formula)
     */
    function calculateDistance(lat1, lon1, lat2, lon2) {
      const R = 6371; // Earth's radius in km
      const dLat = (lat2 - lat1) * Math.PI / 180;
      const dLon = (lon2 - lon1) * Math.PI / 180;
      const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon/2) * Math.sin(dLon/2);
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
      return R * c;
    }
    
    /**
     * Calculate spread probability based on distance
     */
    function calculateSpreadProbability(distance, avgDistance, month) {
      // Higher probability for closer areas
      const distanceFactor = Math.exp(-distance / (avgDistance * 2));
      // Higher probability as time progresses
      const timeFactor = Math.min(1.0, month / 3);
      // Combine factors
      return Math.min(0.8, distanceFactor * timeFactor * 0.3);
    }
    
    /**
     * Calculate color based on distance from start and intensity
     * Returns lighter colors for areas far from start or with low intensity
     */
    function getColorFromDistanceAndIntensity(distanceFromStart, intensity, maxDistance = 50) {
      // Normalize distance (0 to 1, where 1 is maxDistance km)
      const normalizedDistance = Math.min(1.0, distanceFromStart / maxDistance);
      
      // Combine distance and intensity factors (both 0-1)
      // Areas close with high intensity = dark red
      // Areas far with low intensity = light red/pink
      const colorFactor = (1.0 - normalizedDistance * 0.5) * intensity;
      
      // Base red color (#dc3545 = rgb(220, 53, 69))
      // Make it lighter by mixing with white based on colorFactor
      const baseR = 220;
      const baseG = 53;
      const baseB = 69;
      
      // Mix with white (255, 255, 255) based on colorFactor
      // Lower colorFactor = more white = lighter color
      const mixAmount = 1.0 - (colorFactor * 0.7); // 0 to 0.7 mix with white
      const r = Math.round(baseR + (255 - baseR) * mixAmount);
      const g = Math.round(baseG + (255 - baseG) * mixAmount);
      const b = Math.round(baseB + (255 - baseB) * mixAmount);
      
      // Convert to hex
      const hexR = r.toString(16).padStart(2, '0');
      const hexG = g.toString(16).padStart(2, '0');
      const hexB = b.toString(16).padStart(2, '0');
      
      return `#${hexR}${hexG}${hexB}`;
    }
    
    /**
     * Run simulation animation
     */
    function runSimulationAnimation() {
      if (!simulationData.currentSimulation || simulationData.isRunning) return;
      
      simulationData.isRunning = true;
      let currentFrame = 0;
      const totalFrames = simulationData.currentSimulation.frames.length;
      
      function animate() {
        if (!simulationData.isRunning || currentFrame >= totalFrames) {
          simulationData.isRunning = false;
          document.getElementById('simStopBtn').disabled = true;
          return;
        }
        
        displaySimulationFrame(currentFrame);
        currentFrame++;
        
        simulationData.animationFrame = setTimeout(animate, 1000); // 1 second per frame
      }
      
      animate();
    }
    
    /**
     * Display a single frame of the simulation
     */
    function displaySimulationFrame(frameIndex) {
      if (!simulationData.currentSimulation) return;
      
      const frame = simulationData.currentSimulation.frames[frameIndex];
      if (!frame) return;
      
      // Clear previous frame
      simulationLayers.simulation.clearLayers();
      
      // Calculate max distance for color normalization
      let maxDistance = 0;
      frame.areas.forEach(area => {
        if (area.distanceFromStart > maxDistance) {
          maxDistance = area.distanceFromStart;
        }
      });
      maxDistance = Math.max(maxDistance, 50); // Minimum 50km for normalization
      
      // Display all areas up to current frame
      for (let i = 0; i <= frameIndex; i++) {
        const currentFrame = simulationData.currentSimulation.frames[i];
        currentFrame.areas.forEach(area => {
          const radius = 800 + (area.intensity * 400); // 0.8-1.2km radius (smaller circles)
          
          // Calculate color based on distance from start and intensity
          const distanceFromStart = area.distanceFromStart || 0;
          const fillColor = getColorFromDistanceAndIntensity(distanceFromStart, area.intensity, maxDistance);
          
          // Border color - slightly darker than fill
          const borderColor = getColorFromDistanceAndIntensity(distanceFromStart, Math.min(1.0, area.intensity * 1.2), maxDistance);
          
          // Opacity based on intensity and distance (lighter for far/low intensity)
          const baseOpacity = 0.3 + (area.intensity * 0.5);
          const distanceOpacityFactor = 1.0 - (distanceFromStart / maxDistance) * 0.3;
          const opacity = baseOpacity * distanceOpacityFactor;
          
          const circle = L.circle([area.latitude, area.longitude], {
            radius: radius,
            fillColor: fillColor,
            color: borderColor,
            weight: 2,
            opacity: opacity,
            fillOpacity: opacity
          });
          
          circle.bindPopup(`
            <strong>${area.barangay}</strong><br>
            ${area.city}, ${area.province}<br>
            Month: ${area.month}<br>
            Intensity: ${(area.intensity * 100).toFixed(0)}%<br>
            Distance from start: ${distanceFromStart.toFixed(1)} km
          `);
          
          circle.addTo(simulationLayers.simulation);
        });
      }
      
      // Update status
      document.getElementById('simCurrentMonth').textContent = `Month ${frame.month}`;
      document.getElementById('simAffectedCount').textContent = frame.areas.length;
      
      // Center map on starting location if first frame
      if (frameIndex === 0 && simulationData.currentSimulation.startLocation) {
        simulationMap.setView(
          [simulationData.currentSimulation.startLocation.latitude, 
           simulationData.currentSimulation.startLocation.longitude], 
          11
        );
      }
    }
    
    /**
     * Stop simulation
     */
    function stopSimulation() {
      simulationData.isRunning = false;
      if (simulationData.animationFrame) {
        clearTimeout(simulationData.animationFrame);
        simulationData.animationFrame = null;
      }
      document.getElementById('simStopBtn').disabled = true;
    }
    
    /**
     * Reset simulation
     */
    function resetSimulation() {
      stopSimulation();
      simulationLayers.simulation.clearLayers();
      simulationData.currentSimulation = null;
      document.getElementById('simStatus').style.display = 'none';
      document.getElementById('simStartLocation').value = '';
      document.getElementById('simMonthRange').value = 6;
    }
    
    /**
     * Initialize simulation map on page load
     */
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize simulation map after a short delay to ensure DOM is ready
      setTimeout(initSimulationMap, 500);
    });
    
    /**
     * Load recent alerts
     */
    function loadAlerts() {
      fetch('api/get_alerts.php')
        .then(response => response.json())
        .then(data => {
          if (data.success && data.alerts && data.alerts.length > 0) {
            displayAlerts(data.alerts);
            document.getElementById('alertsSection').style.display = 'block';
          }
        })
        .catch(error => {
          console.error('Error loading alerts:', error);
        });
    }

    /**
     * Display alerts
     */
    function displayAlerts(alerts) {
      const container = document.getElementById('alertsContainer');
      let html = '';
      
      alerts.slice(0, 5).forEach(alert => {
        const alertClass = alert.severity === 'high' ? 'alert-danger' : 
                          alert.severity === 'medium' ? 'alert-warning' : 'alert-info';
        html += `
          <div class="alert alert-card ${alertClass}">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>${alert.title}</strong> - ${alert.message}
            <small class="text-muted ms-2">${alert.timestamp}</small>
          </div>
        `;
      });
      
      container.innerHTML = html;
    }

    /**
     * Initialize sidebar navigation
     */
    function initializeSidebarNavigation() {
      const sidebarLinks = document.querySelectorAll('.sidebar-nav .nav-link');
      sidebarLinks.forEach(link => {
        link.addEventListener('click', function(e) {
          sidebarLinks.forEach(l => l.classList.remove('active'));
          this.classList.add('active');
        });
      });
    }

    /**
     * Initialize back to top button
     */
    function initializeBackToTop() {
      const backToTopBtn = document.querySelector('.back-to-top');
      if (backToTopBtn) {
        window.addEventListener('scroll', () => {
          if (window.scrollY > 100) {
            backToTopBtn.classList.add('active');
          } else {
            backToTopBtn.classList.remove('active');
          }
        });
        
        backToTopBtn.addEventListener('click', () => {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        });
      }
    }
  </script>

</body>

</html>
