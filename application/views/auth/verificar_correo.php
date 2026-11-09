<?php
$page_title = 'Confirm email';
$this->load->view('auth/_auth_top', ['page_title' => $page_title]);
?>

<div class="d-flex flex-column-fluid flex-lg-row-auto justify-content-center justify-content-lg-end p-12">
    <div class="bg-body d-flex flex-column flex-center rounded-4 w-md-600px p-10">
        <div class="d-flex flex-center flex-column align-items-stretch w-md-400px text-center">
            <h1 class="text-gray-900 fw-bolder mb-5">Confirm your email address</h1>
            <?php if ($token_status === 'valid'): ?>
            <p class="text-muted mb-8">Confirm that this email address belongs to you to activate your account.</p>
            <form class="w-100" method="POST" action="<?php echo base_url('verificar-correo/confirmar'); ?>">
                <input type="hidden" name="registration_csrf" value="<?php echo htmlspecialchars($registration_csrf, ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>" />
                <button type="submit" class="btn btn-primary w-100 mb-5">Confirm and activate my account</button>
            </form>
            <?php elseif ($token_status === 'expired'): ?>
            <div class="alert alert-warning">The link has expired. Request a new one from the verification screen.</div>
            <a href="<?php echo base_url('registro/resultado'); ?>" class="btn btn-light-primary">Request new link</a>
            <?php else: ?>
            <div class="alert alert-danger">The link is invalid or has already been used.</div>
            <a href="<?php echo base_url('registro/resultado'); ?>" class="btn btn-light-primary">Continue</a>
            <?php endif; ?>
            <a href="<?php echo base_url('login'); ?>" class="link-primary mt-5">Back to sign in</a>
        </div>
        <?php $this->load->view('auth/_auth_bottom'); ?>
    </div>
</div>
