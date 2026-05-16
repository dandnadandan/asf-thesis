<?php
// Head section for ASF Surveillance System
?>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>ASF Surveillance System - CALABARZON African Swine Fever Monitoring</title>
  <meta content="GIS-based predictive surveillance system for early detection and effective management of African Swine Fever outbreaks in CALABARZON" name="description">
  <meta content="ASF, African Swine Fever, surveillance, GIS, CALABARZON, predictive modeling, outbreak management" name="keywords">

  <!-- Favicons -->
  <link href="uploads/asf_logo.png" rel="icon">
  <link href="uploads/asf_logo.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="bootstrap/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="bootstrap/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="bootstrap/assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="bootstrap/assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="bootstrap/assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="bootstrap/assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="bootstrap/assets/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="bootstrap/assets/css/style.css" rel="stylesheet">

  <!-- Custom Login Button Styles -->
  <style>
    .login-section {
      margin-right: 20px;
    }
    
    .login-btn {
      position: relative;
      background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
      border: none;
      border-radius: 25px;
      padding: 10px 25px;
      font-weight: 600;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      overflow: hidden;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
    }
    
    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(13, 110, 253, 0.4);
      background: linear-gradient(135deg, #0b5ed7 0%, #084298 100%);
    }
    
    .login-btn:active {
      transform: translateY(0);
      box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
    }
    
    .login-btn .btn-overlay {
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s ease;
    }
    
    .login-btn:hover .btn-overlay {
      left: 100%;
    }
    
    .login-btn .btn-text {
      position: relative;
      z-index: 2;
    }
    
    .login-btn i {
      position: relative;
      z-index: 2;
      font-size: 16px;
    }
    
    /* Animation for button appearance */
    @keyframes slideInRight {
      from {
        opacity: 0;
        transform: translateX(30px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }
    
    .login-section {
      animation: slideInRight 0.6s ease-out;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .login-btn {
        padding: 8px 20px;
        font-size: 12px;
      }
      
      .login-btn i {
        font-size: 14px;
      }
    }
  </style>

  <!-- Custom Logo Animation Styles -->
  <style>
    /* Logo container animations */
    .logo {
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      position: relative;
    }
    
    .logo:hover {
      transform: scale(1.05);
    }
    
    /* Logo image animations */
    .logo img {
      transition: all 0.4s ease;
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
    }
    
    .logo:hover img {
      filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
      transform: rotate(2deg) scale(1.02);
    }
    
    /* Logo text animations */
    .logo span {
      transition: all 0.3s ease;
      position: relative;
    }
    
    .logo:hover span {
      color: #0d6efd !important;
      transform: translateX(3px);
    }
    
    /* Slogan animations */
    .logo div[style*="font-style: italic"] {
      transition: all 0.3s ease;
      position: relative;
    }
    
    .logo:hover div[style*="font-style: italic"] {
      color: #0d6efd !important;
      transform: translateX(2px);
      font-weight: 500;
    }
    
    /* Logo entrance animations */
    @keyframes logoSlideIn {
      from {
        opacity: 0;
        transform: translateX(-50px) scale(0.8);
      }
      to {
        opacity: 1;
        transform: translateX(0) scale(1);
      }
    }
    
    @keyframes logoBounce {
      0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
      }
      40% {
        transform: translateY(-10px);
      }
      60% {
        transform: translateY(-5px);
      }
    }
    
    /* Logo pulse effect on page load */
    @keyframes logoPulse {
      0% {
        transform: scale(1);
      }
      50% {
        transform: scale(1.1);
      }
      100% {
        transform: scale(1);
      }
    }
    
    .logo img {
      animation: logoPulse 2s ease-in-out 2s;
    }
    
    /* Hover glow effect */
    
    
    .logo:hover::before {
      opacity: 0.3;
      transform: scale(1.2);
    }
    
    /* Mobile logo specific animations */
    .d-lg-none {
      transition: all 0.3s ease;
    }
    
    .d-lg-none:hover {
      transform: scale(1.1) rotate(-2deg);
    }
    
    /* Desktop logo specific animations */
    .d-none.d-lg-block {
      transition: all 0.3s ease;
    }
    
    .d-none.d-lg-block:hover {
      transform: scale(1.05);
    }
    
    /* Responsive logo animations */
    @media (max-width: 768px) {
      .logo:hover {
        transform: scale(1.03);
      }
      
      .logo img {
        animation: logoPulse 1.5s ease-in-out 1.5s;
      }
    }
  </style>

  <!-- Custom Blue Color Scheme Override -->
  <style>
    :root {
      --bs-primary: #0d6efd !important;
      --bs-primary-rgb: 13, 110, 253 !important;
      --bs-link-color: #0d6efd !important;
      --bs-link-hover-color: #0b5ed7 !important;
    }
    
    /* Override any remaining red colors with blue */
    .text-danger, .text-danger:hover {
      color: #0d6efd !important;
    }
    
    .bg-danger, .bg-danger:hover {
      background-color: #0d6efd !important;
    }
    
    .border-danger {
      border-color: #0d6efd !important;
    }
    
    .btn-danger {
      background-color: #0d6efd !important;
      border-color: #0d6efd !important;
    }
    
    .btn-danger:hover {
      background-color: #0b5ed7 !important;
      border-color: #0b5ed7 !important;
    }
    
    .btn-outline-danger {
      color: #0d6efd !important;
      border-color: #0d6efd !important;
    }
    
    .btn-outline-danger:hover {
      background-color: #0d6efd !important;
      border-color: #0d6efd !important;
    }
    
    /* Ensure all primary elements use blue */
    .btn-primary {
      background-color: #0d6efd !important;
      border-color: #0d6efd !important;
    }
    
    .btn-primary:hover {
      background-color: #0b5ed7 !important;
      border-color: #0b5ed7 !important;
    }
    
    .text-primary {
      color: #0d6efd !important;
    }
    
    .bg-primary {
      background-color: #0d6efd !important;
    }
    
         .border-primary {
       border-color: #0d6efd !important;
     }
     
     /* Enhanced Back to Top Button Styles */
     .back-to-top {
       position: fixed;
       visibility: hidden;
       opacity: 0;
       right: 15px;
       bottom: 15px;
       z-index: 99999;
       background: #0d6efd !important;
       width: 40px;
       height: 40px;
       border-radius: 8px;
       transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
       box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
       border: none;
       cursor: pointer;
     }
     
     .back-to-top.active {
       visibility: visible;
       opacity: 1;
     }
     
     .back-to-top:hover {
       background: #0b5ed7 !important;
       transform: translateY(-3px) scale(1.1);
       box-shadow: 0 8px 25px rgba(13, 110, 253, 0.4);
     }
     
     .back-to-top i {
       font-size: 24px;
       color: #fff;
       line-height: 0;
       transition: all 0.3s ease;
     }
     
     .back-to-top:hover i {
       transform: translateY(-2px);
     }
     
     /* Responsive adjustments */
     @media (max-width: 768px) {
       .back-to-top {
         right: 10px;
         bottom: 10px;
         width: 35px;
         height: 35px;
       }
       
       .back-to-top i {
         font-size: 20px;
       }
     }
   </style>

  <!-- =======================================================
  * Template Name: NiceAdmin
  * Template URL: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/
  * Updated: Apr 20 2024 with Bootstrap v5.3.3
  * Author: BootstrapMade.com
  * License: https://bootstrapmade.com/license/
  ======================================================== -->
