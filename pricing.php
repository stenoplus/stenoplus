<?php
// pricing.php
session_start();

// Check if user is logged in
// if (!isset($_SESSION['user_id'])) {
//     header("Location: login.php");
//     exit();
// }

require 'backend/config.php';

// Get current promotions from database
$promo_query = "SELECT * FROM promotions WHERE active = 1 ORDER BY created_at DESC LIMIT 1";
$promo_result = $conn->query($promo_query);
$current_promo = $promo_result->fetch_assoc();

// Get all exam packages
$exam_query = "SELECT * FROM exams WHERE active = 1 ORDER BY display_order";
$exam_result = $conn->query($exam_query);
$all_exams = $exam_result->fetch_all(MYSQLI_ASSOC);

// Format currency
function formatCurrency($amount) {
    return '₹' . number_format($amount, 0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Packages - StenoPlus</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary-red: #D2171E;
            --primary-blue: #002147;
        }
        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: #F3F4F6; 
        }
        .sidebar { 
            transition: transform 0.3s ease-in-out;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-hidden { transform: translateX(-100%); }
        
        .dark body {
            background-color: #111827;
            color: #F3F4F6;
        }
        .dark .sidebar {
            background-color: #1F2937;
        }
        .dark .bg-white {
            background-color: #1E293B !important;
        }
        
        /* Brand Colors */
        .bg-brand-red { background-color: var(--primary-red); }
        .bg-brand-blue { background-color: var(--primary-blue); }
        .text-brand-red { color: var(--primary-red); }
        .text-brand-blue { color: var(--primary-blue); }
        .border-brand-red { border-color: var(--primary-red); }
        .border-brand-blue { border-color: var(--primary-blue); }
        
        .hover\:bg-brand-red:hover { background-color: var(--primary-red); }
        .hover\:bg-brand-blue:hover { background-color: var(--primary-blue); }
        
        /* Pricing Card Styles */
        .pricing-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #E5E7EB;
        }
        .pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .pricing-card.popular {
            border: 2px solid var(--primary-red);
            position: relative;
        }
        .popular-badge {
            position: absolute;
            top: 0;
            right: 20px;
            transform: translateY(-50%);
            background-color: var(--primary-red);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        /* Feature List */
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        .feature-icon {
            margin-right: 8px;
            flex-shrink: 0;
        }
        
        /* FAQ Accordion */
        .faq-item {
            border-bottom: 1px solid #E5E7EB;
            padding-bottom: 16px;
            margin-bottom: 16px;
        }
        .faq-question {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            padding: 12px 0;
        }
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        .faq-answer.active {
            max-height: 500px;
        }
        
        /* Exam Tabs */
        .exam-tab {
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid #E5E7EB;
            margin: 0 4px;
        }
        .exam-tab.active {
            background-color: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            red: '#D2171E',
                            blue: '#002147'
                        }
                    }
                }
            }
        };
    </script>
</head>
<body class="flex">

    <!-- Student Sidebar -->
    <?php include 'student-sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="md:ml-64 w-full lg:p-6 md:p-0">
        <!-- Top Bar -->
        <header class="flex justify-between items-center bg-white shadow p-4 rounded-none lg:rounded-lg dark:bg-gray-800">
            <button id="openSidebar" class="md:hidden">
                <i data-lucide="menu"></i>
            </button>

            <h2 class="text-xl font-semibold dark:text-white">Exam Preparation Packages</h2>
            
            <div class="flex items-center space-x-4">
                <i data-lucide="bell" class="cursor-pointer dark:text-white"></i>
                <i data-lucide="moon" id="darkModeToggle" class="cursor-pointer dark:text-white"></i>
                
                <!-- Profile Dropdown -->
                <?php require 'profile-dropdown.php'; ?>
            </div>
        </header>

        <!-- Pricing Content -->
        <section class="mt-6 p-6 lg:p-0">
            <div class="max-w-7xl mx-auto">
                <!-- Promotional Banner -->
                <?php if ($current_promo): ?>
                <div class="bg-gradient-to-r from-brand-red to-brand-blue rounded-lg p-6 text-white shadow-lg mb-8">
                    <div class="flex flex-col md:flex-row justify-between items-center">
                        <div class="mb-4 md:mb-0">
                            <h2 class="text-xl md:text-2xl font-bold mb-2"><?= htmlspecialchars($current_promo['title']) ?></h2>
                            <p class="opacity-90"><?= htmlspecialchars($current_promo['description']) ?></p>
                        </div>
                        <div class="bg-white text-brand-red px-4 py-2 rounded-lg font-bold">
                            Promo Code: <?= htmlspecialchars($current_promo['code']) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- All Access Pass -->
                <div class="mb-12">
                    <div class="text-center mb-8">
                        <h1 class="text-3xl md:text-4xl font-bold mb-4 dark:text-white">ALL STENO EXAMS PREPARATION</h1>
                        <p class="text-lg text-gray-600 max-w-2xl mx-auto dark:text-gray-400">
                            Master every stenography exam with structured guidance and expert mentorship!
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <!-- 3 Month Pass -->
                        <div class="pricing-card bg-white shadow-md dark:bg-gray-800">
                            <div class="p-8">
                                <h3 class="text-xl font-semibold mb-2 dark:text-white">All Exams Pass</h3>
                                <p class="text-gray-600 mb-6 dark:text-gray-400">3 Months Full Access</p>
                                
                                <div class="mb-6">
                                    <span class="text-4xl font-bold dark:text-white"><?= formatCurrency(2999) ?></span>
                                    <span class="text-gray-500 line-through ml-2 dark:text-gray-400"><?= formatCurrency(3999) ?></span>
                                    <span class="text-sm text-gray-500 ml-2 dark:text-gray-400">(25% OFF)</span>
                                </div>
                                
                                <a href="#" class="block w-full text-center bg-brand-blue hover:bg-brand-blue/90 text-white font-medium py-3 px-4 rounded-lg transition">
                                    Get 3 Month Pass
                                </a>
                            </div>
                            
                            <div class="border-t border-gray-200 px-8 py-6 dark:border-gray-700">
                                <h4 class="font-medium mb-4 dark:text-white">Features:</h4>
                                <ul>
                                    <li class="feature-item">
                                        <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                        <span class="dark:text-gray-300">Access to all exam materials</span>
                                    </li>
                                    <li class="feature-item">
                                        <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                        <span class="dark:text-gray-300">1000+ Dictations</span>
                                    </li>
                                    <li class="feature-item">
                                        <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                        <span class="dark:text-gray-300">All Volumes of KC</span>
                                    </li>
                                    <li class="feature-item">
                                        <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                        <span class="dark:text-gray-300">Basic Support</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- 6 Month Pass -->
                        <div class="pricing-card bg-white shadow-lg popular dark:bg-gray-800">
                            <div class="popular-badge">Best Value</div>
                            <div class="p-8">
                                <h3 class="text-xl font-semibold mb-2 dark:text-white">All Exams Pass</h3>
                                <p class="text-gray-600 mb-6 dark:text-gray-400">6 Months Full Access</p>
                                
                                <div class="mb-6">
                                    <span class="text-4xl font-bold dark:text-white"><?= formatCurrency(4999) ?></span>
                                    <span class="text-gray-500 line-through ml-2 dark:text-gray-400"><?= formatCurrency(7999) ?></span>
                                    <span class="text-sm text-gray-500 ml-2 dark:text-gray-400">(38% OFF)</span>
                                </div>
                                
                                <a href="#" class="block w-full text-center bg-brand-red hover:bg-brand-red/90 text-white font-medium py-3 px-4 rounded-lg transition">
                                    Get 6 Month Pass
                                </a>
                            </div>
                            
                            <div class="border-t border-gray-200 px-8 py-6 dark:border-gray-700">
                                <h4 class="font-medium mb-4 dark:text-white">All 3 Month Features, Plus:</h4>
                                <ul>
                                    <li class="feature-item">
                                        <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                        <span class="dark:text-gray-300">Priority Support</span>
                                    </li>
                                    <li class="feature-item">
                                        <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                        <span class="dark:text-gray-300">Expert Evaluation Sessions</span>
                                    </li>
                                    <li class="feature-item">
                                        <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                        <span class="dark:text-gray-300">Personalized Study Plan</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- 1 Year Pass -->
                        <div class="pricing-card bg-white shadow-md dark:bg-gray-800">
                            <div class="p-8">
                                <h3 class="text-xl font-semibold mb-2 dark:text-white">All Exams Pass</h3>
                                <p class="text-gray-600 mb-6 dark:text-gray-400">1 Year Full Access</p>
                                
                                <div class="mb-6">
                                    <span class="text-4xl font-bold dark:text-white"><?= formatCurrency(7999) ?></span>
                                    <span class="text-gray-500 line-through ml-2 dark:text-gray-400"><?= formatCurrency(14999) ?></span>
                                    <span class="text-sm text-gray-500 ml-2 dark:text-gray-400">(47% OFF)</span>
                                </div>
                                
                                <a href="#" class="block w-full text-center bg-brand-blue hover:bg-brand-blue/90 text-white font-medium py-3 px-4 rounded-lg transition">
                                    Get 1 Year Pass
                                </a>
                            </div>
                            
                            <div class="border-t border-gray-200 px-8 py-6 dark:border-gray-700">
                                <h4 class="font-medium mb-4 dark:text-white">All 6 Month Features, Plus:</h4>
                                <ul>
                                    <li class="feature-item">
                                        <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                        <span class="dark:text-gray-300">1-on-1 Coaching Sessions</span>
                                    </li>
                                    <li class="feature-item">
                                        <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                        <span class="dark:text-gray-300">Printed Study Materials</span>
                                    </li>
                                    <li class="feature-item">
                                        <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                        <span class="dark:text-gray-300">Job Notification Alerts</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mb-12">
                        <h2 class="text-2xl font-bold mb-4 dark:text-white">Covering All Major Steno Exams</h2>
                        <div class="flex flex-wrap justify-center gap-4 max-w-3xl mx-auto">
                            <?php foreach ($all_exams as $exam): ?>
                            <span class="bg-gray-100 text-gray-800 px-4 py-2 rounded-full text-sm dark:bg-gray-700 dark:text-gray-300">
                                <?= htmlspecialchars($exam['exam_name']) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Exam Specific Packages -->
                <div class="mb-12">
                    <div class="text-center mb-8">
                        <h2 class="text-3xl font-bold mb-4 dark:text-white">Exam Specific Packages</h2>
                        <p class="text-lg text-gray-600 max-w-2xl mx-auto dark:text-gray-400">
                            Focused preparation for your target examination
                        </p>
                    </div>
                    
                    <!-- Exam Tabs -->
                    <div class="flex flex-wrap gap-2 mb-8 justify-center">
                        <div class="exam-tab active" data-tab="ssc">SSC</div>
                        <div class="exam-tab" data-tab="railway">Railway</div>
                        <div class="exam-tab" data-tab="courts">Courts</div>
                        <div class="exam-tab" data-tab="supreme-court">Supreme Court</div>
                        <div class="exam-tab" data-tab="defence">Defence & Army</div>
                    </div>
                    
                    <!-- SSC Package -->
                    <div class="exam-content active" id="ssc-package">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- SSC 3 Month -->
                            <div class="pricing-card bg-white shadow-md dark:bg-gray-800">
                                <div class="p-8">
                                    <h3 class="text-xl font-semibold mb-2 dark:text-white">SSC Package</h3>
                                    <p class="text-gray-600 mb-6 dark:text-gray-400">3 Months Access</p>
                                    
                                    <div class="mb-6">
                                        <span class="text-4xl font-bold dark:text-white"><?= formatCurrency(1499) ?></span>
                                        <span class="text-gray-500 line-through ml-2 dark:text-gray-400"><?= formatCurrency(1999) ?></span>
                                        <span class="text-sm text-gray-500 ml-2 dark:text-gray-400">(25% OFF)</span>
                                    </div>
                                    
                                    <a href="#" class="block w-full text-center bg-brand-blue hover:bg-brand-blue/90 text-white font-medium py-3 px-4 rounded-lg transition">
                                        Get SSC Package
                                    </a>
                                </div>
                                
                                <div class="border-t border-gray-200 px-8 py-6 dark:border-gray-700">
                                    <h4 class="font-medium mb-4 dark:text-white">Features:</h4>
                                    <ul>
                                        <li class="feature-item">
                                            <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                            <span class="dark:text-gray-300">300+ SSC Specific Dictations</span>
                                        </li>
                                        <li class="feature-item">
                                            <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                            <span class="dark:text-gray-300">SSC Typing Interface</span>
                                        </li>
                                        <li class="feature-item">
                                            <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                            <span class="dark:text-gray-300">10 Mock Tests</span>
                                        </li>
                                        <li class="feature-item">
                                            <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                            <span class="dark:text-gray-300">Previous 5 Years Papers</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- SSC 6 Month -->
                            <div class="pricing-card bg-white shadow-lg popular dark:bg-gray-800">
                                <div class="popular-badge">Recommended</div>
                                <div class="p-8">
                                    <h3 class="text-xl font-semibold mb-2 dark:text-white">SSC Package</h3>
                                    <p class="text-gray-600 mb-6 dark:text-gray-400">6 Months Access</p>
                                    
                                    <div class="mb-6">
                                        <span class="text-4xl font-bold dark:text-white"><?= formatCurrency(2499) ?></span>
                                        <span class="text-gray-500 line-through ml-2 dark:text-gray-400"><?= formatCurrency(3999) ?></span>
                                        <span class="text-sm text-gray-500 ml-2 dark:text-gray-400">(38% OFF)</span>
                                    </div>
                                    
                                    <a href="#" class="block w-full text-center bg-brand-red hover:bg-brand-red/90 text-white font-medium py-3 px-4 rounded-lg transition">
                                        Get SSC Package
                                    </a>
                                </div>
                                
                                <div class="border-t border-gray-200 px-8 py-6 dark:border-gray-700">
                                    <h4 class="font-medium mb-4 dark:text-white">All 3 Month Features, Plus:</h4>
                                    <ul>
                                        <li class="feature-item">
                                            <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                            <span class="dark:text-gray-300">500+ SSC Dictations</span>
                                        </li>
                                        <li class="feature-item">
                                            <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                            <span class="dark:text-gray-300">25 Mock Tests</span>
                                        </li>
                                        <li class="feature-item">
                                            <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                            <span class="dark:text-gray-300">Previous 10 Years Papers</span>
                                        </li>
                                        <li class="feature-item">
                                            <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                            <span class="dark:text-gray-300">Expert Evaluation</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- SSC 1 Year -->
                            <div class="pricing-card bg-white shadow-md dark:bg-gray-800">
                                <div class="p-8">
                                    <h3 class="text-xl font-semibold mb-2 dark:text-white">SSC Package</h3>
                                    <p class="text-gray-600 mb-6 dark:text-gray-400">1 Year Access</p>
                                    
                                    <div class="mb-6">
                                        <span class="text-4xl font-bold dark:text-white"><?= formatCurrency(3999) ?></span>
                                        <span class="text-gray-500 line-through ml-2 dark:text-gray-400"><?= formatCurrency(6999) ?></span>
                                        <span class="text-sm text-gray-500 ml-2 dark:text-gray-400">(43% OFF)</span>
                                    </div>
                                    
                                    <a href="#" class="block w-full text-center bg-brand-blue hover:bg-brand-blue/90 text-white font-medium py-3 px-4 rounded-lg transition">
                                        Get SSC Package
                                    </a>
                                </div>
                                
                                <div class="border-t border-gray-200 px-8 py-6 dark:border-gray-700">
                                    <h4 class="font-medium mb-4 dark:text-white">All 6 Month Features, Plus:</h4>
                                    <ul>
                                        <li class="feature-item">
                                            <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                            <span class="dark:text-gray-300">1-on-1 Coaching (4 sessions)</span>
                                        </li>
                                        <li class="feature-item">
                                            <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                            <span class="dark:text-gray-300">Printed Study Materials</span>
                                        </li>
                                        <li class="feature-item">
                                            <i data-lucide="check" class="feature-icon text-green-500 w-5 h-5"></i>
                                            <span class="dark:text-gray-300">Interview Preparation</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Other exam packages would follow the same pattern -->
                    <!-- Content for other tabs would be loaded via JavaScript -->
                </div>

                <!-- FAQ Section -->
                <div class="mt-8 bg-white p-8 rounded-lg shadow dark:bg-gray-800">
                    <h2 class="text-2xl font-bold mb-8 text-center dark:text-white">Frequently Asked Questions</h2>
                    
                    <div class="faq-list">
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3 class="font-semibold text-lg dark:text-white">What's included in the exam packages?</h3>
                                <i data-lucide="chevron-down" class="faq-toggle-icon w-5 h-5 text-brand-blue"></i>
                            </div>
                            <div class="faq-answer">
                                <p class="text-gray-600 dark:text-gray-400">
                                    Each package includes access to exam-specific dictations, practice tests, previous year papers, and relevant study materials. Higher tier packages include additional features like expert evaluations and coaching sessions.
                                </p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3 class="font-semibold text-lg dark:text-white">Can I switch between packages?</h3>
                                <i data-lucide="chevron-down" class="faq-toggle-icon w-5 h-5 text-brand-blue"></i>
                            </div>
                            <div class="faq-answer">
                                <p class="text-gray-600 dark:text-gray-400">
                                    Yes, you can upgrade your package at any time. We'll prorate the difference based on your remaining subscription period. Downgrades take effect at your next billing cycle.
                                </p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3 class="font-semibold text-lg dark:text-white">How do I apply a promo code?</h3>
                                <i data-lucide="chevron-down" class="faq-toggle-icon w-5 h-5 text-brand-blue"></i>
                            </div>
                            <div class="faq-answer">
                                <p class="text-gray-600 dark:text-gray-400">
                                    Promo codes can be applied during checkout. Enter the code in the designated field before completing your payment to receive the discount.
                                </p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3 class="font-semibold text-lg dark:text-white">What payment methods do you accept?</h3>
                                <i data-lucide="chevron-down" class="faq-toggle-icon w-5 h-5 text-brand-blue"></i>
                            </div>
                            <div class="faq-answer">
                                <p class="text-gray-600 dark:text-gray-400">
                                    We accept all major credit/debit cards, UPI payments, net banking, and popular digital wallets like Paytm and PhonePe.
                                </p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3 class="font-semibold text-lg dark:text-white">Is there a money-back guarantee?</h3>
                                <i data-lucide="chevron-down" class="faq-toggle-icon w-5 h-5 text-brand-blue"></i>
                            </div>
                            <div class="faq-answer">
                                <p class="text-gray-600 dark:text-gray-400">
                                    We offer a 7-day money-back guarantee if you're not satisfied with our service. Contact our support team within 7 days of purchase for a full refund.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Support Section -->
                <div class="mt-12 bg-brand-blue text-white p-8 rounded-lg shadow">
                    <div class="flex flex-col md:flex-row justify-between items-center">
                        <div class="mb-6 md:mb-0">
                            <h2 class="text-2xl font-bold mb-2">Need Help Choosing a Package?</h2>
                            <p class="opacity-90 max-w-lg">
                                Our experts are available to help you select the perfect package for your exam preparation needs.
                            </p>
                        </div>
                        <a href="https://wa.me/919204123453?text=Hi%20Shadab%20Sir,%20I%20need%20help%20choosing%20a%20StenoPlus%20package" 
                           class="flex items-center bg-white text-brand-blue px-6 py-3 rounded-lg font-bold hover:bg-gray-100 transition">
                            <i data-lucide="message-circle" class="w-5 h-5 mr-2"></i>
                            Chat on WhatsApp
                        </a>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="mt-12 text-center text-gray-600 dark:text-gray-400">
                    <p>© 2025 StenoPlus. All Rights Reserved.</p>
                    <p class="mt-2">Made with ❤ by Code Clue</p>
                </div>
            </div>
        </section>
    </main>

    <script>
        lucide.createIcons();

        // Dark Mode Toggle
        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle("dark");
            localStorage.setItem("darkMode", isDark ? "enabled" : "disabled");
        }
        document.getElementById("darkModeToggle").addEventListener("click", toggleDarkMode);
        if (localStorage.getItem("darkMode") === "enabled") {
            document.documentElement.classList.add("dark");
        }

        // Sidebar Toggle for Mobile
        const sidebar = document.getElementById("sidebar");
        const openSidebar = document.getElementById("openSidebar");
        const closeSidebar = document.getElementById("closeSidebar");

        openSidebar.addEventListener("click", function () {
            sidebar.classList.remove("sidebar-hidden");
        });
        closeSidebar.addEventListener("click", function () {
            sidebar.classList.add("sidebar-hidden");
        });
        document.addEventListener("click", function (event) {
            if (!sidebar.contains(event.target) && !openSidebar.contains(event.target) && !closeSidebar.contains(event.target)) {
                sidebar.classList.add("sidebar-hidden");
            }
        });

        // Exam Tab Switching
        document.querySelectorAll('.exam-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs
                document.querySelectorAll('.exam-tab').forEach(t => t.classList.remove('active'));
                // Add active class to clicked tab
                tab.classList.add('active');
                
                // Hide all exam packages
                document.querySelectorAll('.exam-content').forEach(content => {
                    content.classList.remove('active');
                });
                
                // Show selected exam package
                const tabId = tab.getAttribute('data-tab');
                document.getElementById(tabId + '-package').classList.add('active');
            });
        });

        // FAQ Accordion
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const answer = question.nextElementSibling;
                const icon = question.querySelector('.faq-toggle-icon');
                
                // Toggle answer visibility
                answer.classList.toggle('active');
                
                // Rotate icon
                if (answer.classList.contains('active')) {
                    icon.style.transform = 'rotate(180deg)';
                } else {
                    icon.style.transform = 'rotate(0deg)';
                }
            });
        });
    </script>
</body>
</html>