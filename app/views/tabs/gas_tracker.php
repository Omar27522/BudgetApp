<?php
// views/tabs/gas_tracker.php - Tab 3: Fuel & Mileage Tracker
?>
<div class="content-grid">

    <!-- LEFT: Fast Entry Form on Top + Gas Fill-ups Log -->
    <section>
        <!-- Log New Gas Fill-up Form (Placed on top for fast mobile entry) -->
        <div class="form-card form-card-top">
            <div class="form-title">+ Log Gas Fill-up</div>
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="add_gas_fillup">
                <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                <input type="hidden" name="tab" value="gas">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="fill_date">Date</label>
                        <input type="date" name="fill_date" id="fill_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label" for="station">Gas Station</label>
                        <input type="text" name="station" id="station" class="form-control" placeholder="e.g. Arco, Chevron, Costco, Shell" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="gas_amount">Total Amount ($)</label>
                        <input type="number" step="0.01" name="amount" id="gas_amount" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="gallons">Gallons</label>
                        <input type="number" step="0.001" name="gallons" id="gallons" class="form-control" placeholder="e.g. 14.5" required>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label" for="odometer">Odometer (opt.)</label>
                        <input type="number" step="0.1" name="odometer" id="odometer" class="form-control" placeholder="e.g. 48750 (optional)">
                    </div>
                </div>

                <div style="margin-top: 10px; margin-bottom: 14px;">
                    <label style="font-size:0.85rem; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="checkbox" name="sync_to_purchases" value="1" checked>
                        Also record in Purchases under <strong>Gas</strong> budget category
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">+ Record Fill-up</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    ⛽ Gas Fill-ups Log
                    <span class="card-badge"><?= count($fillups) ?> entries</span>
                </span>
            </div>

            <div class="card-body" style="padding:0;">
                <?php if (empty($fillups)): ?>
                    <div class="empty-state">
                        <p>No gas fill-ups recorded for <strong><?= htmlspecialchars($currentMonth['label']) ?></strong> yet.</p>
                        <p style="font-size:0.8rem;">Log your gas station fill-ups using the form above to track fuel costs.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="stack-table gas-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Station</th>
                                    <th style="text-align:right;">Amount</th>
                                    <th style="text-align:right;">Gallons</th>
                                    <th style="text-align:right;">Price/Gal</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fillups as $f): 
                                    $isEditingGas = ($editGasId && (int)$f['id'] === $editGasId);
                                ?>
                                    <?php if ($isEditingGas): ?>
                                        <!-- INLINE EDIT ROW -->
                                        <tr style="background:#fffbeb; border-top:2px solid var(--warning); border-bottom:2px solid var(--warning);" id="gas-<?= $f['id'] ?>">
                                            <td colspan="6" style="padding:16px;">
                                                <form method="POST" action="index.php#gas-<?= $f['id'] ?>">
                                                    <input type="hidden" name="action" value="update_gas_fillup">
                                                    <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                    <input type="hidden" name="tab" value="gas">
                                                    <input type="hidden" name="gas_id" value="<?= $f['id'] ?>">

                                                    <div style="font-weight:700; margin-bottom:12px; color:#92400e; font-size:0.95rem; display:flex; justify-content:space-between; align-items:center;">
                                                        <span>✏️ Editing Gas Fill-up: <?= htmlspecialchars($f['station']) ?></span>
                                                        <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=gas#gas-<?= $f['id'] ?>" class="btn btn-secondary btn-sm">&times; Cancel</a>
                                                    </div>

                                                    <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));">
                                                        <div class="form-group">
                                                            <label class="form-label">Date</label>
                                                            <input type="date" name="fill_date" class="form-control" value="<?= htmlspecialchars($f['fill_date']) ?>" required>
                                                        </div>
                                                        <div class="form-group" style="grid-column: span 2;">
                                                            <label class="form-label">Station</label>
                                                            <input type="text" name="station" class="form-control" value="<?= htmlspecialchars($f['station']) ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="form-label">Fuel Type</label>
                                                            <input type="text" name="fuel_type" class="form-control" value="<?= htmlspecialchars($f['fuel_type'] ?? 'Regular') ?>">
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="form-label">Amount ($)</label>
                                                            <input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars($f['amount']) ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="form-label">Gallons</label>
                                                            <input type="number" step="0.001" name="gallons" class="form-control" value="<?= htmlspecialchars($f['gallons']) ?>" required>
                                                        </div>
                                                        <div class="form-group" style="grid-column: span 2;">
                                                            <label class="form-label">Odometer (opt.)</label>
                                                            <input type="number" step="0.1" name="odometer" class="form-control" value="<?= htmlspecialchars($f['odometer'] ?? '') ?>" placeholder="e.g. 48750">
                                                        </div>
                                                    </div>

                                                    <div class="form-actions" style="margin-top:12px;">
                                                        <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=gas#gas-<?= $f['id'] ?>" class="btn btn-secondary">Cancel</a>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <!-- STANDARD ROW -->
                                        <tr id="gas-<?= $f['id'] ?>">
                                            <td>
                                                <span class="badge" style="background:#f1f5f9; color:#475569; font-weight:700;">
                                                    📅 <?= htmlspecialchars(Format::shortDate($f['fill_date'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong class="item-name"><?= htmlspecialchars($f['station']) ?></strong>
                                                <?php if (!empty($f['fuel_type'])): ?>
                                                    <span style="font-size:0.75rem; color:var(--text-muted); display:block;"><?= htmlspecialchars($f['fuel_type']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($f['odometer']) && (float)$f['odometer'] > 0): ?>
                                                    <span style="font-size:0.72rem; color:var(--text-muted); display:block;"><?= number_format((float)$f['odometer'], 0) ?> mi</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="amount" style="text-align:right;">
                                                <span style="color:#059669; font-weight:700;"><?= Format::money((float)$f['amount']) ?></span>
                                            </td>
                                            <td class="amount" style="text-align:right;">
                                                <?= number_format((float)$f['gallons'], 2) ?> gal
                                            </td>
                                            <td class="amount" style="text-align:right; color:var(--text-muted);">
                                                $<?= number_format((float)$f['price_per_gal'], 3) ?>
                                            </td>
                                            <td style="text-align:right; white-space:nowrap;">
                                                <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=gas&edit_gas=<?= $f['id'] ?>#gas-<?= $f['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>

                                                <form method="POST" action="index.php" style="display:inline;" onsubmit="return confirm('Delete this gas entry?');">
                                                    <input type="hidden" name="action" value="delete_gas_fillup">
                                                    <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                    <input type="hidden" name="tab" value="gas">
                                                    <input type="hidden" name="gas_id" value="<?= $f['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">&times;</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background:#f9fafb; font-weight:700;">
                                    <td colspan="2">Total Fuel Spent:</td>
                                    <td class="amount" style="text-align:right;"><?= Format::money($gasSummary['total_spent']) ?></td>
                                    <td class="amount" style="text-align:right;"><?= number_format($gasSummary['total_gallons'], 2) ?></td>
                                    <td class="amount" style="text-align:right;">Avg $<?= number_format($gasSummary['avg_price_per_gal'], 3) ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- RIGHT: Vehicle Fuel Profile & Fuel Stats -->
    <section>
        <div class="card" style="margin-top:0; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color:#fff; border:none;">
            <div class="card-body" style="padding:18px 20px;">
                <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.06em; opacity:0.85; font-weight:700;">Vehicle Fuel Profile</div>
                <div style="font-size:1.15rem; font-weight:700; margin:4px 0 16px 0;">18 Gallon Tank &bull; Target ~370 Miles &bull; 20.5 MPG</div>
                
                <div style="display:flex; flex-direction:column; gap:10px; font-size:0.92rem; border-top:1px solid rgba(255,255,255,0.25); padding-top:14px;">
                    <div style="display:flex; justify-content:space-between;">
                        <span style="opacity:0.9;">Total Spent:</span>
                        <strong style="font-size:1rem;">$<?= number_format($gasSummary['total_spent'], 2) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="opacity:0.9;">Total Gallons:</span>
                        <strong><?= number_format($gasSummary['total_gallons'], 1) ?> gal</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="opacity:0.9;">Avg Price / Gal:</span>
                        <strong>$<?= number_format($gasSummary['avg_price_per_gal'], 3) ?></strong>
                    </div>
                    <?php if ($gasSummary['avg_mpg'] > 0): ?>
                        <div style="display:flex; justify-content:space-between;">
                            <span style="opacity:0.9;">Avg Economy:</span>
                            <strong style="color:#bbf7d0;"><?= $gasSummary['avg_mpg'] ?> MPG</strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

</div>
