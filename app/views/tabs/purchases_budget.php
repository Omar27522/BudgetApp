<?php
// views/tabs/purchases_budget.php - Tab 2: Purchases & Variable Categories
?>
<div class="content-grid">

    <!-- LEFT: Daily Purchases / Receipts Log -->
    <section>
        <!-- Log New Purchase Form (Placed on top for fast entry) -->
        <div class="form-card form-card-top">
            <div class="form-title">+ Log New Purchase / Receipt</div>
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="add_purchase">
                <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                <input type="hidden" name="tab" value="purchases">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="purchase_date">Date</label>
                        <input type="date" name="purchase_date" id="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label" for="item">Item / Description</label>
                        <input type="text" name="item" id="item" class="form-control" placeholder="e.g. Candy, Gas station, Offering, Groceries" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="place">Place / Store</label>
                        <input type="text" name="place" id="place" class="form-control" placeholder="e.g. Arco, Sacred Heart Church">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="pur_category_id">Category</label>
                        <select name="category_id" id="pur_category_id" class="form-control">
                            <option value="">-- Choose Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="amount">Amount ($)</label>
                        <input type="number" step="0.01" name="amount" id="amount" class="form-control" placeholder="0.00" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">+ Record Expense</button>
                </div>
            </form>
        </div>

        <!-- Daily Purchases / Expenses Table -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    Daily Purchases / Expenses
                    <span class="card-badge"><?= count($purchases) ?> logged</span>
                </span>
            </div>

            <div class="card-body" style="padding:0;">
                <?php if (empty($purchases)): ?>
                    <div class="empty-state">
                        <p>No daily purchases logged for <strong><?= htmlspecialchars($currentMonth['label']) ?></strong> yet.</p>
                        <p style="font-size:0.8rem;">Log everyday transactions (candy, gas, church offering, groceries) using the form above.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="stack-table purchases-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Item</th>
                                    <th>Place / Vendor</th>
                                    <th>Category</th>
                                    <th style="text-align:right;">Amount</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($purchases as $p): 
                                    $isEditingPur = ($editPurchaseId && (int)$p['id'] === $editPurchaseId);
                                ?>
                                    <?php if ($isEditingPur): ?>
                                        <!-- INLINE EDIT ROW -->
                                        <tr style="background:#fffbeb; border-top:2px solid var(--warning); border-bottom:2px solid var(--warning);" id="pur-<?= $p['id'] ?>">
                                            <td colspan="6" style="padding:16px;">
                                                <form method="POST" action="index.php#pur-<?= $p['id'] ?>">
                                                    <input type="hidden" name="action" value="update_purchase">
                                                    <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                    <input type="hidden" name="tab" value="purchases">
                                                    <input type="hidden" name="purchase_id" value="<?= $p['id'] ?>">

                                                    <div style="font-weight:700; margin-bottom:12px; color:#92400e; font-size:0.95rem; display:flex; justify-content:space-between; align-items:center;">
                                                        <span>✏️ Editing Purchase: <?= htmlspecialchars($p['item']) ?></span>
                                                        <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=purchases#pur-<?= $p['id'] ?>" class="btn btn-secondary btn-sm">&times; Cancel</a>
                                                    </div>

                                                    <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));">
                                                        <div class="form-group">
                                                            <label class="form-label">Date</label>
                                                            <input type="date" name="purchase_date" class="form-control" value="<?= htmlspecialchars($p['purchase_date']) ?>" required>
                                                        </div>
                                                        <div class="form-group" style="grid-column: span 2;">
                                                            <label class="form-label">Item / Description</label>
                                                            <input type="text" name="item" class="form-control" value="<?= htmlspecialchars($p['item']) ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="form-label">Place / Store</label>
                                                            <input type="text" name="place" class="form-control" value="<?= htmlspecialchars($p['place'] ?? '') ?>">
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="form-label">Category</label>
                                                            <select name="category_id" class="form-control">
                                                                <option value="">-- Uncategorized --</option>
                                                                <?php foreach ($categories as $cat): ?>
                                                                    <option value="<?= $cat['id'] ?>" <?= (int)$p['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($cat['name']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="form-label">Amount ($)</label>
                                                            <input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars($p['amount']) ?>" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-actions" style="margin-top:12px;">
                                                        <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=purchases#pur-<?= $p['id'] ?>" class="btn btn-secondary">Cancel</a>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <!-- STANDARD ROW -->
                                        <tr id="pur-<?= $p['id'] ?>">
                                            <td style="font-size:0.82rem; white-space:nowrap; color:var(--text-muted);">
                                                <?= htmlspecialchars(Format::shortDate($p['purchase_date'])) ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($p['item']) ?></strong>
                                                <?php if (!empty($p['notes'])): ?>
                                                    <span style="font-size:0.72rem; color:var(--text-muted); display:block;"><?= htmlspecialchars($p['notes']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size:0.85rem; color:var(--text-muted);">
                                                <?= !empty($p['place']) ? htmlspecialchars($p['place']) : '&mdash;' ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($p['category_name'])): ?>
                                                    <span class="badge" style="background:#f3f4f6; color:var(--text-main); font-weight:600;">
                                                        <?= htmlspecialchars($p['category_name']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="font-size:0.75rem; color:var(--text-muted);">Uncategorized</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="amount" style="text-align:right;">
                                                <?= Format::money((float)$p['amount']) ?>
                                            </td>
                                            <td style="text-align:right; white-space:nowrap;">
                                                <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=purchases&edit_purchase=<?= $p['id'] ?>#pur-<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>

                                                <form method="POST" action="index.php" style="display:inline;" onsubmit="return confirm('Delete this purchase?');">
                                                    <input type="hidden" name="action" value="delete_purchase">
                                                    <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                    <input type="hidden" name="tab" value="purchases">
                                                    <input type="hidden" name="purchase_id" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">&times;</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background:#f9fafb; font-weight:700;">
                                    <td colspan="4">Total Daily Expenses:</td>
                                    <td class="amount" style="text-align:right;"><?= Format::money($totalPurchases) ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- RIGHT: Variable Budget Categories -->
    <section>
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    Variable Budget Categories
                    <span class="card-badge"><?= count($categories) ?> categories</span>
                </span>
                <?php if (empty($categories)): ?>
                    <form method="POST" action="index.php" style="display:inline;">
                        <input type="hidden" name="action" value="seed_standard_categories">
                        <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                        <input type="hidden" name="tab" value="purchases">
                        <button type="submit" class="btn btn-secondary btn-sm">+ Standard Categories</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card-body" style="padding:0;">
                <?php if (empty($categories)): ?>
                    <div class="empty-state">
                        <p>No budget categories set up for <strong><?= htmlspecialchars($currentMonth['label']) ?></strong> yet.</p>
                        <p style="font-size:0.8rem;">Click "+ Standard Categories" above or add custom categories below.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="stack-table categories-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th style="text-align:right;">Budget</th>
                                    <th style="text-align:right;">Spent</th>
                                    <th style="text-align:right;">Remaining</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): 
                                    $isEditingCat = ($editCatId && (int)$cat['id'] === $editCatId);
                                    $est = (float)$cat['estimated_amount'];
                                    $spent = (float)$cat['actual_spent'];
                                    $rem = $est - $spent;
                                    $pct = $est > 0 ? min(100, round(($spent / $est) * 100)) : ($spent > 0 ? 100 : 0);
                                    $barClass = 'progress-good';
                                    if ($pct >= 100) { $barClass = 'progress-danger'; }
                                    elseif ($pct >= 80) { $barClass = 'progress-warning'; }
                                ?>
                                    <?php if ($isEditingCat): ?>
                                        <!-- INLINE EDIT ROW -->
                                        <tr style="background:#fffbeb; border-top:2px solid var(--warning); border-bottom:2px solid var(--warning);" id="cat-<?= $cat['id'] ?>">
                                            <td colspan="5" style="padding:16px;">
                                                <form method="POST" action="index.php#cat-<?= $cat['id'] ?>">
                                                    <input type="hidden" name="action" value="update_category">
                                                    <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                    <input type="hidden" name="tab" value="purchases">
                                                    <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">

                                                    <div style="font-weight:700; margin-bottom:12px; color:#92400e; font-size:0.95rem; display:flex; justify-content:space-between; align-items:center;">
                                                        <span>✏️ Editing Category: <?= htmlspecialchars($cat['name']) ?></span>
                                                        <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=purchases#cat-<?= $cat['id'] ?>" class="btn btn-secondary btn-sm">&times; Cancel</a>
                                                    </div>

                                                    <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));">
                                                        <div class="form-group" style="grid-column: span 2;">
                                                            <label class="form-label">Category Name</label>
                                                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($cat['name']) ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="form-label">Budget Estimate ($)</label>
                                                            <input type="number" step="0.01" name="estimated_amount" class="form-control" value="<?= htmlspecialchars($cat['estimated_amount']) ?>" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-actions" style="margin-top:12px;">
                                                        <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=purchases#cat-<?= $cat['id'] ?>" class="btn btn-secondary">Cancel</a>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <!-- STANDARD ROW -->
                                        <tr id="cat-<?= $cat['id'] ?>">
                                            <td>
                                                <strong><?= htmlspecialchars($cat['name']) ?></strong>
                                                <span style="font-size:0.75rem; color:var(--text-muted); display:block;">
                                                    <?= (int)$cat['purchase_count'] ?> purchase<?= (int)$cat['purchase_count'] === 1 ? '' : 's' ?>
                                                </span>
                                                <div class="progress-container">
                                                    <div class="progress-fill <?= $barClass ?>" style="width: <?= $pct ?>%;"></div>
                                                </div>
                                            </td>
                                            <td class="amount" style="text-align:right;">
                                                <?= Format::money($est) ?>
                                            </td>
                                            <td class="amount" style="text-align:right;">
                                                <?= Format::money($spent) ?>
                                            </td>
                                            <td class="amount" style="text-align:right; color:<?= $rem >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                                                <?= Format::money($rem) ?>
                                            </td>
                                            <td style="text-align:right; white-space:nowrap;">
                                                <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=purchases&edit_cat=<?= $cat['id'] ?>#cat-<?= $cat['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>

                                                <form method="POST" action="index.php" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                                                    <input type="hidden" name="action" value="delete_category">
                                                    <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                    <input type="hidden" name="tab" value="purchases">
                                                    <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">&times;</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background:#f9fafb; font-weight:700;">
                                    <td>Total Variable Budget:</td>
                                    <td class="amount" style="text-align:right;"><?= Format::money($catSummary['total_estimated']) ?></td>
                                    <td class="amount" style="text-align:right;"><?= Format::money($catSummary['total_actual']) ?></td>
                                    <td class="amount" style="text-align:right; color:<?= $catSummary['remaining'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                                        <?= Format::money($catSummary['remaining']) ?>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add New Category Form -->
        <div class="form-card">
            <div class="form-title">+ Add Variable Category</div>
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="add_category">
                <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                <input type="hidden" name="tab" value="purchases">

                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label" for="cat_name">Category Name</label>
                        <input type="text" name="name" id="cat_name" class="form-control" placeholder="e.g. Gas, Groceries, Dining out" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cat_estimated">Budget Estimate ($)</label>
                        <input type="number" step="0.01" name="estimated_amount" id="cat_estimated" class="form-control" placeholder="0.00" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">+ Add Category</button>
                </div>
            </form>
        </div>
    </section>

</div>

