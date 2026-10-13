<div style="display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 16px;">
    <div class="sq-card" style="width: 100%; max-width: 380px; padding: 24px;">
        <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 20px; text-align: center; color: var(--text-main);">Connect to Database</h3>
        
        <?php if (!empty($login_error)): ?>
            <div style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 10px; border-radius: 4px; margin-bottom: 16px; font-size: 12px;">
                <?= htmlspecialchars($login_error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" style="display: flex; flex-direction: column; gap: 12px;">
            <div>
                <label style="font-size: 11px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px;">Host</label>
                <input class="sq-input" type="text" name="db_host" placeholder="e.g. localhost / IP" required>
            </div>
            <div>
                <label style="font-size: 11px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px;">Port</label>
                <input class="sq-input" type="text" name="db_port" value="3306" required>
            </div>
            <div>
                <label style="font-size: 11px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px;">Username</label>
                <input class="sq-input" type="text" name="db_user" placeholder="Username" required>
            </div>
            <div>
                <label style="font-size: 11px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px;">Password</label>
                <input class="sq-input" type="password" name="db_pass" placeholder="Password">
            </div>

            <?php if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 3): ?>
            <div style="background: var(--bg-subtle); padding: 10px; border-radius: 4px; border: 1px solid var(--border-color);">
                <label style="font-size: 11px; font-weight: 600; color: var(--danger); display: block; margin-bottom: 4px;">⚠ <?= $captcha_text ?></label>
                <input class="sq-input" type="number" name="captcha_answer" placeholder="Hasil penjumlahannya..." required>
            </div>
            <?php endif; ?>

            <div style="margin-top: 8px;">
                <button type="submit" name="do_login" class="sq-btn sq-btn-primary" style="width: 100%; justify-content: center; height: 38px;">Connect</button>
            </div>
        </form>
    </div>
</div>