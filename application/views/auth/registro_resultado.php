<?php
$page_title = 'Account verification';
$this->load->view('auth/_auth_top', ['page_title' => $page_title]);
?>

<div class="d-flex flex-column-fluid flex-lg-row-auto justify-content-center justify-content-lg-end p-12">
    <div class="bg-body d-flex flex-column flex-center rounded-4 w-md-600px p-10">
        <div class="d-flex flex-center flex-column align-items-stretch w-md-400px text-center">
            <h1 class="text-gray-900 fw-bolder mb-5">Account verification</h1>
            <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <p class="text-muted">If your account is pending, you can request a new verification link.</p>
            <form class="w-100 mb-5" method="POST" action="<?php echo base_url('registro/reenviar-verificacion'); ?>">
                <input type="hidden" name="registration_csrf" value="<?php echo htmlspecialchars($registration_csrf, ENT_QUOTES, 'UTF-8'); ?>" />
                <div class="fv-row mb-4">
                    <input type="email" name="usu_correo" class="form-control bg-transparent" placeholder="Email address" autocomplete="email" required />
                </div>
                <button type="submit" class="btn btn-light-primary w-100">Resend verification</button>
            </form>
            <a href="<?php echo base_url('login'); ?>" class="btn btn-primary">Back to sign in</a>
        </div>
        <?php $this->load->view('auth/_auth_bottom'); ?>
    </div>
</div>
