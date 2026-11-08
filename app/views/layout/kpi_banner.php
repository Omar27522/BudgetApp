<?php
// views/layout/kpi_banner.php
?>
<section class="kpi-grid">
    <div class="kpi-card">
        <span class="kpi-title">Income Received</span>
        <span class="kpi-value kpi-positive"><?= Format::money($incomeSummary['total_actual']) ?></span>
        <span class="kpi-sub">
            Expected: <strong><?= Format::money($incomeSummary['total_estimated']) ?></strong>
            <?php $incPending = $incomeSummary['total_estimated'] - $incomeSummary['total_actual']; ?>
            <?php if ($incPending > 0): ?>
            &bull; <span style="color:var(--warning); font-weight:600;"><?= Format::money($incPending) ?> pending</span>
            <?php else: ?>
            &bull; <span style="color:var(--success); font-weight:600;">All received &#10003;</span>
            <?php endif; ?>
        </span>
    </div>

    <div class="kpi-card">
        <span class="kpi-title">Bills Paid (Cash Out)</span>
        <span class="kpi-value kpi-positive"><?= Format::money($billsSummary['total_paid']) ?></span>
        <span class="kpi-sub">
            <?php if ($billsSummary['total_remaining'] > 0): ?>
            Unpaid: <strong
                style="color:var(--warning);"><?= Format::money($billsSummary['total_remaining']) ?></strong>
            (<?= $billsSummary['unpaid_count'] ?> left)
            <?php else: ?>
            <strong style="color:var(--success);">All <?= $billsSummary['total_count'] ?> bills paid &#10003;</strong>
            <?php endif; ?>
            &bull; Total: <?= Format::money($billsSummary['total_estimated']) ?>
        </span>
    </div>

    <div class="kpi-card">
        <span class="kpi-title">Variable Budget Left</span>
        <span class="kpi-value <?= $catSummary['remaining'] >= 0 ? 'kpi-positive' : 'kpi-negative' ?>">
            <?= Format::money($catSummary['remaining']) ?>
        </span>
        <span class="kpi-sub">
            Spent: <strong><?= Format::money($totalPurchases) ?></strong> of
            <?= Format::money($catSummary['total_estimated']) ?> budgeted
        </span>
    </div>

    <div class="kpi-card">
        <span class="kpi-title">Estimated Net Left</span>
        <span class="kpi-value <?= $netEstimated >= 0 ? 'kpi-primary' : 'kpi-negative' ?>">
            <?= Format::money($netEstimated) ?>
        </span>
        <span class="kpi-sub">
            In <?= Format::money($incomeSummary['total_estimated']) ?> &minus; Bills
            <?= Format::money($billsSummary['total_estimated']) ?> &minus; Budget
            <?= Format::money($catSummary['total_estimated']) ?>
        </span>
    </div>
</section>

<!-- Navigation Tabs -->
<nav class="nav-tabs">
    <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=bills"
        class="nav-tab <?= $activeTab === 'bills' ? 'active' : '' ?>">
        📋 Monthly Bills & Income ("Cash Out")
    </a>
    <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=purchases"
        class="nav-tab <?= $activeTab === 'purchases' ? 'active' : '' ?>">
        🛒 Purchases & Variable Budget
    </a>
    <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=gas"
        class="nav-tab <?= $activeTab === 'gas' ? 'active' : '' ?>">
        ⛽ Gas & Fuel Tracker
    </a>
</nav>