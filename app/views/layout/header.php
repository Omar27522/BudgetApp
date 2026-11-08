<?php
// views/layout/header.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personal Finance App</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <header class="app-header">
        <div class="container header-inner">
            <div class="brand-title">
                <span>Personal Finance</span>
                <span class="brand-badge"><?= htmlspecialchars($currentMonth['label']) ?></span>
            </div>

            <!-- Month & Year Switcher -->
            <div class="month-selector-form">
                <div class="month-nav-group">
                    <!-- Prev Month Button -->
                    <?php if ($prevChronMonth): ?>
                        <a href="index.php?month_id=<?= $prevChronMonth['id'] ?>&tab=<?= htmlspecialchars($activeTab) ?>" 
                           class="btn-nav-month" title="Previous month: <?= htmlspecialchars($prevChronMonth['label']) ?>">
                            &lsaquo;
                        </a>
                    <?php else: ?>
                        <span class="btn-nav-month is-disabled" title="No earlier months">&lsaquo;</span>
                    <?php endif; ?>

                    <form method="GET" action="index.php" style="display:inline-flex; align-items:center; gap:6px;">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">

                        <label for="header_year" style="font-size:0.82rem; font-weight:700; color:var(--text-muted);">Year:</label>
                        <select name="year" id="header_year" class="month-select year-select" onchange="var m=document.getElementById('header_month'); if(m) m.disabled=true; this.form.submit();">
                            <?php foreach ($availableYears as $y): ?>
                                <option value="<?= $y ?>" <?= (int)$y === $activeYear ? 'selected' : '' ?>>
                                    <?= $y ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="header_month" style="font-size:0.82rem; font-weight:700; color:var(--text-muted); margin-left:2px;">Month:</label>
                        <select name="month_id" id="header_month" class="month-select" onchange="this.form.submit()">
                            <?php foreach ($monthsForActiveYear as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= (int)$m['id'] === $selectedMonthId ? 'selected' : '' ?>>
                                    <?= Format::monthNames()[(int)$m['month']] ?? $m['label'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <noscript><button type="submit" class="btn btn-secondary btn-sm">Switch</button></noscript>
                    </form>

                    <!-- Next Month Button -->
                    <?php if ($nextChronMonth): ?>
                        <a href="index.php?month_id=<?= $nextChronMonth['id'] ?>&tab=<?= htmlspecialchars($activeTab) ?>" 
                           class="btn-nav-month" title="Next month: <?= htmlspecialchars($nextChronMonth['label']) ?>">
                            &rsaquo;
                        </a>
                    <?php else: ?>
                        <span class="btn-nav-month is-disabled" title="No later months">&rsaquo;</span>
                    <?php endif; ?>
                </div>

                <?php if (!$showNewMonthForm): ?>
                    <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=<?= htmlspecialchars($activeTab) ?>&view=new_month" class="btn btn-primary btn-sm">+ New Month</a>
                <?php else: ?>
                    <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=<?= htmlspecialchars($activeTab) ?>" class="btn btn-secondary btn-sm">Close</a>
                <?php endif; ?>

                <a href="backup.php" class="btn btn-secondary btn-sm" title="Download secure backup (SQLite Database + Monthly CSVs)" style="display:inline-flex; align-items:center; gap:4px; background:#fff; border-color:var(--border-color); color:var(--text-main);">
                    📦 Backup
                </a>

                <?php if (!empty($_SESSION['authenticated'])): ?>
                    <a href="logout.php" class="btn btn-secondary btn-sm" title="Sign out (<?= htmlspecialchars($_SESSION['username'] ?? '') ?>)" style="color:#ef4444; border-color:#fecaca; background:#fff;">
                        🚪 Logout
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container">

        <!-- Notification Banner -->
        <?php if (!empty($statusMessage)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($statusMessage) ?>
            </div>
        <?php endif; ?>

        <!-- New Month Form -->
        <?php if ($showNewMonthForm): 
            $latestMonth = $allMonths[0] ?? ['year' => (int)date('Y'), 'month' => (int)date('n')];
            $nextM = (int)$latestMonth['month'] + 1;
            $nextY = (int)$latestMonth['year'];
            if ($nextM > 12) {
                $nextM = 1;
                $nextY++;
            }
            $monthNames = Format::monthNames();
            $suggestedName = $monthNames[$nextM] ?? "Month {$nextM}";
            $suggestedLabel = "{$suggestedName} {$nextY}";
        ?>
            <div class="card" style="border: 2px solid var(--primary); margin-bottom: 24px;">
                <div class="card-header">
                    <span class="card-title">⚡ Quick Start a New Month / Year</span>
                    <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=<?= htmlspecialchars($activeTab) ?>" class="btn btn-secondary btn-sm">&times; Cancel</a>
                </div>
                <div class="card-body">
                    <!-- 1-Click Fast Recommendation -->
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px 16px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <div>
                            <span style="font-size:0.9rem; font-weight:700; color:#15803d;">Next chronological month:</span>
                            <span style="font-size:1.05rem; font-weight:800; color:#166534; margin-left:6px;"><?= htmlspecialchars($suggestedLabel) ?></span>
                            <?php if ($nextM === 1): ?>
                                <span class="badge" style="background:#dbeafe; color:#1e40af; margin-left:6px; font-weight:700;">🎉 New Year!</span>
                            <?php endif; ?>
                        </div>
                        <form method="POST" action="index.php" style="margin:0;">
                            <input type="hidden" name="action" value="create_month">
                            <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
                            <input type="hidden" name="year" value="<?= $nextY ?>">
                            <input type="hidden" name="month" value="<?= $nextM ?>">
                            <input type="hidden" name="copy_from_id" value="<?= $selectedMonthId ?>">
                            <input type="hidden" name="copy_income" value="1">
                            <input type="hidden" name="copy_categories" value="1">
                            <button type="submit" class="btn btn-primary btn-sm" style="font-weight:700; box-shadow:0 2px 4px rgba(37,99,235,0.2);">
                                ⚡ 1-Click Start <?= htmlspecialchars($suggestedLabel) ?>
                            </button>
                        </form>
                    </div>

                    <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:14px;">
                        Or choose any specific year and month below. Carries over non-variable payments, recurring income, and budget categories ready for the new month.
                    </p>

                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="create_month">
                        <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">

                        <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                            <!-- 1. YEAR OPTION FIRST -->
                            <div class="form-group">
                                <label class="form-label" for="new_year">1. Select Year</label>
                                <select name="year" id="new_year" class="form-control" required>
                                    <?php 
                                    $minY = min(array_merge($availableYears, [(int)date('Y')]));
                                    $maxY = max(array_merge($availableYears, [(int)date('Y') + 3, $nextY]));
                                    for ($y = $minY; $y <= $maxY; $y++): ?>
                                        <option value="<?= $y ?>" <?= $y === $nextY ? 'selected' : '' ?>>
                                            <?= $y ?> <?= ($y === (int)date('Y')) ? '(Current Year)' : ($y === (int)date('Y') + 1 ? '(Next Year)' : '') ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <!-- 2. MONTH OPTION SECOND -->
                            <div class="form-group">
                                <label class="form-label" for="new_month">2. Select Month</label>
                                <select name="month" id="new_month" class="form-control" required>
                                    <?php foreach ($monthNames as $num => $name): ?>
                                        <option value="<?= $num ?>" <?= $num === $nextM ? 'selected' : '' ?>>
                                            <?= $name ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- 3. TEMPLATE -->
                            <div class="form-group">
                                <label class="form-label" for="copy_from_id">3. Copy templates from:</label>
                                <select name="copy_from_id" id="copy_from_id" class="form-control">
                                    <?php foreach ($allMonths as $m): ?>
                                        <option value="<?= $m['id'] ?>" <?= (int)$m['id'] === $selectedMonthId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($m['label']) ?> (Recommended)
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="">-- Start with empty lists --</option>
                                </select>
                            </div>
                        </div>

                        <div style="margin-top: 10px; margin-bottom: 16px; display:flex; flex-wrap:wrap; gap:16px;">
                            <label style="font-size:0.85rem; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                                <input type="checkbox" name="copy_income" value="1" checked>
                                Copy recurring income sources
                            </label>
                            <label style="font-size:0.85rem; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                                <input type="checkbox" name="copy_categories" value="1" checked>
                                Copy budget categories & estimates
                            </label>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" style="font-weight:700;">Create & Setup Month</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

