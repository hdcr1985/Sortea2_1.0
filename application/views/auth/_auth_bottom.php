<?php
$auth_layout = $auth_layout ?? 'branded';
?>
<!--begin::Footer de auth-->
<div class="d-flex flex-stack justify-content-between align-items-center mt-6 fs-7 text-gray-500">
    <div>
        <a href="<?php echo htmlspecialchars($theme_settings['footer_support_url'] ?? '#'); ?>" class="text-gray-500 text-hover-primary" target="_blank">
            <?php echo htmlspecialchars($theme_settings['footer_support_label'] ?? 'Support'); ?>
        </a>
    </div>
    <div>
        <span><?php echo htmlspecialchars($theme_settings['footer_text'] ?? ('© ' . date('Y') . ' ' . ($app_name ?? 'System'))); ?></span>
    </div>
</div>
<!--end::Footer de auth-->

<?php if ($auth_layout === 'classic'): ?>
    </div><!--end::mw-425px-->
</div><!--end::d-flex flex-column flex-center-->
</div><!--end::kt_app_root-->
<?php else: ?>
        </div><!--end::Content-->
    </div><!--end::Wrapper-->
</div><!--end::Body-->

</div><!--end::d-flex flex-column flex-lg-row-->
</div><!--end::kt_app_root-->
<?php endif; ?>

<!--begin::Javascript-->
<script>var hostUrl = "<?php echo base_url('assets/'); ?>";</script>
<script src="<?php echo base_url('assets/plugins/global/plugins.bundle.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/scripts.bundle.js'); ?>"></script>
<?php if (!empty($auth_script)): ?>
<script src="<?php echo base_url('assets/js/' . $auth_script); ?>"></script>
<?php endif; ?>
<!--end::Javascript-->

</body>
</html>
