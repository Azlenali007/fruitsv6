<?php
/**
 * Admin Login View with Two-Factor Authentication (TOTP)
 * N.A Fresh Fruits & Coconuts
 */
$requires2fa = $requires2fa ?? (!empty($_SESSION['na_admin_2fa_pending']));
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - N.A Fresh Fruits & Coconuts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            600: '#235d3a',
                            700: '#1b4a2e',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="h-full flex items-center justify-center p-4">

<div class="max-w-md w-full space-y-6">
    
    <div class="text-center space-y-2">
        <a href="/" class="inline-flex items-center gap-2 text-brand-600">
            <span class="w-10 h-10 rounded-xl bg-brand-600 text-white flex items-center justify-center font-black text-xl">NA</span>
        </a>
        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Admin Portal</h1>
        <p class="text-xs text-gray-500">N.A Fresh Fruits & Coconuts Management System</p>
    </div>

    <!-- Login Box -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-5">
        
        <?php if (!empty($loginError)): ?>
        <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl font-medium flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <span><?= e($loginError) ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($loginNotice)): ?>
        <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl font-medium flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            <span><?= e($loginNotice) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($requires2fa): ?>
        <!-- Step 2: Two-Factor Authentication TOTP Form -->
        <div class="text-center py-2 space-y-2">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-xl font-bold">
                🔐
            </div>
            <h2 class="text-sm font-black text-gray-900">Two-Factor Authentication</h2>
            <p class="text-xs text-gray-500">Open your authenticator app (Google Authenticator, Microsoft Authenticator, Authy) and enter the 6-digit code.</p>
        </div>

        <form action="/admin/login" method="POST" class="space-y-4">
            <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="verify_2fa">

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1 text-center">6-Digit Authenticator Code</label>
                <input 
                    type="text" 
                    name="totp_code" 
                    maxlength="6" 
                    pattern="[0-9]{6}" 
                    inputmode="numeric" 
                    autocomplete="one-time-code" 
                    autofocus 
                    required 
                    placeholder="123456" 
                    class="w-full text-center tracking-widest font-mono text-2xl font-black bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-brand-600 focus:outline-none"
                >
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold text-sm py-3 px-4 rounded-xl shadow-xs transition">
                    Verify & Access Admin
                </button>
            </div>

            <div class="text-center pt-2">
                <a href="/admin/login?reset_2fa=1" class="text-xs text-gray-500 hover:text-gray-700">Cancel and sign in with another account</a>
            </div>
        </form>

        <?php else: ?>
        <!-- Step 1: Standard Username & Password Form -->
        <form action="/admin/login" method="POST" class="space-y-4">
            <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Username</label>
                <input 
                    type="text" 
                    name="username" 
                    value="<?= e($_POST['username'] ?? 'admin') ?>" 
                    required 
                    autocomplete="username"
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-600 focus:outline-none"
                >
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Password</label>
                <input 
                    type="password" 
                    name="password" 
                    value="admin12345"
                    required 
                    autocomplete="current-password"
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-600 focus:outline-none"
                >
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold text-sm py-3 px-4 rounded-xl shadow-xs transition">
                    Sign In to Admin Panel
                </button>
            </div>
        </form>

        <div class="border-t border-gray-100 pt-3 text-center">
            <p class="text-[11px] text-gray-400">
                Default Credentials: Username <code class="font-mono text-gray-700 font-bold">admin</code> / Password <code class="font-mono text-gray-700 font-bold">admin12345</code>
            </p>
        </div>
        <?php endif; ?>

    </div>

    <div class="text-center">
        <a href="/" class="text-xs font-semibold text-gray-500 hover:text-brand-600 transition">
            &larr; Return to Customer Website
        </a>
    </div>

</div>

</body>
</html>
