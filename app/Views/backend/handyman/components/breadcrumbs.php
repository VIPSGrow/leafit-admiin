<?php if (!empty($breadcrumbs)): ?>
    <div class="section-header-breadcrumb">
        <?php foreach ($breadcrumbs as $i => $crumb): ?>
            <?php $isLast = ($i === count($breadcrumbs) - 1); ?>
            <?php if (!$isLast && !empty($crumb['url'])): ?>
                <div class="breadcrumb-item active">
                    <a href="<?= $crumb['url'] ?>">
                        <?php if (!empty($crumb['icon'])): ?>
                            <i class="<?= esc($crumb['icon']) ?> text-primary"></i>
                        <?php endif; ?>
                        <?= $crumb['label'] ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="breadcrumb-item">
                    <?php if (!empty($crumb['icon'])): ?>
                        <i class="<?= esc($crumb['icon']) ?> text-primary"></i>
                    <?php endif; ?>
                    <?= $crumb['label'] ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
