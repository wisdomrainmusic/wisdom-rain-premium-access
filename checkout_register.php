<?php
if ( ! defined('ABSPATH') ) exit;

// Eğer kullanıcı zaten giriş yaptıysa formu gösterme
if ( is_user_logged_in() ) {
    return;
}

// Form gönderildiyse kayıt işlemini başlat
if ( isset($_POST['wr_reg_submit']) ) {

    // E-posta ve şifreyi güvenli biçimde al
    $email = isset($_POST['wr_reg_email']) ? sanitize_email($_POST['wr_reg_email']) : '';
    $pass  = isset($_POST['wr_reg_password']) ? sanitize_text_field($_POST['wr_reg_password']) : '';

    // Alanlar dolu mu ve e-posta zaten kayıtlı mı kontrol et
    if ( ! empty($email) && ! empty($pass) && ! email_exists($email) ) {

        // Kullanıcıyı oluştur
        $user_id = wp_create_user($email, $pass, $email);

        if ( ! is_wp_error($user_id) ) {
            // Kullanıcıyı otomatik olarak giriş yapmış hale getir
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);

            // WooCommerce checkout sayfasına yönlendir
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        } else {
            // Hata varsa kullanıcıya göster
            wc_add_notice(__('Registration failed. Please try again.', 'wrpa'), 'error');
        }

    } else {
        // E-posta zaten kayıtlıysa veya alanlar boşsa
        wc_add_notice(__('This email is already registered or fields are missing.', 'wrpa'), 'error');
    }
}
?>

<div class="wr-quick-register" style="margin-bottom:40px; padding:20px; border:1px solid #ddd; border-radius:10px;">
    <h3 style="margin-bottom:10px;">Create an Account</h3>
    <p style="margin-bottom:20px;">Don’t have an account yet? Register below to access premium content.</p>

    <form method="post" class="wr-register-form" style="max-width:400px;">
        <p>
            <label for="wr_reg_email"><strong>Email address</strong></label><br>
            <input type="email" id="wr_reg_email" name="wr_reg_email" required style="width:100%; padding:8px; margin-top:5px;">
        </p>

        <p>
            <label for="wr_reg_password"><strong>Password</strong></label><br>
            <input type="password" id="wr_reg_password" name="wr_reg_password" required style="width:100%; padding:8px; margin-top:5px;">
        </p>

        <p style="margin-top:15px;">
            <input type="submit" name="wr_reg_submit" class="button alt" value="Register" style="background:#C1252D; border:none; padding:10px 20px; color:#fff; cursor:pointer;">
        </p>
    </form>
</div>
