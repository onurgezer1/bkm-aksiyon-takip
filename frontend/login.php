<?php
/**
 * BKM Aksiyon Takip - Ultra Modern Frontend Login Template
 */

// WordPress'i yükle
if (!defined('ABSPATH')) {
    // Plugin dizininden WordPress kök dizinine git
    $wp_config_path = dirname(dirname(__FILE__));
    while (!file_exists($wp_config_path . '/wp-config.php') && $wp_config_path !== '/') {
        $wp_config_path = dirname($wp_config_path);
    }
    
    if (file_exists($wp_config_path . '/wp-config.php')) {
        require_once($wp_config_path . '/wp-config.php');
        require_once($wp_config_path . '/wp-load.php');
    } else {
        // Alternatif yol - WordPress'in wp-load.php'sini dahil et
        $wp_load_paths = [
            '../../../wp-load.php',
            '../../../../wp-load.php',
            '../../../../../wp-load.php',
            '../wp-load.php',
            '../../wp-load.php'
        ];
        
        foreach ($wp_load_paths as $path) {
            if (file_exists(dirname(__FILE__) . '/' . $path)) {
                require_once(dirname(__FILE__) . '/' . $path);
                break;
            }
        }
    }
}

// Eğer WordPress fonksiyonları hala yüklenmediyse basit değerler kullan
if (!function_exists('get_option')) {
    function get_option($option_name, $default = false) {
        return $default;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_login_url')) {
    function wp_login_url() {
        return '/wp-login.php';
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) {
        return wp_hash($action . '|' . time(), 'nonce');
    }
}

if (!function_exists('wp_hash')) {
    function wp_hash($data, $scheme = 'auth') {
        return md5($data . 'salt');
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return '/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return false;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        return true;
    }
}

// Get company information
$company_name = get_option('bkm_company_name', 'BKM Aksiyon Takip');
$company_logo = get_option('bkm_company_logo', '');

?>

<!DOCTYPE html>
<html lang="tr">
<head>    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="format-detection" content="telephone=no">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?php echo esc_html($company_name); ?> - Giriş</title>
    <script src="https://cdn.tailwindcss.com"></script><script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'company-primary': '#1a365d',    // Koyu mavi - ana renk
                        'company-secondary': '#2c5aa0',  // Orta mavi
                        'company-accent': '#4299e1',     // Açık mavi
                        'company-light': '#bee3f8',     // Çok açık mavi
                        'company-gradient-start': '#1a365d',
                        'company-gradient-end': '#2c5aa0'
                    },
                    animation: {
                        'gradient': 'gradient 8s ease infinite',
                        'float': 'float 6s ease-in-out infinite',
                        'glow': 'glow 2s ease-in-out infinite alternate',
                        'slide-up': 'slide-up 0.6s ease-out',
                        'fade-in': 'fade-in 0.8s ease-out',
                        'bounce-subtle': 'bounce-subtle 2s ease-in-out infinite',
                    },
                    keyframes: {
                        gradient: {
                            '0%, 100%': {
                                'background-size': '200% 200%',
                                'background-position': 'left center'
                            },
                            '50%': {
                                'background-size': '200% 200%',
                                'background-position': 'right center'
                            }
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-20px)' }
                        },
                        glow: {
                            '0%': { 'box-shadow': '0 0 20px rgba(66, 153, 225, 0.4)' },
                            '100%': { 'box-shadow': '0 0 40px rgba(66, 153, 225, 0.8)' }
                        },
                        'slide-up': {
                            '0%': { opacity: '0', transform: 'translateY(30px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        'fade-in': {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        'bounce-subtle': {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-5px)' }
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">    <style>
        /* TEMA OVERRIDE - TÜM TEMA KURALLARI GEÇERSİZ */
        * {
            margin: 0 !important;
            padding: 0 !important;
            box-sizing: border-box !important;
        }
        
        /* HTML ve BODY tam kontrol */
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            height: 100% !important;
            width: 100% !important;
            max-width: none !important;
            overflow-x: hidden !important;
            position: relative !important;
        }
        
        /* WordPress tema container'larını geçersiz kıl */
        .site, .site-content, .content-area, .site-main,
        .container, .container-fluid, .wrap, .wrapper,
        .page, .single, .archive, .home, .blog,
        #page, #content, #primary, #main,
        .entry, .entry-content, .post, .page-content {
            margin: 0 !important;
            padding: 0 !important;
            max-width: none !important;
            width: 100% !important;
        }
        
        /* Arka plan katmanları - TEMA GEÇERSİZ */
        .login-background-layer {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            margin: 0 !important;
            padding: 0 !important;
            max-width: none !important;
        }
        
        /* Ek gradient efektleri */
        .bg-overlay-gradient {
            background: linear-gradient(45deg, 
                rgba(26, 54, 93, 0.9) 0%, 
                rgba(44, 90, 160, 0.8) 25%, 
                rgba(66, 153, 225, 0.7) 50%, 
                rgba(44, 90, 160, 0.8) 75%, 
                rgba(26, 54, 93, 0.9) 100%);
            animation: shiftGradient 10s ease-in-out infinite;
        }
        
        @keyframes shiftGradient {
            0%, 100% { 
                background-position: 0% 50%; 
                transform: scale(1);
            }
            50% { 
                background-position: 100% 50%; 
                transform: scale(1.05);
            }
        }
        
        /* Partiküller için gelişmiş animasyon */
        .particle-glow {
            box-shadow: 0 0 6px rgba(255, 255, 255, 0.8);
            animation: particleGlow 3s ease-in-out infinite alternate, float 8s ease-in-out infinite;
        }
        
        @keyframes particleGlow {
            0% { 
                opacity: 0.2; 
                transform: scale(1);
                box-shadow: 0 0 6px rgba(255, 255, 255, 0.4);
            }
            100% { 
                opacity: 0.6; 
                transform: scale(1.2);
                box-shadow: 0 0 12px rgba(255, 255, 255, 0.8);
            }
        }
        
        @keyframes float {
            0%, 100% { 
                transform: translateY(0px) translateX(0px); 
            }
            25% { 
                transform: translateY(-20px) translateX(10px); 
            }
            50% { 
                transform: translateY(0px) translateX(-10px); 
            }
            75% { 
                transform: translateY(-10px) translateX(5px); 
            }
        }
        
        /* Login container tam ekran */
        #login-root {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            z-index: 9999 !important;
            margin: 0 !important;
            padding: 0 !important;
        }
          /* Mobile responsive */
        @media screen and (max-width: 768px) {
            html, body, #login-root {
                width: 100vw !important;
                height: 100vh !important;
                overflow-x: hidden !important;
            }
        }        /* Input field icon positioning fix */
        .input-icon {
            position: absolute !important;
            left: 16px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            pointer-events: none !important;
            z-index: 10 !important;
            color: rgba(255, 255, 255, 0.7) !important;
            font-size: 16px !important;
        }        .input-field {
            padding-left: 50px !important;
            padding-right: 16px !important;
            padding-top: 10px !important;
            padding-bottom: 10px !important;
            position: relative !important;
            z-index: 1 !important;
            font-size: 15px !important;
            min-height: 44px !important;
            border-radius: 12px !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        
        .input-field:focus {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.15) !important;
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-company-gradient-start via-company-secondary to-company-accent overflow-hidden relative">
    <!-- Ana arka plan katmanı - Tam ekran VW/VH -->
    <div class="login-background-layer bg-gradient-to-br from-company-gradient-start via-company-secondary to-company-accent" style="z-index: 1;"></div>
    
    <!-- Animasyonlu gradient katmanı - Tam ekran VW/VH -->
    <div class="login-background-layer bg-overlay-gradient" style="z-index: 2;"></div>
    
    <!-- Ek efekt katmanı -->
    <div class="login-background-layer" style="background: radial-gradient(ellipse at center, rgba(255,255,255,0.1) 0%, transparent 50%, rgba(26,54,93,0.3) 100%); z-index: 3;"></div>
      <!-- Floating Particles Background -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-10" id="particles-container"></div>
    
    <!-- Main Login Container -->
    <div class="fixed inset-0 w-screen h-screen flex items-center justify-center p-4" style="z-index: 30;">
        <!-- Main Login Card -->
        <div class="relative z-40 w-full max-w-sm mx-auto">
            <!-- Material Design Glass Card -->
            <div class="bg-white/20 backdrop-blur-lg rounded-2xl shadow-2xl border border-white/40 px-8 py-10" style="background: rgba(255,255,255,0.15); min-height: 400px;">
                  <!-- Company Logo Section -->
                <div class="text-center mb-10 animate-slide-up"><?php if ($company_logo): ?>
                        <div class="flex flex-col items-center space-y-3">
                            <div class="relative">
                                <img src="<?php echo esc_url($company_logo); ?>" 
                                     alt="<?php echo esc_attr($company_name); ?>"
                                     class="h-20 w-auto max-w-xs object-contain filter drop-shadow-2xl">
                            </div>
                            <h1 class="text-2xl font-bold text-white text-center tracking-wide">
                                <?php echo esc_html($company_name); ?>
                            </h1>
                            <p class="text-white text-base animate-fade-in font-medium">Aksiyon Takip Girişi</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center">
                            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-r from-company-primary to-company-secondary rounded-2xl mb-3 shadow-lg">
                                <i class="fas fa-building text-white text-2xl"></i>
                            </div>
                            <h1 class="text-2xl font-bold text-white tracking-wide">
                                <?php echo esc_html($company_name); ?>
                            </h1>
                            <p class="text-white text-base mt-2 animate-fade-in font-medium">Aksiyon Takip Girişi</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Login Form -->
                <div id="login-form-container">
                    <!-- Timeout Message -->
                    <?php if (isset($_GET['timeout']) && $timeout_message = get_transient('bkm_timeout_message')): ?>
                        <div class="mb-6 p-4 rounded-xl bg-orange-500/20 border border-orange-300/50 text-white text-sm transition-all duration-500 opacity-100 translate-y-0" id="timeout-message">
                            <div class="flex items-center">
                                <i class="fas fa-clock mr-2 text-orange-300"></i>
                                <?php echo esc_html($timeout_message); delete_transient('bkm_timeout_message'); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Error Message -->
                    <?php if ($error = get_transient('bkm_login_error')): ?>
                        <div class="mb-6 p-4 rounded-xl bg-red-500/20 border border-red-300/50 text-white text-sm transition-all duration-500 opacity-100 translate-y-0" id="error-message">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle mr-2 text-red-300"></i>
                                <?php echo esc_html($error); delete_transient('bkm_login_error'); ?>
                            </div>
                        </div>
                    <?php endif; ?>                    <!-- Login Form -->
                    <form method="post" action="<?php echo esc_url(wp_login_url()); ?>" class="space-y-6" id="login-form">
                          <!-- Username Field -->
                        <div class="relative mb-8 animate-slide-up">
                            <div class="relative">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" name="log" required
                                       autocomplete="username"
                                       autocapitalize="none"
                                       autocorrect="off"
                                       spellcheck="false"
                                       inputmode="text"
                                       class="input-field block w-full border border-white/30 rounded-xl 
                                              bg-white/10 backdrop-blur-sm text-white placeholder-white/60
                                              focus:outline-none focus:ring-2 focus:ring-white/50 focus:border-white/50
                                              transition-all duration-300 hover:bg-white/15"
                                       placeholder="Kullanıcı Adı veya E-postanizi giriniz">
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="relative mb-8 animate-slide-up">
                            <div class="relative">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" name="pwd" required
                                       autocomplete="current-password"
                                       autocapitalize="none"
                                       autocorrect="off"
                                       spellcheck="false"
                                       class="input-field block w-full border border-white/30 rounded-xl 
                                              bg-white/10 backdrop-blur-sm text-white placeholder-white/60
                                              focus:outline-none focus:ring-2 focus:ring-white/50 focus:border-white/50
                                              transition-all duration-300 hover:bg-white/15"
                                       placeholder="Şifrenizi giriniz">
                            </div>
                        </div>

                        <!-- Remember Me Checkbox -->
                        <div class="flex items-center justify-between mb-8 animate-slide-up">
                            <label class="flex items-center text-white cursor-pointer group">
                                <input type="checkbox" name="rememberme" value="forever"
                                       class="w-4 h-4 text-company-accent bg-white/10 border-white/30 rounded 
                                              focus:ring-white/50 focus:ring-2 transition-all duration-300 mr-2">
                                <span class="text-sm font-medium group-hover:text-white/90 transition-colors duration-300">
                                    Beni Hatırla
                                </span>
                            </label>
                            
                            <button type="button" id="forgot-password-btn"
                                    class="text-sm text-white hover:text-blue-200 transition-colors duration-300 hover:underline font-medium cursor-pointer relative z-50 px-2 py-1 rounded bg-transparent border-0 outline-none">
                                Şifremi Unuttum
                            </button>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" id="login-submit-btn"
                                class="w-full bg-gradient-to-r from-company-primary to-company-secondary text-white font-semibold py-4 px-6 rounded-xl
                                       hover:from-company-secondary hover:to-company-accent focus:outline-none focus:ring-4 focus:ring-company-accent/50
                                       transform transition-all duration-300 hover:scale-[1.02] hover:-translate-y-1 disabled:opacity-50 disabled:cursor-not-allowed
                                       shadow-lg hover:shadow-2xl animate-slide-up text-base elevation-2 relative overflow-hidden"
                                style="background: linear-gradient(135deg, #1a365d 0%, #2c5aa0 50%, #4299e1 100%)">
                            <div class="flex items-center justify-center" id="login-btn-content">
                                <i class="fas fa-sign-in-alt mr-2"></i>
                                Giriş Yap
                            </div>
                        </button>

                        <!-- Hidden Fields -->
                        <input type="hidden" name="bkm_nonce" value="<?php echo wp_create_nonce('bkm_login_nonce'); ?>">
                        <input type="hidden" name="bkm_login_submit" value="1">
                    </form>

                    <!-- Footer -->
                    <div class="mt-6 text-center animate-fade-in">
                        <p class="text-white/60 text-xs font-normal">
                            © 2025 <?php echo esc_html($company_name); ?>. Tüm hakları saklıdır.
                        </p>
                    </div>
                </div>

                <!-- Forgot Password Form (Initially Hidden) -->
                <div id="forgot-password-form" class="space-y-6" style="display: none;">
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-company-primary to-company-secondary rounded-2xl mb-4">
                            <i class="fas fa-key text-white text-xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-2">Şifremi Unuttum</h3>
                        <p class="text-white/80 text-sm">
                            E-posta adresinizi girin, size şifre sıfırlama bağlantısı gönderelim.
                        </p>
                    </div>                    <form class="space-y-6" id="forgot-form">
                        <div class="relative mb-10">
                            <div class="relative">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="text" name="user_login" required id="forgot-user-login"
                                       class="input-field block w-full border border-white/30 rounded-xl 
                                              bg-white/10 backdrop-blur-sm text-white placeholder-white/60
                                              focus:outline-none focus:ring-2 focus:ring-white/50 focus:border-white/50
                                              transition-all duration-300 hover:bg-white/15"
                                       placeholder="Kullanıcı adınız veya e-posta adresiniz">
                            </div>
                        </div>

                        <div class="flex space-x-4">
                            <button type="button" id="back-to-login-btn"
                                    class="flex-1 bg-white/10 text-white font-bold py-4 px-6 rounded-xl
                                           hover:bg-white/20 focus:outline-none focus:ring-4 focus:ring-white/30
                                           transition-all duration-300 text-base border border-white/30">
                                ← Geri Dön
                            </button>
                            
                            <button type="submit" id="forgot-submit-btn"
                                    class="flex-1 bg-gradient-to-r from-company-primary to-company-secondary text-white font-bold py-4 px-6 rounded-xl
                                           hover:from-company-secondary hover:to-company-accent focus:outline-none focus:ring-4 focus:ring-white/30
                                           transform transition-all duration-300 hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed
                                           shadow-lg hover:shadow-2xl text-base">
                                <div class="flex items-center justify-center" id="forgot-btn-content">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Gönder
                                </div>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Decorative Elements -->
            <div class="absolute -top-6 -left-6 w-20 h-20 bg-company-accent/20 rounded-full blur-2xl animate-pulse"></div>
            <div class="absolute -bottom-6 -right-6 w-24 h-24 bg-company-primary/20 rounded-full blur-2xl animate-pulse delay-1000"></div>
        </div>
    </div>

    <script>
        // Pure JavaScript implementation - No React/JSX
        document.addEventListener('DOMContentLoaded', function() {
            // Create floating particles
            function createParticles() {
                const container = document.getElementById('particles-container');
                for (let i = 0; i < 30; i++) {
                    const particle = document.createElement('div');
                    particle.className = 'absolute w-2 h-2 bg-white opacity-20 rounded-full animate-float';
                    particle.style.left = Math.random() * 100 + '%';
                    particle.style.top = Math.random() * 100 + '%';
                    particle.style.animationDelay = Math.random() * 6 + 's';
                    particle.style.animationDuration = (6 + Math.random() * 4) + 's';
                    container.appendChild(particle);
                }
            }
            createParticles();

            // Form elements
            const loginForm = document.getElementById('login-form');
            const loginFormContainer = document.getElementById('login-form-container');
            const forgotPasswordForm = document.getElementById('forgot-password-form');
            const forgotPasswordBtn = document.getElementById('forgot-password-btn');
            const backToLoginBtn = document.getElementById('back-to-login-btn');
            const loginSubmitBtn = document.getElementById('login-submit-btn');
            const forgotSubmitBtn = document.getElementById('forgot-submit-btn');
            const forgotForm = document.getElementById('forgot-form');

            // Show forgot password form
            forgotPasswordBtn.addEventListener('click', function(e) {
                e.preventDefault();
                loginFormContainer.style.display = 'none';
                forgotPasswordForm.style.display = 'block';
            });

            // Back to login form
            backToLoginBtn.addEventListener('click', function(e) {
                e.preventDefault();
                forgotPasswordForm.style.display = 'none';
                loginFormContainer.style.display = 'block';
                document.getElementById('forgot-user-login').value = '';
            });

            // Login form submit
            loginForm.addEventListener('submit', function(e) {
                const submitBtn = document.getElementById('login-submit-btn');
                const btnContent = document.getElementById('login-btn-content');
                
                submitBtn.disabled = true;
                btnContent.innerHTML = `
                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                    Giriş Yapılıyor...
                `;
                
                // Allow form to submit naturally after showing loading state
                setTimeout(() => {
                    // Form will submit naturally
                }, 100);
            });

            // Forgot password form submit
            forgotForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const submitBtn = document.getElementById('forgot-submit-btn');
                const btnContent = document.getElementById('forgot-btn-content');
                const userLogin = document.getElementById('forgot-user-login').value;
                
                if (!userLogin.trim()) {
                    alert('Lütfen kullanıcı adınızı veya e-posta adresinizi giriniz.');
                    return;
                }
                
                submitBtn.disabled = true;
                btnContent.innerHTML = `
                    <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                    Gönderiliyor...
                `;
                
                // AJAX request for forgot password
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'bkm_forgot_password',
                        user_login: userLogin,
                        nonce: '<?php echo wp_create_nonce('bkm_forgot_password_nonce'); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    submitBtn.disabled = false;
                    btnContent.innerHTML = `
                        <i class="fas fa-paper-plane mr-2"></i>
                        Gönder
                    `;
                    
                    if (data.success) {
                        alert('✅ Şifre sıfırlama bağlantısı e-posta adresinize gönderildi.');
                        forgotPasswordForm.style.display = 'none';
                        loginFormContainer.style.display = 'block';
                        document.getElementById('forgot-user-login').value = '';
                    } else {
                        alert('❌ Hata: ' + (data.data || 'Bir sorun oluştu.'));
                    }
                })
                .catch(error => {
                    submitBtn.disabled = false;
                    btnContent.innerHTML = `
                        <i class="fas fa-paper-plane mr-2"></i>
                        Gönder
                    `;
                    alert('❌ Bağlantı hatası oluştu.');
                });
            });            // Hide error message after 5 seconds
            const errorMessage = document.getElementById('error-message');
            if (errorMessage) {
                setTimeout(() => {
                    errorMessage.style.opacity = '0';
                    errorMessage.style.transform = 'translateY(-8px)';
                }, 5000);
            }
            
            // Mobile-specific enhancements
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            if (isMobile) {
                console.log('🔍 [BKM Mobile] Mobile device detected');
                
                // Add mobile-specific form handling
                const loginForm = document.getElementById('login-form');
                const usernameInput = document.querySelector('input[name="log"]');
                const passwordInput = document.querySelector('input[name="pwd"]');
                
                if (loginForm && usernameInput && passwordInput) {
                    // Debug mobile form submission
                    loginForm.addEventListener('submit', function(e) {
                        const username = usernameInput.value.trim();
                        const password = passwordInput.value;
                        
                        console.log('🔍 [BKM Mobile] Form submission:', {
                            username: username,
                            usernameLength: username.length,
                            passwordLength: password.length,
                            userAgent: navigator.userAgent,
                            timestamp: new Date().toISOString()
                        });
                        
                        // Mobile validation enhancements
                        if (!username || !password) {
                            e.preventDefault();
                            alert('⚠️ Lütfen kullanıcı adı ve şifre alanlarını doldurunuz.');
                            return false;
                        }
                        
                        // Clean username from potential auto-fill artifacts
                        if (usernameInput.value !== username) {
                            console.log('🔍 [BKM Mobile] Cleaning username whitespace');
                            usernameInput.value = username;
                        }
                    });
                    
                    // Handle mobile auto-fill issues
                    usernameInput.addEventListener('blur', function() {
                        this.value = this.value.trim();
                    });
                    
                    passwordInput.addEventListener('blur', function() {
                        this.value = this.value.trim();
                    });
                    
                    // Prevent mobile zoom on input focus
                    document.addEventListener('touchstart', function() {}, true);
                }
            }
        });
    </script>
</body>
</html>