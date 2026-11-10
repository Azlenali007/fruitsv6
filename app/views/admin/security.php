<?php
/**
 * Admin Security Settings & Audit Trail View
 * N.A Fresh Fruits & Coconuts
 */
$adminTitle = 'Security & Audit Logs - N.A Fresh Fruits & Coconuts';
$activeAdminTab = 'security';
require_once __DIR__ . '/layout-header.php';

$admin = AuthService::user();
$twoFactorEnabled = !empty($admin['two_factor_enabled']);
$totpSecret = $_SESSION['na_temp_totp_secret'] ?? null;
if (!$twoFactorEnabled && empty($totpSecret)) {
    $totpSecret = TotpService::generateSecret();
    $_SESSION['na_temp_totp_secret'] = $totpSecret;
}
$provisioningUri = !empty($totpSecret) ? TotpService::getProvisioningUri($totpSecret, $admin['username'] ?? 'admin', 'N.A Fresh Fruits') : '';
$recentLogs = AdminAuditService::getRecentLogs(50);
?>

<div class="space-y-6" x-data="{ showSetup2FA: false }">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 bg-white p-5 rounded-3xl border border-gray-100 shadow-xs">
        <div>
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-slate-900 text-white flex items-center justify-center font-bold text-lg">
                    🛡️
                </div>
                <div>
                    <h1 class="text-xl font-extrabold text-gray-900 tracking-tight">Security Hardening & Audit Trail</h1>
                    <p class="text-xs text-gray-500">Argon2id password security, RFC 6238 TOTP 2FA, session timeout & immutable activity logs</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold <?= $twoFactorEnabled ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>">
                <span class="w-2 h-2 rounded-full <?= $twoFactorEnabled ? 'bg-emerald-500' : 'bg-amber-500' ?>"></span>
                <span>2FA: <?= $twoFactorEnabled ? 'Active (Protected)' : 'Disabled (Recommended)' ?></span>
            </span>
        </div>
    </div>

    <!-- Security Cards Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Card 1: Two-Factor Authentication Management -->
        <div class="lg:col-span-1 bg-white p-6 rounded-3xl border border-gray-100 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Two-Factor Auth (TOTP)</h2>
                <span class="text-xs font-mono font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-600">RFC 6238</span>
            </div>

            <?php if ($twoFactorEnabled): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs text-emerald-900 space-y-2">
                    <div class="flex items-center gap-2 font-bold text-emerald-800">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        <span>2FA Protection is Active</span>
                    </div>
                    <p class="text-[11px] text-emerald-700 leading-relaxed">
                        Your account requires a 6-digit TOTP verification code from Google Authenticator, Microsoft Authenticator, or 1Password at each sign in.
                    </p>
                </div>

                <form action="/admin/security/disable-2fa" method="POST" onsubmit="return confirm('Are you sure you want to disable 2FA? Your account will be less protected.');">
                    <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                    <button type="submit" class="w-full py-2.5 px-4 bg-red-50 hover:bg-red-100 text-red-700 text-xs font-bold rounded-xl border border-red-200 transition">
                        Disable Two-Factor Authentication
                    </button>
                </form>

            <?php else: ?>
                <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl text-xs text-amber-900 space-y-2">
                    <p class="text-[11px] text-amber-800 leading-relaxed">
                        Add an extra layer of security. Require a time-based code from your mobile authenticator app when signing into the Store Admin Panel.
                    </p>
                </div>

                <!-- Setup 2FA Flow -->
                <div class="space-y-4 pt-2">
                    <div class="space-y-2">
                        <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Secret Key (Manual Entry):</label>
                        <div class="flex items-center gap-2">
                            <input type="text" readonly value="<?= e($totpSecret) ?>" class="w-full font-mono font-bold text-xs bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-gray-800 select-all text-center">
                        </div>
                    </div>

                    <div class="text-center p-3 bg-gray-50 rounded-2xl border border-gray-200">
                        <!-- Standard QR code image using safe image generator -->
                        <img 
                            src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=<?= urlencode($provisioningUri) ?>" 
                            alt="2FA QR Code" 
                            class="w-36 h-36 mx-auto rounded-xl shadow-xs border border-white"
                        >
                        <span class="text-[10px] text-gray-400 block mt-2">Scan in Google Authenticator or Microsoft Authenticator</span>
                    </div>

                    <form action="/admin/security/enable-2fa" method="POST" class="space-y-3">
                        <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                        <input type="hidden" name="secret" value="<?= e($totpSecret) ?>">

                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Confirm 6-Digit Code:</label>
                            <input 
                                type="text" 
                                name="code" 
                                maxlength="6" 
                                pattern="[0-9]{6}" 
                                inputmode="numeric" 
                                required 
                                placeholder="123456" 
                                class="w-full text-center tracking-widest font-mono text-lg font-bold bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-brand-600 focus:outline-none"
                            >
                        </div>

                        <button type="submit" class="w-full bg-[#235d3a] hover:bg-[#1b4a2e] text-white font-bold text-xs py-2.5 px-4 rounded-xl shadow-xs transition">
                            Verify & Enable 2FA
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Card 2: Security Policies & Inactivity Timeout -->
        <div class="lg:col-span-2 bg-white p-6 rounded-3xl border border-gray-100 shadow-xs space-y-5">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Active Security Policies</h2>
                <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold">Hardened</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 space-y-1">
                    <span class="font-bold text-gray-800 block text-xs">Password Algorithm</span>
                    <span class="text-[11px] font-mono text-emerald-700 font-semibold block">Argon2id (RFC 9106) / Bcrypt (Cost 12)</span>
                    <p class="text-[10px] text-gray-500">Automatic transparent rehashing upon successful login.</p>
                </div>

                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 space-y-1">
                    <span class="font-bold text-gray-800 block text-xs">Session Inactivity Timeout</span>
                    <span class="text-[11px] font-mono text-emerald-700 font-semibold block">30 Minutes</span>
                    <p class="text-[10px] text-gray-500">Unused administrator sessions automatically expire and require re-login.</p>
                </div>

                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 space-y-1">
                    <span class="font-bold text-gray-800 block text-xs">Absolute Session Lifetime</span>
                    <span class="text-[11px] font-mono text-emerald-700 font-semibold block">8 Hours Maximum</span>
                    <p class="text-[10px] text-gray-500">Hard limit regardless of active interactions.</p>
                </div>

                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 space-y-1">
                    <span class="font-bold text-gray-800 block text-xs">Rate Limiting & Lockout</span>
                    <span class="text-[11px] font-mono text-emerald-700 font-semibold block">5 Attempts / 15-Min Lockout</span>
                    <p class="text-[10px] text-gray-500">Dual-layer throttling on IP address and administrator account.</p>
                </div>

                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 space-y-1">
                    <span class="font-bold text-gray-800 block text-xs">Session Cookie Hardening</span>
                    <span class="text-[11px] font-mono text-emerald-700 font-semibold block">HttpOnly • SameSite=Lax • Regenerated</span>
                    <p class="text-[10px] text-gray-500">Session ID rotated on every privilege elevation.</p>
                </div>

                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 space-y-1">
                    <span class="font-bold text-gray-800 block text-xs">Static & Upload Script Blocking</span>
                    <span class="text-[11px] font-mono text-emerald-700 font-semibold block">Strict Whitelist • .htaccess Deny</span>
                    <p class="text-[10px] text-gray-500">PHP execution strictly blocked in upload and static directories.</p>
                </div>
            </div>
        </div>

    </div>

    <!-- Card 3: Immutable Activity Audit Trail -->
    <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-xs space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Immutable Administrator Activity Logs</h2>
                <p class="text-xs text-gray-500">Tamper-evident audit trail of administrative logins, setting changes, and system operations</p>
            </div>
            <span class="text-xs font-mono font-semibold px-2.5 py-1 rounded-xl bg-gray-100 text-gray-700">
                <?= count($recentLogs) ?> events shown
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100 uppercase text-[10px] tracking-wider">
                        <th class="py-2.5 px-3">Timestamp</th>
                        <th class="py-2.5 px-3">Admin</th>
                        <th class="py-2.5 px-3">Action</th>
                        <th class="py-2.5 px-3">Target Entity</th>
                        <th class="py-2.5 px-3">IP Address</th>
                        <th class="py-2.5 px-3">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($recentLogs)): ?>
                    <tr>
                        <td colspan="6" class="py-8 text-center text-gray-400">
                            No administrative audit records logged yet.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                        <tr class="hover:bg-gray-50/80 transition font-mono text-[11px]">
                            <td class="py-2.5 px-3 text-gray-500 whitespace-nowrap"><?= e($log['created_at']) ?></td>
                            <td class="py-2.5 px-3 font-bold text-gray-800"><?= e($log['admin_username']) ?></td>
                            <td class="py-2.5 px-3">
                                <?php
                                    $action = $log['action'];
                                    $badge = 'bg-gray-100 text-gray-700';
                                    if (str_contains($action, 'SUCCESS')) $badge = 'bg-emerald-50 text-emerald-800 border border-emerald-200';
                                    elseif (str_contains($action, 'FAILED') || str_contains($action, 'LOCKED')) $badge = 'bg-red-50 text-red-800 border border-red-200';
                                    elseif (str_contains($action, 'UPDATE')) $badge = 'bg-blue-50 text-blue-800 border border-blue-200';
                                ?>
                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold <?= $badge ?>">
                                    <?= e($action) ?>
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-gray-600">
                                <?= e($log['entity_type'] ? ($log['entity_type'] . ($log['entity_id'] ? " #{$log['entity_id']}" : '')) : '—') ?>
                            </td>
                            <td class="py-2.5 px-3 text-gray-500 whitespace-nowrap"><?= e($log['ip_address']) ?></td>
                            <td class="py-2.5 px-3 text-gray-600 truncate max-w-xs font-sans text-xs">
                                <?= e($log['details'] ?: '—') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout-footer.php'; ?>
