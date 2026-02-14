<div class="activity-item">
    <div class="d-flex justify-content-between align-items-start mb-1">
        <div>
            <span class="text-primary fw-bold small">
                <?= htmlspecialchars($activity['fullname'] ?: $activity['username'] ?: 'Système') ?>
            </span>
            <span class="text-muted extra-small ms-2">
                @
                <?= htmlspecialchars($activity['username'] ?: 'system') ?>
            </span>
        </div>
        <span class="text-muted extra-small">
            <?= date('d/m/Y H:i', strtotime($activity['created_at'])) ?>
        </span>
    </div>
    <div class="text-white small">
        <?= htmlspecialchars($activity['action']) ?>
    </div>
    <?php if ($activity['details']): ?>
        <div class="text-muted extra-small mt-1">
            <?= htmlspecialchars($activity['details']) ?>
        </div>
    <?php endif; ?>
    <?php if ($activity['ip_address']): ?>
        <div class="text-muted extra-small mt-1">
            <i class="fa-solid fa-location-dot me-1"></i>
            <?= htmlspecialchars($activity['ip_address']) ?>
        </div>
    <?php endif; ?>
</div>