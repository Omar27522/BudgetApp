<?php
// views/tabs/bills_income.php - Tab 1: Monthly Bills & Income
?>
<div class="content-grid">

    <!-- LEFT: Monthly Recurring Bills ("Cash Out") -->
    <section>
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    Monthly Payments ("Cash Out")
                    <span class="card-badge"><?= count($bills) ?> bills</span>
                </span>
                <?php if (empty($bills) && count($allMonths) > 1): ?>
                    <form method="POST" action="index.php" style="display:inline;">
                        <input type="hidden" name="action" value="copy_bills">
                        <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                        <input type="hidden" name="tab" value="bills">
                        <select name="source_month_id" class="form-control" style="font-size:0.75rem; padding:3px 6px; display:inline-block; width:auto;">
                            <?php foreach ($allMonths as $m): if ($m['id'] != $selectedMonthId): ?>
                                <option value="<?= $m['id'] ?>">From <?= htmlspecialchars($m['label']) ?></option>
                            <?php endif; endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-secondary btn-sm">Copy Bills</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card-body" style="padding:0;">
                <?php if (empty($bills)): ?>
                    <div class="empty-state">
                        <p>No monthly payments recorded for <strong><?= htmlspecialchars($currentMonth['label']) ?></strong> yet.</p>
                        <p style="font-size:0.8rem;">Add your recurring bills (Rent, Truck, Insurance, Utilities, Phone) using the form below.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="stack-table bills-table">
                            <thead>
                                <tr>
                                    <th>Due</th>
                                    <th>Bill / Payee</th>
                                    <th style="text-align:right;">Estimate</th>
                                    <th style="text-align:center;">Status</th>
                                    <th style="text-align:right;">Actual Paid</th>
                                    <th>Date Paid</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bills as $b): 
                                    $bPayments = $allBillPayments[$b['id']] ?? [];
                                    $paymentCount = count($bPayments);
                                    $est = (float)$b['estimated_amount'];
                                    $act = (float)$b['actual_amount'];
                                    $isPaid = (int)$b['is_paid'] === 1;
                                    $isEditingThis = ($editBillId && (int)$b['id'] === $editBillId);
                                    $remaining = max(0.0, $est - $act);
                                    $isPartial = (!$isPaid && $act > 0 && $act < $est);
                                    $rowClass = $isPaid ? 'is-paid-row' : ($isPartial ? 'is-partial-row' : 'is-unpaid-row');
                                ?>
                                    <?php if ($isEditingThis): ?>
                                        <!-- INLINE EDIT ROW -->
                                        <tr style="background:#fffdf5; border-top:2px solid var(--warning); border-bottom:2px solid var(--warning);" id="bill-<?= $b['id'] ?>">
                                            <td colspan="7" style="padding:18px 20px;">
                                                <!-- Top Bar: Title & Cancel -->
                                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                                                    <div>
                                                        <span style="font-size:1.05rem; font-weight:800; color:#92400e; display:flex; align-items:center; gap:8px;">
                                                            ✏️ Editing Bill: <?= htmlspecialchars($b['name']) ?>
                                                        </span>
                                                        <span style="font-size:0.8rem; color:#b45309;">
                                                            Due: <strong><?= htmlspecialchars($b['due_date_label']) ?></strong> &bull; Category: <strong><?= htmlspecialchars($b['category'] ?: 'Bill') ?></strong>
                                                        </span>
                                                    </div>
                                                    <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=bills#bill-<?= $b['id'] ?>" class="btn btn-secondary btn-sm" style="font-weight:700;">&times; Close</a>
                                                </div>

                                                <!-- 1. Bill Details Form -->
                                                <form method="POST" action="index.php#bill-<?= $b['id'] ?>" style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:14px; margin-bottom:16px;">
                                                    <input type="hidden" name="action" value="update_bill">
                                                    <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                    <input type="hidden" name="tab" value="bills">
                                                    <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">

                                                    <div style="font-size:0.82rem; font-weight:700; color:#374151; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.04em;">
                                                        1. Bill Information
                                                    </div>

                                                    <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));">
                                                        <div class="form-group">
                                                            <label class="form-label">Due Date Label</label>
                                                            <input type="text" name="due_date_label" class="form-control" value="<?= htmlspecialchars($b['due_date_label']) ?>" required placeholder="e.g. 1st, 14th, Friday">
                                                        </div>
                                                        <div class="form-group" style="grid-column: span 2;">
                                                            <label class="form-label">Bill / Payee Name</label>
                                                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($b['name']) ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="form-label">Category</label>
                                                            <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($b['category'] ?? 'Bill') ?>" placeholder="e.g. Living, Utility">
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="form-label">Estimated Amount ($)</label>
                                                            <input type="number" step="0.01" name="estimated_amount" class="form-control" value="<?= htmlspecialchars($b['estimated_amount']) ?>" required>
                                                        </div>
                                                        <div class="form-group" style="grid-column: span 2;">
                                                            <label class="form-label">Notes (Optional)</label>
                                                            <input type="text" name="notes" class="form-control" value="<?= htmlspecialchars($b['notes'] ?? '') ?>" placeholder="e.g. Cash envelope, split with family">
                                                        </div>
                                                    </div>

                                                    <div style="display:flex; justify-content:flex-end; margin-top:8px;">
                                                        <button type="submit" class="btn btn-primary btn-sm" style="font-weight:700;">Save Bill Details</button>
                                                    </div>
                                                </form>

                                                <!-- 2. Payment Dates & Deposits Log (Throughout the Month) -->
                                                <div style="background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding:16px;">
                                                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:8px;">
                                                        <div>
                                                            <span style="font-size:0.95rem; font-weight:800; color:#1e293b;">
                                                                💰 Payment Dates (<?= $paymentCount ?> recorded)
                                                            </span>
                                                            <span style="font-size:0.78rem; color:var(--text-muted); display:block;">
                                                                Default is 1 date paid per entry, but you can add multiple payments or weekly deposits throughout the month.
                                                            </span>
                                                        </div>
                                                        <div style="font-size:0.85rem; font-weight:700;">
                                                            Total Paid: <span style="color:var(--success); font-size:1rem;"><?= Format::money($act) ?></span> / <?= Format::money($est) ?>
                                                            <?php if ($act < $est): ?>
                                                                <span class="badge" style="background:#fffbeb; color:#b45309; margin-left:6px;"><?= Format::money($est - $act) ?> unpaid</span>
                                                            <?php else: ?>
                                                                <span class="badge" style="background:#f0fdf4; color:#15803d; margin-left:6px;">✓ Fully Paid</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <!-- Visual Progress Bar -->
                                                    <?php 
                                                        $pct = $est > 0 ? min(100, round(($act / $est) * 100)) : 100;
                                                        $barColor = $pct >= 100 ? '#10b981' : ($pct > 0 ? '#3b82f6' : '#e5e7eb');
                                                    ?>
                                                    <div style="background:#f1f5f9; border-radius:999px; height:8px; overflow:hidden; margin-bottom:14px;">
                                                        <div style="width:<?= $pct ?>%; background:<?= $barColor ?>; height:100%; transition:width 0.3s;"></div>
                                                    </div>

                                                    <!-- List of existing payments -->
                                                    <?php if (!empty($bPayments)): ?>
                                                        <table style="width:100%; font-size:0.85rem; margin-bottom:14px; border-collapse:collapse;">
                                                            <thead>
                                                                <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; font-size:0.75rem; color:#64748b;">
                                                                    <th style="padding:6px 8px; text-align:left;">Date Paid</th>
                                                                    <th style="padding:6px 8px; text-align:right;">Amount ($)</th>
                                                                    <th style="padding:6px 8px; text-align:left;">Note / Deposit Label</th>
                                                                    <th style="padding:6px 8px; text-align:right;">Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($bPayments as $pay): ?>
                                                                    <tr style="border-bottom:1px solid #f1f5f9;">
                                                                        <td colspan="4" style="padding:4px 0;">
                                                                            <form method="POST" action="index.php#bill-<?= $b['id'] ?>" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:4px 8px;">
                                                                                <input type="hidden" name="action" value="update_bill_payment">
                                                                                <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                                                <input type="hidden" name="tab" value="bills">
                                                                                <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">
                                                                                <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">

                                                                                <div style="width:140px;">
                                                                                    <input type="date" name="payment_date" class="form-control" value="<?= htmlspecialchars($pay['payment_date']) ?>" required style="padding:4px 6px; font-size:0.8rem;">
                                                                                </div>
                                                                                <div style="width:110px;">
                                                                                    <input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars($pay['amount']) ?>" required style="padding:4px 6px; font-size:0.8rem; text-align:right; font-weight:700; color:#15803d;">
                                                                                </div>
                                                                                <div style="flex:1; min-width:140px;">
                                                                                    <input type="text" name="notes" class="form-control" value="<?= htmlspecialchars($pay['notes'] ?? '') ?>" placeholder="e.g. 1st Friday, partial cash" style="padding:4px 6px; font-size:0.8rem;">
                                                                                </div>

                                                                                <button type="submit" class="btn btn-secondary btn-sm" title="Save this payment" style="padding:4px 8px; font-size:0.75rem;">Save</button>
                                                                                
                                                                                <button type="submit" form="del-pay-<?= $pay['id'] ?>" class="btn btn-danger btn-sm" title="Remove this payment" style="padding:4px 8px; font-size:0.75rem;">&times;</button>
                                                                            </form>

                                                                            <form id="del-pay-<?= $pay['id'] ?>" method="POST" action="index.php" style="display:none;" onsubmit="return confirm('Remove this payment entry?');">
                                                                                <input type="hidden" name="action" value="delete_bill_payment">
                                                                                <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                                                <input type="hidden" name="tab" value="bills">
                                                                                <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">
                                                                                <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">
                                                                            </form>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    <?php else: ?>
                                                        <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:6px; padding:10px 14px; margin-bottom:14px; font-size:0.82rem; color:#64748b;">
                                                            ℹ️ No payments logged yet for this bill this month. Record the payment date below.
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- + Add Another Payment Form -->
                                                    <form method="POST" action="index.php" style="background:#f0fdf4; border:1px solid #86efac; border-radius:6px; padding:12px; display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                                                        <input type="hidden" name="action" value="add_bill_payment">
                                                        <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                        <input type="hidden" name="tab" value="bills">
                                                        <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">

                                                        <div class="form-group" style="min-width:130px;">
                                                            <label class="form-label" style="color:#166534; font-weight:700;">+ Date Paid</label>
                                                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required style="padding:5px 8px; font-size:0.85rem; border-color:#86efac;">
                                                        </div>

                                                        <div class="form-group" style="width:120px;">
                                                            <label class="form-label" style="color:#166534; font-weight:700;">Amount ($)</label>
                                                            <input type="number" step="0.01" name="amount" class="form-control" value="<?= $remaining > 0 ? htmlspecialchars($remaining) : htmlspecialchars($b['estimated_amount']) ?>" placeholder="0.00" required style="padding:5px 8px; font-size:0.85rem; border-color:#86efac; font-weight:700;">
                                                        </div>

                                                        <div class="form-group" style="flex:1; min-width:160px;">
                                                            <label class="form-label" style="color:#166534; font-weight:700;">Note / Label (Optional)</label>
                                                            <input type="text" name="notes" class="form-control" placeholder="<?= $paymentCount > 0 ? 'e.g. Deposit #' . ($paymentCount + 1) . ', Friday cash' : 'e.g. Paid in full, cash envelope' ?>" style="padding:5px 8px; font-size:0.85rem; border-color:#86efac;">
                                                        </div>

                                                        <button type="submit" class="btn btn-success btn-sm" style="height:35px; font-weight:700; padding:0 14px; box-shadow:0 1px 2px rgba(16,185,129,0.2);">
                                                            + Add Payment Date
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <!-- STANDARD ROW -->
                                        <tr class="<?= $rowClass ?>" id="bill-<?= $b['id'] ?>">
                                            <td>
                                                <span class="badge badge-due"><?= htmlspecialchars($b['due_date_label']) ?></span>
                                            </td>
                                            <td>
                                                <strong class="item-name"><?= htmlspecialchars($b['name']) ?></strong>
                                                <?php if (!empty($b['category']) && $b['category'] !== 'Bill'): ?>
                                                    <span style="font-size:0.75rem; color:var(--text-muted); display:block;"><?= htmlspecialchars($b['category']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($b['notes'])): ?>
                                                    <span style="font-size:0.72rem; color:var(--text-muted); display:block;"><?= htmlspecialchars($b['notes']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="amount" style="text-align:right;">
                                                <?= Format::money((float)$b['estimated_amount']) ?>
                                            </td>
                                            <td style="text-align:center;">
                                                <form method="POST" action="index.php" style="display:inline;">
                                                    <input type="hidden" name="action" value="toggle_bill">
                                                    <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                    <input type="hidden" name="tab" value="bills">
                                                    <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">
                                                    <?php if ($isPaid): ?>
                                                        <button type="submit" class="btn-toggle-paid" title="Click to unmark as pending">&#10003; Paid</button>
                                                    <?php else: ?>
                                                        <button type="submit" class="btn-toggle-unpaid" title="Click to mark as paid">&#9675; Pay</button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                            <td class="amount" style="text-align:right;">
                                                <?php if ($act > 0): ?>
                                                    <div style="font-weight:700; color:var(--success);"><?= Format::money($act) ?></div>
                                                    <?php if ($paymentCount > 1): ?>
                                                        <span class="badge" style="background:#eff6ff; color:#1d4ed8; font-size:0.68rem; padding:1px 5px;"><?= $paymentCount ?> payments</span>
                                                    <?php endif; ?>
                                                    <?php if ($act < $est): ?>
                                                        <span style="color:#b45309; font-size:0.7rem; display:block; font-weight:600;"><?= Format::money($est - $act) ?> left</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span style="color:var(--text-muted);">&mdash;</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="white-space:nowrap;">
                                                <?php if ($paymentCount > 1): ?>
                                                    <!-- Multiple payments throughout month -->
                                                    <div style="display:flex; flex-direction:column; gap:3px;">
                                                        <?php foreach ($bPayments as $pay): ?>
                                                            <span style="font-size:0.75rem; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:4px; padding:2px 6px; display:inline-flex; justify-content:space-between; gap:6px;">
                                                                <span>📅 <?= htmlspecialchars(Format::shortDate($pay['payment_date'])) ?></span>
                                                                <strong style="color:#059669;"><?= Format::money((float)$pay['amount']) ?></strong>
                                                            </span>
                                                        <?php endforeach; ?>
                                                        <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=bills&edit_bill=<?= $b['id'] ?>#bill-<?= $b['id'] ?>" 
                                                           style="font-size:0.72rem; color:var(--primary); font-weight:700; text-decoration:none; margin-top:2px;">
                                                            + Add date
                                                        </a>
                                                    </div>
                                                <?php elseif ($paymentCount === 1): ?>
                                                    <!-- Default: 1 payment date -->
                                                    <div>
                                                        <span style="font-size:0.85rem; font-weight:600; color:#1f2937;">📅 <?= htmlspecialchars(Format::shortDate($b['paid_date'])) ?></span>
                                                        <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=bills&edit_bill=<?= $b['id'] ?>#bill-<?= $b['id'] ?>" 
                                                           style="font-size:0.72rem; color:var(--primary); text-decoration:none; display:block; margin-top:2px;">
                                                            + Add another date
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- Unpaid / 0 payments: quick date picker with checkmark button (works with zero JS) -->
                                                    <form method="POST" action="index.php#bill-<?= $b['id'] ?>" style="display:inline-flex; align-items:center; gap:4px;">
                                                        <input type="hidden" name="action" value="update_paid_date">
                                                        <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                        <input type="hidden" name="tab" value="bills">
                                                        <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">
                                                        <input type="date" name="paid_date" value="<?= date('Y-m-d') ?>" class="form-control" style="font-size:0.75rem; padding:2px 4px; width:120px;" aria-label="Date paid">
                                                        <button type="submit" class="btn btn-secondary btn-sm" title="Save date paid" style="padding:2px 6px; font-weight:700;">&#10003;</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align:right; white-space:nowrap;">
                                                <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=bills&edit_bill=<?= $b['id'] ?>#bill-<?= $b['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                                
                                                <form method="POST" action="index.php" style="display:inline;" onsubmit="return confirm('Delete this bill?');">
                                                    <input type="hidden" name="action" value="delete_bill">
                                                    <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                    <input type="hidden" name="tab" value="bills">
                                                    <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">&times;</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background:#f9fafb; font-weight:700;">
                                    <td colspan="2">Total Monthly Payments:</td>
                                    <td class="amount" style="text-align:right;"><?= Format::money($billsSummary['total_estimated']) ?></td>
                                    <td></td>
                                    <td class="amount" style="text-align:right; color:var(--success);"><?= Format::money($billsSummary['total_paid']) ?></td>
                                    <td colspan="2" style="font-size:0.8rem; color:var(--warning);">
                                        <?= Format::money($billsSummary['total_remaining']) ?> unpaid
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add New Bill Form -->
        <div class="form-card">
            <div class="form-title">+ Add Monthly Bill ("Cash Out")</div>
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="add_bill">
                <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                <input type="hidden" name="tab" value="bills">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="due_date_label">Due Date</label>
                        <input type="text" name="due_date_label" id="due_date_label" class="form-control" placeholder="1st, 14th, Friday" value="1st" required>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label" for="bill_name">Bill Name / Payee</label>
                        <input type="text" name="name" id="bill_name" class="form-control" placeholder="e.g. Rent, Truck, YouTube, Electric" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="estimated_amount">Estimate ($)</label>
                        <input type="number" step="0.01" name="estimated_amount" id="estimated_amount" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="category">Category</label>
                        <input type="text" name="category" id="category" class="form-control" placeholder="Housing, Utility, Auto" value="Bill">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">+ Add Bill</button>
                </div>
            </form>
        </div>
    </section>

    <!-- RIGHT: Income Streams -->
    <section>
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    Income Streams
                    <span class="card-badge"><?= count($incomes) ?> sources</span>
                </span>
            </div>

            <div class="card-body" style="padding:0;">
                <?php if (empty($incomes)): ?>
                    <div class="empty-state">
                        <p>No income streams added for <strong><?= htmlspecialchars($currentMonth['label']) ?></strong>.</p>
                        <p style="font-size:0.8rem;">Add your job paychecks, side gigs, or tips below.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="stack-table income-table">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th style="text-align:right;">Expected</th>
                                    <th style="text-align:center;">Status</th>
                                    <th style="text-align:right;">Actual</th>
                                    <th>Date</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incomes as $inc): 
                                    $isEditingInc = ($editIncomeId && (int)$inc['id'] === $editIncomeId);
                                    $isReceived = ((int)($inc['is_received'] ?? 0) === 1 || (float)$inc['actual_amount'] > 0);
                                    $isRecurring = ((int)($inc['is_recurring'] ?? 1) === 1);
                                ?>
                                    <?php if ($isEditingInc): ?>
                                        <!-- INLINE EDIT ROW -->
                                        <tr style="background:#fffbeb; border-top:2px solid var(--warning); border-bottom:2px solid var(--warning);" id="inc-<?= $inc['id'] ?>">
                                            <td colspan="6" style="padding:16px;">
                                                <form method="POST" action="index.php#inc-<?= $inc['id'] ?>">
                                                    <input type="hidden" name="action" value="update_income">
                                                    <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                    <input type="hidden" name="tab" value="bills">
                                                    <input type="hidden" name="income_id" value="<?= $inc['id'] ?>">

                                                    <div style="font-weight:700; margin-bottom:12px; color:#92400e; font-size:0.95rem; display:flex; justify-content:space-between; align-items:center;">
                                                        <span>✏️ Editing Income: <?= htmlspecialchars($inc['source_name']) ?></span>
                                                        <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=bills#inc-<?= $inc['id'] ?>" class="btn btn-secondary btn-sm">&times; Cancel</a>
                                                    </div>

                                                    <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));">
                                                        <div class="form-group" style="grid-column: span 2;">
                                                             <label class="form-label">Income Source</label>
                                                            <input type="text" name="source_name" class="form-control" value="<?= htmlspecialchars($inc['source_name']) ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="form-label">Expected ($)</label>
                                                            <input type="number" step="0.01" name="estimated_amount" class="form-control" value="<?= htmlspecialchars($inc['estimated_amount']) ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="form-label">Actual Received ($)</label>
                                                            <input type="number" step="0.01" name="actual_amount" class="form-control" value="<?= htmlspecialchars($inc['actual_amount']) ?>">
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="form-label">Date</label>
                                                            <input type="date" name="received_date" class="form-control" value="<?= htmlspecialchars($inc['received_date'] ?? '') ?>">
                                                        </div>
                                                    </div>

                                                    <div style="margin-top:10px;">
                                                        <label style="font-size:0.85rem; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                                                            <input type="checkbox" name="is_recurring" value="1" <?= $isRecurring ? 'checked' : '' ?>>
                                                            🔁 <strong>Keep this income</strong> (Fixed paycheck every 2 weeks / monthly)
                                                        </label>
                                                        <span style="font-size:0.75rem; color:var(--text-muted); display:block; margin-left:22px;">
                                                            Uncheck for variable income like commission, tips, or one-off bonuses.
                                                        </span>
                                                    </div>

                                                    <div class="form-actions" style="margin-top:12px;">
                                                        <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=bills#inc-<?= $inc['id'] ?>" class="btn btn-secondary">Cancel</a>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <!-- STANDARD ROW -->
                                        <tr class="<?= $isReceived ? 'is-received-row' : '' ?>" id="inc-<?= $inc['id'] ?>">
                                            <td>
                                                <strong class="item-name"><?= htmlspecialchars($inc['source_name']) ?></strong>
                                                
                                                <!-- Clickable Recurring / Variable Toggle Badge -->
                                                <div style="margin-top:2px;">
                                                    <form method="POST" action="index.php#inc-<?= $inc['id'] ?>" style="display:inline-block;">
                                                        <input type="hidden" name="action" value="toggle_income_recurring">
                                                        <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                        <input type="hidden" name="tab" value="bills">
                                                        <input type="hidden" name="income_id" value="<?= $inc['id'] ?>">
                                                        <?php if ($isRecurring): ?>
                                                            <button type="submit" class="badge-recur is-recurring" title="Fixed bi-weekly/monthly check. Tap to switch to Variable.">🔁 Fixed</button>
                                                        <?php else: ?>
                                                            <button type="submit" class="badge-recur is-variable" title="Variable income (commission/tips). Tap to switch to Fixed.">📊 Variable</button>
                                                        <?php endif; ?>
                                                    </form>
                                                </div>

                                                <?php if (!empty($inc['notes'])): ?>
                                                    <span style="font-size:0.72rem; color:var(--text-muted); display:block;"><?= htmlspecialchars($inc['notes']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="amount" style="text-align:right;">
                                                <?= Format::money((float)$inc['estimated_amount']) ?>
                                            </td>
                                            <td style="text-align:center;">
                                                <form method="POST" action="index.php#inc-<?= $inc['id'] ?>" style="display:inline;">
                                                    <input type="hidden" name="action" value="toggle_income">
                                                    <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                    <input type="hidden" name="tab" value="bills">
                                                    <input type="hidden" name="income_id" value="<?= $inc['id'] ?>">
                                                    <?php if ($isReceived): ?>
                                                        <button type="submit" class="btn-toggle-paid" title="Click to unmark as pending">&#10003; Received</button>
                                                    <?php else: ?>
                                                        <button type="submit" class="btn-toggle-unpaid" title="Click to record as received (keeps expected check amount)">&#9675; Receive</button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                            <td class="amount amount-actual" style="text-align:right;">
                                                <?php if ($isReceived && (float)$inc['actual_amount'] > 0): ?>
                                                    <span style="color:var(--success); font-weight:600;"><?= Format::money((float)$inc['actual_amount']) ?></span>
                                                <?php else: ?>
                                                    <span style="color:var(--text-muted);">&mdash;</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="white-space:nowrap;">
                                                <form method="POST" action="index.php#inc-<?= $inc['id'] ?>" class="date-chip-form">
                                                    <input type="hidden" name="action" value="update_income_date">
                                                    <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                    <input type="hidden" name="tab" value="bills">
                                                    <input type="hidden" name="income_id" value="<?= $inc['id'] ?>">
                                                    <label class="date-chip <?= empty($inc['received_date']) ? 'is-empty' : '' ?>" title="Tap to change date">
                                                        <span class="date-chip-text">&#128197; <?= !empty($inc['received_date']) ? htmlspecialchars(Format::shortDate($inc['received_date'])) : 'Set date' ?></span>
                                                        <input type="date" name="received_date" value="<?= htmlspecialchars($inc['received_date'] ?? '') ?>" class="date-chip-input" onchange="this.form.submit()" aria-label="Date received">
                                                    </label>
                                                    <noscript><button type="submit" class="btn btn-secondary btn-sm">&#10003;</button></noscript>
                                                </form>
                                            </td>
                                            <td style="text-align:right; white-space:nowrap;">
                                                <a href="index.php?month_id=<?= $selectedMonthId ?>&tab=bills&edit_income=<?= $inc['id'] ?>#inc-<?= $inc['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>

                                                <form method="POST" action="index.php" style="display:inline;" onsubmit="return confirm('Delete this income item?');">
                                                    <input type="hidden" name="action" value="delete_income">
                                                    <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                                                    <input type="hidden" name="tab" value="bills">
                                                    <input type="hidden" name="income_id" value="<?= $inc['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">&times;</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background:#f9fafb; font-weight:700;">
                                    <td>Total Income:</td>
                                    <td class="amount" style="text-align:right;"><?= Format::money($incomeSummary['total_estimated']) ?></td>
                                    <td></td>
                                    <td class="amount" style="text-align:right; color:var(--success);"><?= Format::money($incomeSummary['total_actual']) ?></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add New Income Form -->
        <div class="form-card">
            <div class="form-title">+ Add Income Stream</div>
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="add_income">
                <input type="hidden" name="month_id" value="<?= $selectedMonthId ?>">
                <input type="hidden" name="tab" value="bills">

                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label" for="source_name">Income Source</label>
                        <input type="text" name="source_name" id="source_name" class="form-control" placeholder="e.g. Primary Paycheck, LAPC, Tips, Extra" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="inc_estimated">Expected ($)</label>
                        <input type="number" step="0.01" name="estimated_amount" id="inc_estimated" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="inc_actual">Actual ($)</label>
                        <input type="number" step="0.01" name="actual_amount" id="inc_actual" class="form-control" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="inc_received_date">Date</label>
                        <input type="date" name="received_date" id="inc_received_date" class="form-control">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label style="font-size:0.85rem; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                            <input type="checkbox" name="is_recurring" value="1" checked>
                            🔁 <strong>Keep this income</strong> (Fixed check every 2 weeks / monthly)
                        </label>
                        <span style="font-size:0.75rem; color:var(--text-muted); display:block; margin-left:22px;">
                            Uncheck for variable income like commission, tips, or bonuses.
                        </span>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">+ Add Income</button>
                </div>
            </form>
        </div>
    </section>

</div>

